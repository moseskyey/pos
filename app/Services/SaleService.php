<?php

namespace App\Services;

use App\Contracts\FiscalDevice;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Pos\CartCalculator;
use App\Services\Pos\PriceResolver;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sale checkout, hold/resume and void. All money and stock rules are enforced
 * here; prices are always re-read from the database.
 */
class SaleService
{
    public function __construct(
        protected StockService $stock,
        protected DocumentNumberService $numbers,
        protected CustomerLedgerService $ledger,
        protected LoyaltyService $loyalty,
        protected PriceResolver $prices,
        protected ApprovalService $approvals,
    ) {}

    /**
     * Build authoritative, priced lines from a cart.
     *
     * @param  array<int, array{product_id:int, product_unit_id?:?int, qty:mixed, unit_price?:mixed, price_override?:bool, discount_type?:?string, discount_value?:mixed}>  $lines
     * @return array{lines: array<int, array>, products: Collection}
     */
    public function priceLines(array $lines, ?Customer $customer, User $user, array $approvals = []): array
    {
        if (! $lines) {
            throw new BusinessRuleException(__('The cart is empty.'));
        }

        $products = Product::query()->with('units.unit', 'unit')->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $priced = [];
        foreach (array_values($lines) as $i => $line) {
            $product = $products->get($line['product_id']);
            if (! $product || ! $product->is_active || $product->has_variants) {
                throw new BusinessRuleException(__('A product in the cart is no longer available.'));
            }
            $qty = Qty::round($line['qty'] ?? 0);
            if (! Qty::isPositive($qty)) {
                throw new BusinessRuleException(__('Quantity for :p must be greater than zero.', ['p' => $product->name]));
            }
            /** @var ProductUnit|null $unit */
            $unit = ! empty($line['product_unit_id']) ? $product->units->firstWhere('id', (int) $line['product_unit_id']) : null;
            if (! empty($line['product_unit_id']) && ! $unit) {
                throw new BusinessRuleException(__('Invalid unit for :p.', ['p' => $product->name]));
            }
            $allowsDecimal = $unit ? $unit->unit?->allow_decimal : ($product->unit?->allow_decimal ?? false);
            if (! $allowsDecimal && ! $product->is_weighted && Qty::cmp($qty, (string) (int) (float) $qty) !== 0) {
                throw new BusinessRuleException(__(':p must be sold in whole units.', ['p' => $product->name]));
            }

            $resolved = $this->prices->resolve($product, $unit, $qty, $customer);
            $price = $resolved['price'];
            $tier = $resolved['tier'];
            if (! empty($line['price_override']) && isset($line['unit_price']) && Money::cmp($line['unit_price'], $price) !== 0) {
                $this->authorize($user, 'sales.price_override', $approvals, 'price_override', __('Price override needs manager approval.'));
                $price = Money::round($line['unit_price']);
                $tier = 'override';
            }
            if (isset($line['line_total_override']) && Money::isPositive($line['line_total_override'])) {
                // Price-embedded scale barcode: the scale printed the line total.
                $price = Money::div($line['line_total_override'], $qty);
            }

            $factor = $unit ? (string) $unit->factor : '1';
            $priced[$i] = [
                'product' => $product,
                'unit' => $unit,
                'product_unit_id' => $unit?->id,
                'qty' => $qty,
                'factor' => $factor,
                'base_qty' => Qty::mul($qty, $factor),
                'unit_price' => Money::round($price),
                'list_price' => Money::round($unit ? $unit->retail_price : $product->retail_price),
                'tier' => $tier,
                'discount_type' => in_array($line['discount_type'] ?? null, ['percent', 'fixed'], true) ? $line['discount_type'] : null,
                'discount_value' => $line['discount_value'] ?? null,
                'tax_type' => $product->tax_type->value,
                'tax_rate' => $product->taxRate(),
                'unit_cost' => Money::mul($product->cost_price, $factor),
            ];
        }

        return ['lines' => $priced, 'products' => $products];
    }

    /**
     * Complete a sale.
     *
     * @param  array{lines: array, customer_id?: ?int, cart_discount_type?: ?string, cart_discount_value?: mixed, loyalty_points?: int, note?: ?string}  $cart
     * @param  array<int, array{method: string, amount: mixed, reference?: ?string}>  $payments
     * @param  array<string, int>  $approvals  action => approver user id (verified by the caller via manager PIN)
     */
    public function checkout(array $cart, array $payments, User $cashier, Shift $shift, string $idempotencyKey, array $approvals = [], string $status = 'completed'): Sale
    {
        if ($existing = Sale::withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }
        if (! $shift->isOpen() || $shift->user_id !== $cashier->id) {
            throw new BusinessRuleException(__('Open a shift before selling.'));
        }

        $sale = DB::transaction(function () use ($cart, $payments, $cashier, $shift, $idempotencyKey, $approvals, $status) {
            $customer = ! empty($cart['customer_id']) ? Customer::lockForUpdate()->find($cart['customer_id']) : null;
            if (! empty($cart['customer_id']) && (! $customer || ! $customer->is_active)) {
                throw new BusinessRuleException(__('Customer not found.'));
            }

            ['lines' => $lines] = $this->priceLines($cart['lines'], $customer, $cashier, $approvals);

            // Loyalty redemption is applied as an extra fixed cart discount.
            $redeemPoints = 0;
            $loyaltyValue = '0.00';
            if (! empty($cart['loyalty_points']) && $customer && $this->loyalty->enabled()) {
                $redeemPoints = min((int) $cart['loyalty_points'], $customer->loyalty_points);
                $loyaltyValue = $this->loyalty->valueOf($redeemPoints);
            }

            $calculator = CartCalculator::fromSettings();
            $calcLines = array_map(fn ($l) => Arr::only($l, ['qty', 'unit_price', 'discount_type', 'discount_value', 'tax_rate']), $lines);
            $preview = $calculator->calculate($calcLines, $cart['cart_discount_type'] ?? null, $cart['cart_discount_value'] ?? null);
            $cartDiscountAmount = Money::add($preview['cart_discount'], $loyaltyValue);
            $totals = Money::isPositive($loyaltyValue)
                ? $calculator->calculate($calcLines, 'fixed', $cartDiscountAmount)
                : $preview;

            $this->checkDiscounts($lines, $totals, $preview, $cashier, $approvals);
            $this->checkBelowCost($lines, $totals, $cashier, $approvals);

            // Payments ------------------------------------------------------
            [$paymentRows, $paid, $tendered, $change, $creditAmount, $storeCreditAmount] = $this->preparePayments($payments, $totals['total'], $customer, $cashier, $approvals, $status);

            // Sale ----------------------------------------------------------
            $sale = Sale::withoutGlobalScopes()->create([
                'branch_id' => $shift->branch_id,
                'register_id' => $shift->register_id,
                'shift_id' => $shift->id,
                'user_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'number' => $this->numbers->next($status === 'layaway' ? 'layaway' : 'invoice', $shift->branch_id),
                'status' => $status,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'rounding' => $totals['rounding'],
                'total' => $totals['total'],
                'paid_total' => Money::sub($paid, $creditAmount),
                'tendered' => $tendered,
                'change_due' => $change,
                'balance_due' => Money::add(Money::sub($totals['total'], $paid), $creditAmount),
                'cart_discount_type' => $cart['cart_discount_type'] ?? null,
                'cart_discount_value' => $cart['cart_discount_value'] ?? null,
                'note' => $cart['note'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'completed_at' => $status === 'completed' ? now() : null,
                'loyalty_redeemed' => $redeemPoints,
            ]);

            // Items + stock ---------------------------------------------------
            $policy = setting('pos.negative_stock', 'block');
            $allowNegative = $policy === 'allow' || ($policy === 'warn' && ($cashier->can('sales.negative_stock') || isset($approvals['negative_stock'])));
            foreach ($lines as $key => $line) {
                $calc = $totals['lines'][$key];
                $item = $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'product_unit_id' => $line['product_unit_id'],
                    'name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'unit_name' => $line['unit']?->unit?->short_name ?? $line['product']->unit?->short_name,
                    'conversion_factor' => $line['factor'],
                    'quantity' => $line['qty'],
                    'base_quantity' => $line['base_qty'],
                    'unit_price' => $line['unit_price'],
                    'list_price' => $line['list_price'],
                    'price_tier' => $line['tier'],
                    'cost_price' => $line['unit_cost'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_type'] ? Money::round($line['discount_value']) : null,
                    'discount_amount' => $calc['discount_amount'],
                    'cart_discount_share' => $calc['cart_discount_share'],
                    'tax_type' => $line['tax_type'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $calc['tax_amount'],
                    'line_total' => $calc['line_total'],
                ]);

                try {
                    $movements = $this->stock->issue($sale->branch_id, $line['product'], $line['base_qty'], MovementType::Sale, $sale, $allowNegative);
                } catch (InsufficientStockException $e) {
                    if ($policy === 'warn') {
                        throw new ApprovalRequiredException('negative_stock', 'sales.negative_stock', $e->getMessage().' '.__('A manager can approve selling beyond stock.'));
                    }
                    throw new BusinessRuleException($e->getMessage());
                }
                if ($movements->isNotEmpty() && Qty::isPositive($line['base_qty'])) {
                    $cost = Money::div(Money::sum($movements, fn ($m) => Money::mul(Qty::abs($m->quantity), $m->unit_cost)), $line['base_qty']);
                    $item->update(['cost_price' => Money::mul($cost, $line['factor'])]);
                }
            }

            // Payment rows ----------------------------------------------------
            foreach ($paymentRows as $row) {
                $sale->payments()->create($row + ['branch_id' => $sale->branch_id, 'shift_id' => $shift->id, 'received_by' => $cashier->id]);
            }

            if ($customer && Money::isPositive($creditAmount)) {
                $this->ledger->post($customer, 'sale', $creditAmount, 0, $sale, __('Credit sale :n', ['n' => $sale->number]), dueDate: now()->addDays(30)->toDateString());
            }
            if ($customer && Money::isPositive($storeCreditAmount)) {
                $this->ledger->post($customer, 'store_credit_used', $storeCreditAmount, 0, $sale, __('Used on :n', ['n' => $sale->number]), 'store_credit');
            }
            if ($customer && $status === 'layaway' && Money::isPositive($sale->balance_due)) {
                // Layaway: the balance is tracked on the sale, not as debt.
            }

            if ($customer && $this->loyalty->enabled()) {
                if ($redeemPoints > 0) {
                    $this->loyalty->redeem($customer, $sale, $redeemPoints);
                }
                if ($status === 'completed') {
                    $earned = $this->loyalty->earn($customer, $sale, Money::sub($totals['total'], $creditAmount));
                    $sale->update(['loyalty_earned' => $earned]);
                }
            }

            $this->recordApprovals($sale, $cashier, $approvals, $totals);

            return $sale;
        });

        $this->submitFiscal($sale);

        return $sale->fresh(['items', 'payments', 'customer']);
    }

    /** Park the current cart for later. */
    public function hold(array $cart, User $cashier, ?Shift $shift, int $branchId, ?string $note = null): Sale
    {
        return DB::transaction(function () use ($cart, $cashier, $shift, $branchId, $note) {
            $sale = Sale::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'register_id' => $shift?->register_id,
                'shift_id' => $shift?->id,
                'user_id' => $cashier->id,
                'customer_id' => $cart['customer_id'] ?? null,
                'status' => SaleStatus::Held,
                'hold_note' => $note ?: __('Held :time', ['time' => now()->format('H:i')]),
                'cart_discount_type' => $cart['cart_discount_type'] ?? null,
                'cart_discount_value' => $cart['cart_discount_value'] ?? null,
            ]);
            $total = '0';
            foreach ($cart['lines'] as $line) {
                $product = Product::find($line['product_id']);
                if (! $product) {
                    continue;
                }
                $lineTotal = Money::mul($line['qty'], $line['unit_price']);
                $total = Money::add($total, $lineTotal);
                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_unit_id' => $line['product_unit_id'] ?? null,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit_name' => $line['unit'] ?? null,
                    'conversion_factor' => $line['factor'] ?? 1,
                    'quantity' => $line['qty'],
                    'base_quantity' => Qty::mul($line['qty'], $line['factor'] ?? 1),
                    'unit_price' => $line['unit_price'],
                    'list_price' => $line['unit_price'],
                    'price_tier' => ! empty($line['price_override']) ? 'override' : 'retail',
                    'discount_type' => $line['discount_type'] ?? null,
                    'discount_value' => $line['discount_value'] ?? null,
                    'tax_type' => $product->tax_type->value,
                    'tax_rate' => $product->taxRate(),
                    'line_total' => $lineTotal,
                ]);
            }
            $sale->update(['subtotal' => $total, 'total' => $total]);

            return $sale;
        });
    }

    /** Resume a held sale: returns the cart lines and deletes the hold. */
    public function resume(Sale $sale, User $user): array
    {
        if ($sale->status !== SaleStatus::Held) {
            throw new BusinessRuleException(__('This sale is not on hold.'));
        }
        $cart = [
            'customer_id' => $sale->customer_id,
            'cart_discount_type' => $sale->cart_discount_type,
            'cart_discount_value' => $sale->cart_discount_value !== null ? (float) $sale->cart_discount_value : null,
            'lines' => $sale->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'qty' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'price_override' => $i->price_tier === 'override',
                'discount_type' => $i->discount_type,
                'discount_value' => $i->discount_value !== null ? (float) $i->discount_value : null,
            ])->all(),
        ];
        DB::transaction(function () use ($sale) {
            $sale->items()->delete();
            $sale->delete();
        });

        return $cart;
    }

    /** Void a same-day sale: restores stock, reverses account entries and loyalty. */
    public function void(Sale $sale, User $approver, string $reason, ?User $requester = null): Sale
    {
        return DB::transaction(function () use ($sale, $approver, $reason, $requester) {
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== SaleStatus::Completed) {
                throw new BusinessRuleException(__('Only completed sales can be voided.'));
            }
            if (! $sale->created_at->isToday()) {
                throw new BusinessRuleException(__('Sales can only be voided on the same day. Use a return instead.'));
            }
            if (class_exists(SaleReturn::class) && $sale->returns()->exists()) {
                throw new BusinessRuleException(__('This sale has returns and cannot be voided.'));
            }

            $movements = StockMovement::withoutGlobalScopes()->with('product')
                ->where('reference_type', $sale->getMorphClass())->where('reference_id', $sale->id)
                ->where('type', MovementType::Sale->value)->get();
            foreach ($movements as $movement) {
                $this->stock->receive($sale->branch_id, $movement->product, Qty::abs($movement->quantity), MovementType::Void, $sale,
                    $movement->unit_cost, null, null, __('Void :n', ['n' => $sale->number]), $movement->batch_id);
            }

            if ($sale->customer_id) {
                $customer = Customer::withTrashed()->find($sale->customer_id);
                $credit = Money::sum($sale->payments->where('method', PaymentMethod::Credit), 'amount');
                if (Money::isPositive($credit)) {
                    $this->ledger->post($customer, 'void', 0, $credit, $sale, __('Void :n', ['n' => $sale->number]));
                }
                $storeCredit = Money::sum($sale->payments->where('method', PaymentMethod::StoreCredit), 'amount');
                if (Money::isPositive($storeCredit)) {
                    $this->ledger->post($customer, 'void', 0, $storeCredit, $sale, __('Void :n', ['n' => $sale->number]), 'store_credit');
                }
                $this->loyalty->reverse($sale);
            }

            $sale->update(['status' => SaleStatus::Voided, 'voided_at' => now(), 'voided_by' => $approver->id, 'void_reason' => $reason]);
            $this->approvals->record('void', $requester ?? $approver, $approver, $sale, $sale->total, $reason);

            return $sale;
        });
    }

    // ---------------------------------------------------------------------

    protected function authorize(User $user, string $permission, array $approvals, string $action, string $message): void
    {
        if ($user->can($permission)) {
            return;
        }
        $approverId = $approvals[$action] ?? null;
        $approver = $approverId ? User::find($approverId) : null;
        if (! $approver || ! $approver->can($permission)) {
            throw new ApprovalRequiredException($action, $permission, $message);
        }
    }

    protected function checkDiscounts(array $lines, array $totals, array $preview, User $user, array $approvals): void
    {
        $max = setting('pos.max_discount_percent', 10);
        $hasDiscount = Money::isPositive($preview['discount_total']);
        if (! $hasDiscount) {
            return;
        }
        if (! $user->can('sales.discount')) {
            $this->authorize($user, 'sales.discount', $approvals, 'discount', __('Discounts need manager approval.'));
        }

        $above = false;
        foreach ($lines as $key => $line) {
            $calc = $totals['lines'][$key];
            if (Money::isPositive($calc['discount_amount']) && Money::gt(CartCalculator::discountPercent($calc['gross'], $calc['discount_amount']), $max)) {
                $above = true;
            }
        }
        $afterLines = Money::sub($preview['subtotal'], $preview['line_discounts']);
        if (Money::isPositive($preview['cart_discount']) && Money::gt(CartCalculator::discountPercent($afterLines, $preview['cart_discount']), $max)) {
            $above = true;
        }
        if ($above) {
            $this->authorize($user, 'sales.discount.above_limit', $approvals, 'discount', __('Discounts above :max% need manager approval.', ['max' => $max]));
        }
    }

    protected function checkBelowCost(array $lines, array $totals, User $user, array $approvals): void
    {
        $policy = setting('pos.below_cost', 'approval');
        if ($policy === 'allow') {
            return;
        }
        foreach ($lines as $key => $line) {
            if (! Money::isPositive($line['unit_cost'])) {
                continue;
            }
            // Compare the VAT-exclusive selling price with the (VAT-exclusive) cost.
            $calc = $totals['lines'][$key];
            $netUnit = Money::div(Money::sub($calc['net_total'], setting('tax.prices_include_vat', true) ? $calc['tax_amount'] : 0), $line['qty']);
            if (Money::lt($netUnit, $line['unit_cost'])) {
                if ($policy === 'block') {
                    throw new BusinessRuleException(__(':p cannot be sold below cost.', ['p' => $line['product']->name]));
                }
                $this->authorize($user, 'sales.below_cost', $approvals, 'below_cost', __(':p is below cost. Manager approval required.', ['p' => $line['product']->name]));
            }
        }
    }

    /**
     * @return array{0: array, 1: string, 2: string, 3: string, 4: string, 5: string}
     */
    protected function preparePayments(array $payments, string $total, ?Customer $customer, User $user, array $approvals, string $status): array
    {
        $rows = [];
        $remaining = $total;
        $tendered = '0.00';
        $change = '0.00';
        $credit = '0.00';
        $storeCredit = '0.00';
        $enabled = collect(PaymentMethod::enabled())->map->value->all();

        // Non-cash first so cash absorbs change.
        usort($payments, fn ($a, $b) => ($a['method'] === 'cash') <=> ($b['method'] === 'cash'));

        foreach ($payments as $payment) {
            $method = PaymentMethod::tryFrom($payment['method'] ?? '');
            $amount = Money::round($payment['amount'] ?? 0);
            if (! $method || ! Money::isPositive($amount)) {
                continue;
            }
            if (! in_array($method->value, $enabled, true)) {
                throw new BusinessRuleException(__(':m payments are disabled.', ['m' => $method->label()]));
            }

            if ($method === PaymentMethod::Cash) {
                $tendered = Money::add($tendered, $amount);
                $applied = Money::min($amount, $remaining);
                $change = Money::add($change, Money::sub($amount, $applied));
                $amount = $applied;
            } elseif (Money::gt($amount, $remaining)) {
                throw new BusinessRuleException(__(':m amount is more than the balance due.', ['m' => $method->label()]));
            }
            if (! Money::isPositive($amount)) {
                continue;
            }

            if ($method->isMobileMoney() && empty($payment['reference']) && empty($payment['gateway_reference'])) {
                throw new BusinessRuleException(__('Enter the :m transaction reference.', ['m' => $method->label()]));
            }
            if ($method === PaymentMethod::Credit) {
                if (! $customer) {
                    throw new BusinessRuleException(__('Select a registered customer for credit sales.'));
                }
                $newBalance = Money::add($customer->balance, Money::add($credit, $amount));
                if (Money::gt($newBalance, $customer->credit_limit)) {
                    $this->authorize($user, 'sales.credit.above_limit', $approvals, 'credit_limit',
                        __(':c credit limit (:l) exceeded. Manager approval required.', ['c' => $customer->name, 'l' => money($customer->credit_limit)]));
                }
                $credit = Money::add($credit, $amount);
            }
            if ($method === PaymentMethod::StoreCredit) {
                if (! $customer || Money::gt(Money::add($storeCredit, $amount), $customer->store_credit)) {
                    throw new BusinessRuleException(__('Not enough store credit.'));
                }
                $storeCredit = Money::add($storeCredit, $amount);
            }

            $rows[] = [
                'method' => $method->value,
                'amount' => $amount,
                'reference' => $payment['reference'] ?? null,
                'gateway' => $payment['gateway'] ?? null,
                'gateway_status' => $payment['gateway_status'] ?? null,
                'gateway_reference' => $payment['gateway_reference'] ?? null,
                'meta' => $method === PaymentMethod::Cash ? ['tendered' => $payment['amount'], 'change' => Money::sub($payment['amount'], $amount)] : null,
            ];
            $remaining = Money::sub($remaining, $amount);
        }

        $paid = Money::sub($total, $remaining);
        if ($status === 'completed' && Money::isPositive($remaining)) {
            throw new BusinessRuleException(__('Payment is short by :amount.', ['amount' => money($remaining)]));
        }
        if ($status === 'layaway' && ! $customer) {
            throw new BusinessRuleException(__('Select a registered customer for layaway.'));
        }

        return [$rows, $paid, $tendered, $change, $credit, $storeCredit];
    }

    protected function recordApprovals(Sale $sale, User $cashier, array $approvals, array $totals): void
    {
        foreach ($approvals as $action => $approverId) {
            if ($approver = User::find($approverId)) {
                $this->approvals->record($action, $cashier, $approver, $sale, $action === 'discount' ? $totals['discount_total'] : $sale->total);
            }
        }
        $max = setting('pos.max_discount_percent', 10);
        if (Money::isPositive($totals['discount_total']) && Money::gt(CartCalculator::discountPercent($totals['subtotal'], $totals['discount_total']), $max)) {
            activity('sales')->causedBy($cashier)->performedOn($sale)
                ->withProperties(['discount' => $totals['discount_total'], 'subtotal' => $totals['subtotal']])
                ->log('Discount above limit');
        }
    }

    protected function submitFiscal(Sale $sale): void
    {
        try {
            $result = app(FiscalDevice::class)->submit($sale);
            if ($result->submitted) {
                $sale->update(['fiscal_code' => $result->verificationCode, 'fiscal_qr' => $result->qrPayload]);
            }
        } catch (Throwable $e) {
            Log::warning('Fiscal submission failed for sale '.$sale->id.': '.$e->getMessage());
        }
    }
}
