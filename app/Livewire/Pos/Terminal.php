<?php

namespace App\Livewire\Pos;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\RequiresApproval;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\CustomerLedgerService;
use App\Services\LoyaltyService;
use App\Services\PaymentService;
use App\Services\Pos\CartCalculator;
use App\Services\Pos\PriceResolver;
use App\Services\ProductService;
use App\Services\QuotationService;
use App\Services\ReceiptService;
use App\Services\SaleService;
use App\Services\ShareService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\BranchContext;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Terminal extends Component
{
    use RequiresApproval;

    // Cart -------------------------------------------------------------------
    public array $cart = [];

    public ?int $customerId = null;

    public ?string $cartDiscountType = null;

    public $cartDiscountValue = null;

    public int $loyaltyPoints = 0;

    public ?string $selectedLine = null;

    public string $note = '';

    #[Locked]
    public string $idempotencyKey = '';

    #[Locked]
    public ?int $quotationId = null;

    // Catalogue ----------------------------------------------------------------
    public string $search = '';

    public ?int $categoryId = null;

    public string $view = 'grid';

    // Shift --------------------------------------------------------------------
    #[Locked]
    public ?int $shiftId = null;

    public ?int $registerId = null;

    public $openingFloat = 0;

    // Modals -------------------------------------------------------------------
    public ?string $modal = null; // customer | discount | line | held | payment | help | hold

    public string $customerSearch = '';

    public array $newCustomer = ['name' => '', 'phone' => '', 'type' => 'retail'];

    public array $discountForm = ['type' => 'percent', 'value' => null];

    public array $lineForm = ['key' => null, 'qty' => null, 'unit_price' => null, 'discount_type' => 'percent', 'discount_value' => null];

    public string $holdNote = '';

    // Payment ------------------------------------------------------------------
    public array $payments = [];

    public ?array $completed = null;

    public function mount(ShiftService $shifts): void
    {
        abort_unless(auth()->user()->can('pos.access'), 403);
        $branchId = app(BranchContext::class)->currentId();
        $this->shiftId = $branchId ? $shifts->current(auth()->user(), $branchId)?->id : null;
        $this->registerId = Register::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->idempotencyKey = (string) Str::uuid();
        $this->customerId = setting('pos.default_customer_id') ?: null;

        if ($quotation = request()->integer('quotation')) {
            $this->loadQuotation($quotation);
        }
    }

    public function loadQuotation(int $id): void
    {
        $quote = Sale::query()->with('items')->find($id);
        if (! $quote) {
            return;
        }
        try {
            $cart = app(QuotationService::class)->toCart($quote);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->clearCart();
        $this->quotationId = $quote->id;
        $this->customerId = $cart['customer_id'];
        $this->cartDiscountType = $cart['cart_discount_type'];
        $this->cartDiscountValue = $cart['cart_discount_value'];
        foreach ($cart['lines'] as $line) {
            $this->addLine($line['product_id'], $line['product_unit_id'], $line['qty']);
            $key = $this->selectedLine;
            if ($key && $line['price_override']) {
                $this->cart[$key]['unit_price'] = $line['unit_price'];
                $this->cart[$key]['price_override'] = true;
                $this->cart[$key]['tier'] = 'override';
            }
            if ($key) {
                $this->cart[$key]['discount_type'] = $line['discount_type'];
                $this->cart[$key]['discount_value'] = $line['discount_value'];
            }
        }
    }

    // ------------------------------------------------------------------ Shift --

    public function openShift(ShiftService $shifts): void
    {
        abort_unless(auth()->user()->can('shifts.open'), 403);
        $this->validate(['registerId' => ['required', 'exists:registers,id'], 'openingFloat' => ['required', 'numeric', 'min:0']]);
        $register = Register::findOrFail($this->registerId);
        try {
            $this->shiftId = $shifts->open($register, auth()->user(), $this->openingFloat)->id;
            $this->dispatch('toast', message: __('Shift opened. Karibu!'), type: 'success');
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function shift(): ?Shift
    {
        return $this->shiftId ? Shift::withoutGlobalScopes()->with('register')->find($this->shiftId) : null;
    }

    // -------------------------------------------------------------- Products --

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'color']);
    }

    #[Computed]
    public function products(): Collection
    {
        $term = trim($this->search);
        $query = Product::query()->active()->sellable()->with(['unit'])->orderBy('name');
        if ($term !== '') {
            $query->search($term);
        } elseif ($this->categoryId) {
            $query->whereIn('category_id', Category::find($this->categoryId)?->descendantIds() ?? [$this->categoryId]);
        }
        $products = $query->limit($term !== '' ? 30 : 60)->get();

        $branchId = $this->shift?->branch_id;
        $stock = $branchId ? app(StockService::class)->availableMany($branchId, $products->pluck('id')->all()) : [];
        $products->each(fn ($p) => $p->setAttribute('available', $p->track_stock ? (float) ($stock[$p->id] ?? 0) : null));

        return $products;
    }

    /** Enter in the search box – barcode scanner or keyboard. */
    public function scan(): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        $match = app(ProductService::class)->findByBarcode($term);
        if ($match) {
            $this->addLine($match['product']->id, $match['unit']?->id, $match['quantity'], $match['quantity'] !== null ? $term : null);
            $this->search = '';
            unset($this->products);

            return;
        }
        if ($this->products->count() === 1) {
            $this->addProduct($this->products->first()->id);
            $this->search = '';
            unset($this->products);

            return;
        }
        if ($this->products->isEmpty()) {
            $this->dispatch('scan-fail');
            $this->dispatch('toast', message: __('No product matches “:t”.', ['t' => $term]), type: 'warning');
        }
    }

    /** Tap / click a product (callable from the browser: never takes a price or quantity). */
    public function addProduct(int $productId, ?int $unitId = null): void
    {
        $this->addLine($productId, $unitId);
    }

    /**
     * Add a line. $scaleBarcode is the scanned price-embedded label: the server
     * re-reads it at checkout, so the browser can never set a line total.
     */
    protected function addLine(int $productId, ?int $unitId = null, $quantity = null, ?string $scaleBarcode = null): void
    {
        $product = Product::with(['units.unit', 'unit'])->find($productId);
        if (! $product || ! $product->is_active || $product->has_variants) {
            $this->dispatch('scan-fail');

            return;
        }
        $quantity = $quantity !== null ? (float) $quantity : 1.0;

        // Increment an existing identical line (not for scale barcodes).
        if ($scaleBarcode === null) {
            foreach ($this->cart as $key => $line) {
                if ($line['product_id'] === $product->id && $line['product_unit_id'] === $unitId && empty($line['scale_barcode'])) {
                    $this->cart[$key]['qty'] = (float) Qty::add($line['qty'], $quantity);
                    $this->selectedLine = $key;
                    $this->reprice($key);
                    $this->dispatch('scan-ok');

                    return;
                }
            }
        }

        $unit = $unitId ? $product->units->firstWhere('id', $unitId) : null;
        $key = 'l'.Str::random(6);
        $this->cart[$key] = [
            'product_id' => $product->id,
            'product_unit_id' => $unit?->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $unit?->unit?->short_name ?? $product->unit?->short_name,
            'factor' => (float) ($unit?->factor ?? 1),
            'qty' => $quantity,
            'unit_price' => 0,
            'list_price' => 0,
            'tier' => 'retail',
            'price_override' => false,
            'scale_barcode' => $scaleBarcode,
            'discount_type' => null,
            'discount_value' => null,
            'tax_rate' => (float) $product->taxRate(),
            'track_stock' => $product->track_stock,
            'decimal' => (bool) ($product->is_weighted || ($unit?->unit ?? $product->unit)?->allow_decimal),
            'units' => collect([['id' => null, 'label' => $product->unit?->short_name ?? 'pc', 'factor' => 1]])
                ->merge($product->units->map(fn (ProductUnit $u) => ['id' => $u->id, 'label' => $u->unit->short_name.' ×'.qty($u->factor), 'factor' => (float) $u->factor]))->all(),
        ];
        $this->selectedLine = $key;
        $this->reprice($key);
        $this->dispatch('scan-ok');
    }

    public function setQty(string $key, $qty): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }
        $qty = is_numeric($qty) ? (float) $qty : 0;
        if ($qty <= 0) {
            $this->removeLine($key);

            return;
        }
        if (! $this->cart[$key]['decimal']) {
            $qty = max(1, (int) round($qty));
        }
        $this->cart[$key]['qty'] = $qty;
        $this->reprice($key);
    }

    public function increment(string $key, int $by = 1): void
    {
        if (isset($this->cart[$key])) {
            $this->setQty($key, (float) $this->cart[$key]['qty'] + $by);
        }
    }

    public function changeUnit(string $key, $unitId): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }
        $unitId = $unitId !== '' && $unitId !== null ? (int) $unitId : null;
        $product = Product::with('units.unit', 'unit')->find($this->cart[$key]['product_id']);
        $unit = $unitId ? $product->units->firstWhere('id', $unitId) : null;
        $this->cart[$key]['product_unit_id'] = $unit?->id;
        $this->cart[$key]['unit'] = $unit?->unit?->short_name ?? $product->unit?->short_name;
        $this->cart[$key]['factor'] = (float) ($unit?->factor ?? 1);
        $this->cart[$key]['price_override'] = false;
        $this->reprice($key);
    }

    public function removeLine(string $key): void
    {
        unset($this->cart[$key]);
        if ($this->selectedLine === $key) {
            $this->selectedLine = array_key_last($this->cart);
        }
    }

    public function removeSelected(): void
    {
        if ($this->selectedLine) {
            $this->removeLine($this->selectedLine);
        }
    }

    public function selectLine(string $key): void
    {
        $this->selectedLine = isset($this->cart[$key]) ? $key : null;
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->customerId = setting('pos.default_customer_id') ?: null;
        $this->cartDiscountType = null;
        $this->cartDiscountValue = null;
        $this->loyaltyPoints = 0;
        $this->selectedLine = null;
        $this->note = '';
        $this->approvals = [];
        $this->payments = [];
        $this->quotationId = null;
        $this->idempotencyKey = (string) Str::uuid();
    }

    /** Restore a cart saved in the browser (offline resilience). */
    public function restoreCart(array $lines): void
    {
        foreach ($lines as $line) {
            if (isset($line['product_id'])) {
                $this->addLine((int) $line['product_id'], $line['product_unit_id'] ?? null, $line['qty'] ?? 1);
            }
        }
    }

    protected function reprice(string $key): void
    {
        $line = $this->cart[$key];
        if ($line['price_override']) {
            return;
        }
        $product = Product::with('units')->find($line['product_id']);
        $unit = $line['product_unit_id'] ? $product->units->firstWhere('id', $line['product_unit_id']) : null;
        $resolved = app(PriceResolver::class)->resolve($product, $unit, $line['qty'], $this->customer);
        $this->cart[$key]['unit_price'] = (float) $resolved['price'];
        $this->cart[$key]['list_price'] = (float) ($unit ? $unit->retail_price : $product->retail_price);
        $this->cart[$key]['tier'] = $resolved['tier'];
        if (! empty($line['scale_barcode'])) {
            // Display only; SaleService recomputes this from the barcode.
            $scale = app(ProductService::class)->findByBarcode($line['scale_barcode']);
            $this->cart[$key]['qty'] = (float) ($scale['quantity'] ?? $line['qty']);
            if (! empty($scale['line_total']) && (float) $this->cart[$key]['qty'] > 0) {
                $this->cart[$key]['unit_price'] = (float) Money::div($scale['line_total'], $this->cart[$key]['qty']);
            }
        }
    }

    protected function repriceAll(): void
    {
        foreach (array_keys($this->cart) as $key) {
            $this->reprice($key);
        }
    }

    // -------------------------------------------------------------- Customer --

    #[Computed]
    public function customer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    #[Computed]
    public function customerResults(): Collection
    {
        $term = trim($this->customerSearch);
        $phone = PhoneNumber::normalize($term);

        return Customer::query()->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->when($phone, fn ($x) => $x->orWhere('phone', $phone))->orWhere('phone', 'like', "%$term%")))
            ->orderBy('name')->limit(10)->get();
    }

    public function selectCustomer(?int $id): void
    {
        $this->customerId = $id;
        $this->loyaltyPoints = 0;
        unset($this->customer);
        $this->repriceAll();
        $this->modal = null;
        $this->customerSearch = '';
    }

    public function quickAddCustomer(CustomerLedgerService $ledger): void
    {
        abort_unless(auth()->user()->can('customers.manage'), 403);
        $this->newCustomer['phone'] = PhoneNumber::normalize($this->newCustomer['phone']) ?? $this->newCustomer['phone'];
        $data = $this->validate([
            'newCustomer.name' => ['required', 'string', 'max:120'],
            'newCustomer.phone' => ['nullable', 'regex:/^255[67]\d{8}$/', 'unique:customers,phone'],
            'newCustomer.type' => ['required', 'in:retail,wholesale'],
        ], ['newCustomer.phone.regex' => __('Enter a valid Tanzanian mobile number.')], ['newCustomer.name' => 'name', 'newCustomer.phone' => 'phone'])['newCustomer'];
        if (! auth()->user()->can('customers.credit')) {
            $data['type'] = 'retail'; // wholesale prices need customers.credit
        }
        $customer = Customer::create($data);
        $this->newCustomer = ['name' => '', 'phone' => '', 'type' => 'retail'];
        $this->selectCustomer($customer->id);
        $this->dispatch('toast', message: __('Customer :n added.', ['n' => $customer->name]));
    }

    // -------------------------------------------------------------- Discounts --

    public function openDiscount(): void
    {
        $this->discountForm = ['type' => $this->cartDiscountType ?? 'percent', 'value' => $this->cartDiscountValue];
        $this->modal = 'discount';
    }

    public function applyCartDiscount(): void
    {
        $this->validate(['discountForm.type' => ['required', 'in:percent,fixed'], 'discountForm.value' => ['nullable', 'numeric', 'min:0']]);
        $this->cartDiscountType = $this->discountForm['value'] ? $this->discountForm['type'] : null;
        $this->cartDiscountValue = $this->discountForm['value'] ?: null;
        $this->modal = null;
    }

    public function openLine(string $key): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }
        $line = $this->cart[$key];
        $this->selectedLine = $key;
        $this->lineForm = [
            'key' => $key, 'qty' => $line['qty'], 'unit_price' => $line['unit_price'],
            'discount_type' => $line['discount_type'] ?? 'percent', 'discount_value' => $line['discount_value'],
        ];
        $this->modal = 'line';
    }

    public function saveLine(): void
    {
        $key = $this->lineForm['key'];
        if (! isset($this->cart[$key])) {
            return;
        }
        $this->validate([
            'lineForm.qty' => ['required', 'numeric', 'gt:0'],
            'lineForm.unit_price' => ['required', 'numeric', 'min:0'],
            'lineForm.discount_type' => ['required', 'in:percent,fixed'],
            'lineForm.discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->cart[$key]['qty'] = (float) $this->lineForm['qty'];
        $this->reprice($key);
        if (Money::cmp($this->lineForm['unit_price'], $this->cart[$key]['unit_price']) !== 0) {
            $this->cart[$key]['unit_price'] = (float) $this->lineForm['unit_price'];
            $this->cart[$key]['price_override'] = true;
            $this->cart[$key]['tier'] = 'override';
        }
        $this->cart[$key]['discount_type'] = $this->lineForm['discount_value'] ? $this->lineForm['discount_type'] : null;
        $this->cart[$key]['discount_value'] = $this->lineForm['discount_value'] ?: null;
        $this->modal = null;
    }

    public function resetLinePrice(string $key): void
    {
        if (isset($this->cart[$key])) {
            $this->cart[$key]['price_override'] = false;
            $this->reprice($key);
        }
    }

    // ------------------------------------------------------------ Hold/resume --

    public function hold(SaleService $sales): void
    {
        if (! $this->cart) {
            return;
        }
        $sales->hold($this->cartPayload(), auth()->user(), $this->shift, $this->shift?->branch_id ?? app(BranchContext::class)->currentId(), $this->holdNote);
        $this->clearCart();
        $this->holdNote = '';
        $this->modal = null;
        $this->dispatch('toast', message: __('Sale held.'), type: 'success');
    }

    #[Computed]
    public function heldSales(): Collection
    {
        return Sale::query()->where('status', SaleStatus::Held)->where('user_id', auth()->id())->with('customer')->withCount('items')->latest()->get();
    }

    public function resume(int $saleId, SaleService $sales): void
    {
        $sale = Sale::query()->where('user_id', auth()->id())->with('items')->findOrFail($saleId);
        if ($this->cart) {
            $sales->hold($this->cartPayload(), auth()->user(), $this->shift, $sale->branch_id, __('Swapped :time', ['time' => now()->format('H:i')]));
        }
        $cart = $sales->resume($sale, auth()->user());
        $this->clearCart();
        $this->customerId = $cart['customer_id'];
        $this->cartDiscountType = $cart['cart_discount_type'];
        $this->cartDiscountValue = $cart['cart_discount_value'];
        foreach ($cart['lines'] as $line) {
            $this->addLine($line['product_id'], $line['product_unit_id'], $line['qty']);
            $key = $this->selectedLine;
            if ($line['price_override']) {
                $this->cart[$key]['unit_price'] = $line['unit_price'];
                $this->cart[$key]['price_override'] = true;
                $this->cart[$key]['tier'] = 'override';
            }
            $this->cart[$key]['discount_type'] = $line['discount_type'];
            $this->cart[$key]['discount_value'] = $line['discount_value'];
        }
        unset($this->heldSales);
        $this->modal = null;
    }

    public function deleteHeld(int $saleId): void
    {
        $sale = Sale::query()->where('user_id', auth()->id())->where('status', SaleStatus::Held)->findOrFail($saleId);
        $sale->items()->delete();
        $sale->delete();
        unset($this->heldSales);
    }

    // --------------------------------------------------------------- Payment --

    public function openPayment(): void
    {
        if (! $this->cart) {
            $this->dispatch('toast', message: __('The cart is empty.'), type: 'warning');

            return;
        }
        if (! $this->shift) {
            $this->dispatch('toast', message: __('Open a shift before selling.'), type: 'error');

            return;
        }
        $total = $this->totals['total'];
        $this->payments = [['method' => 'cash', 'amount' => (float) $total, 'reference' => '']];
        $this->modal = 'payment';
    }

    public function addPayment(string $method): void
    {
        if (! PaymentMethod::tryFrom($method)) {
            return;
        }
        $remaining = Money::sub($this->totals['total'], $this->paidAmount());
        foreach ($this->payments as $i => $p) {
            if ($p['method'] === $method) {
                return;
            }
            if (Money::isZero($p['amount'])) {
                unset($this->payments[$i]);
            }
        }
        // Switching the only full cash line to another method.
        if (count($this->payments) === 1 && $this->payments[array_key_first($this->payments)]['method'] === 'cash' && ! Money::isPositive($remaining)) {
            $this->payments = [];
            $remaining = $this->totals['total'];
        }
        $this->payments = array_values($this->payments);
        if ($method === PaymentMethod::CashUsd->value) {
            // Suggest whole dollars covering the balance; change comes back in shillings.
            $rate = Money::round(setting('currency.usd_rate', 0));
            $usd = Money::isPositive($rate) ? (string) ceil((float) Money::div(Money::max($remaining, 0), $rate, 4)) : '0';
            $this->payments[] = ['method' => $method, 'foreign_amount' => (float) $usd, 'amount' => (float) Money::round(Money::mul($usd, $rate)), 'reference' => ''];

            return;
        }
        $this->payments[] = ['method' => $method, 'amount' => (float) Money::max($remaining, 0), 'reference' => ''];
    }

    /** Keep the shilling value of a dollar payment in sync (display only; the server recomputes). */
    public function updatedPayments($value, $key): void
    {
        [$index, $field] = array_pad(explode('.', (string) $key), 2, null);
        if ($field === 'foreign_amount' && isset($this->payments[$index])) {
            $this->payments[$index]['amount'] = (float) Money::round(Money::mul(Money::round($value ?: 0), Money::round(setting('currency.usd_rate', 0))));
        }
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function quickCash($amount): void
    {
        $index = collect($this->payments)->search(fn ($p) => $p['method'] === 'cash');
        if ($index === false) {
            $this->payments[] = ['method' => 'cash', 'amount' => 0, 'reference' => ''];
            $index = array_key_last($this->payments);
        }
        if ($amount === 'exact') {
            $others = Money::sum(collect($this->payments)->except($index), 'amount');
            $this->payments[$index]['amount'] = (float) Money::max(Money::sub($this->totals['total'], $others), 0);
        } else {
            $this->payments[$index]['amount'] = (float) Money::add($this->payments[$index]['amount'] ?: 0, $amount);
        }
    }

    public function sendStkPush(int $index, PaymentService $payments): void
    {
        $payment = $this->payments[$index] ?? null;
        $method = PaymentMethod::tryFrom($payment['method'] ?? '');
        if (! $payment || ! $method || ! app(PaymentGateway::class)->supportsPush() || ! $this->shift) {
            return;
        }
        $phone = $payment['phone'] ?? null ?: $this->customer?->phone;
        try {
            $intent = $payments->initiate($this->shift->branch_id, auth()->user(), $method, (string) $phone, $payment['amount'], 'DP-'.Str::upper(Str::random(12)));
        } catch (BusinessRuleException $e) {
            $this->addError("payments.$index.phone", $e->getMessage());

            return;
        }
        $this->payments[$index]['intent_reference'] = $intent->reference;
        $this->payments[$index]['intent_status'] = $intent->status;
        $this->payments[$index]['intent_recheck'] = $intent->recheckable();
        $this->dispatch('toast', message: $intent->status === 'failed' ? ($intent->message ?: __('Payment request failed.'))
            : __('Payment request sent to :p. Ask the customer to enter their PIN.', ['p' => PhoneNumber::display($intent->phone)]), type: $intent->status === 'failed' ? 'error' : 'info');
    }

    public function checkStkStatus(int $index, PaymentService $payments): void
    {
        $ref = $this->payments[$index]['intent_reference'] ?? null;
        $intent = $ref ? PaymentIntent::query()->where('reference', $ref)->first() : null;
        if ($intent) {
            $this->syncIntent($index, $payments->refresh($intent));
        }
    }

    /**
     * Poll pushes that can still change: pending ones, and provider-reported
     * failures that FastLipa may yet confirm (called by wire:poll).
     */
    public function pollStk(PaymentService $payments): void
    {
        foreach ($this->payments as $i => $p) {
            $watching = in_array($p['intent_status'] ?? null, ['pending', 'processing'], true) || ! empty($p['intent_recheck']);
            if (empty($p['intent_reference']) || ! $watching) {
                continue;
            }
            $intent = PaymentIntent::query()->where('reference', $p['intent_reference'])->first();
            if ($intent && ! $intent->isTerminal() && $intent->updated_at->lt(now()->subSeconds(8))) {
                $intent = $payments->refresh($intent);
            }
            if ($intent) {
                $this->syncIntent($i, $intent);
            }
        }
    }

    protected function syncIntent(int $index, PaymentIntent $intent): void
    {
        $was = $this->payments[$index]['intent_status'] ?? null;
        $this->payments[$index]['intent_status'] = $intent->status;
        $this->payments[$index]['intent_recheck'] = $intent->recheckable();
        if ($intent->status === PaymentIntent::COMPLETED && $was !== PaymentIntent::COMPLETED) {
            $this->payments[$index]['amount'] = (float) $intent->amount;
            $this->dispatch('toast', message: __(':m payment confirmed.', ['m' => PaymentMethod::from($intent->method)->label()]), type: 'success');
        }
    }

    public function checkoutLayaway(SaleService $sales): void
    {
        abort_unless(auth()->user()->can('layaway.manage'), 403);
        $this->checkout($sales, 'layaway');
    }

    public function checkout(SaleService $sales, string $status = 'completed'): void
    {
        if (session('pos_locked')) {
            $this->dispatch('toast', message: __('Terminal locked. Enter your PIN to continue.'), type: 'error');

            return;
        }
        if (! $this->shift) {
            $this->dispatch('toast', message: __('Open a shift before selling.'), type: 'error');

            return;
        }
        try {
            $sale = $sales->checkout($this->cartPayload(), $this->payments, auth()->user(), $this->shift, $this->idempotencyKey, $this->approvals, $status);
            if ($this->quotationId && ($quote = Sale::find($this->quotationId))) {
                app(QuotationService::class)->markConverted($quote, $sale);
            }
        } catch (ApprovalRequiredException $e) {
            $this->requestApproval($e->action, $e->permission, $e->getMessage(), $status === 'layaway' ? 'checkoutLayaway' : 'checkout');

            return;
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            $this->dispatch('scan-fail');

            return;
        }

        $this->completed = [
            'id' => $sale->id,
            'number' => $sale->number,
            'total' => (string) $sale->total,
            'change' => (string) $sale->change_due,
            'balance' => (string) $sale->balance_due,
            'layaway' => $status === 'layaway',
            'customer' => $sale->customer?->name,
            'phone' => $sale->customer?->phone,
            'receipt' => route('receipts.show', $sale),
            'whatsapp' => app(ShareService::class)->saleLink($sale),
            'escpos' => setting('receipt.print_mode') === 'escpos' ? route('receipts.escpos', $sale) : null,
        ];
        $this->clearCart();
        $this->modal = null;
        $this->dispatch('sale-completed', receipt: $this->completed['receipt'], escpos: $this->completed['escpos'], autoPrint: (bool) setting('receipt.auto_print'));
    }

    public function newSale(): void
    {
        $this->completed = null;
        $this->dispatch('focus-search');
    }

    public function smsReceipt(): void
    {
        if (! $this->completed || ! $this->completed['phone']) {
            $this->dispatch('toast', message: __('The customer has no phone number.'), type: 'warning');

            return;
        }
        $sale = Sale::find($this->completed['id']);
        app(ReceiptService::class)->sendSms($sale);
        $this->dispatch('toast', message: __('Receipt sent by SMS.'));
    }

    // ---------------------------------------------------------------- Helpers --

    protected function cartPayload(): array
    {
        return [
            'lines' => array_values(array_map(fn ($l) => [
                'product_id' => $l['product_id'],
                'product_unit_id' => $l['product_unit_id'],
                'qty' => $l['qty'],
                'unit' => $l['unit'],
                'factor' => $l['factor'],
                'unit_price' => $l['unit_price'],
                'price_override' => $l['price_override'],
                'scale_barcode' => $l['scale_barcode'] ?? null,
                'discount_type' => $l['discount_type'],
                'discount_value' => $l['discount_value'],
            ], $this->cart)),
            'customer_id' => $this->customerId,
            'cart_discount_type' => $this->cartDiscountType,
            'cart_discount_value' => $this->cartDiscountValue,
            'loyalty_points' => $this->loyaltyPoints,
            'note' => $this->note ?: null,
        ];
    }

    protected function paidAmount(): string
    {
        return Money::sum($this->payments, 'amount');
    }

    #[Computed]
    public function totals(): array
    {
        $calculator = CartCalculator::fromSettings();
        $lines = array_map(fn ($l) => ['qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'discount_type' => $l['discount_type'], 'discount_value' => $l['discount_value'], 'tax_rate' => $l['tax_rate']], $this->cart);
        $totals = $calculator->calculate($lines, $this->cartDiscountType, $this->cartDiscountValue);
        if ($this->loyaltyPoints > 0 && $this->customer) {
            $value = app(LoyaltyService::class)->valueOf(min($this->loyaltyPoints, $this->customer->loyalty_points));
            $totals = $calculator->calculate($lines, 'fixed', Money::add($totals['cart_discount'], $value));
            $totals['loyalty_value'] = $value;
        }

        return $totals;
    }

    public function render()
    {
        $totals = $this->totals;
        $paid = $this->paidAmount();
        $cashPaid = Money::sum(collect($this->payments)->whereIn('method', ['cash', 'cash_usd']), 'amount');
        $nonCash = Money::sub($paid, $cashPaid);
        $change = Money::max(Money::sub($paid, $totals['total']), 0);
        $change = Money::min($change, $cashPaid);

        return view('livewire.pos.terminal', [
            'totals' => $totals,
            'paid' => $paid,
            'remaining' => Money::max(Money::sub($totals['total'], $paid), 0),
            'change' => $change,
            'nonCashExceeds' => Money::gt($nonCash, $totals['total']),
            'methods' => PaymentMethod::enabled(),
            'registers' => Register::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'branch' => app(BranchContext::class)->current(),
            'supportsPush' => app(PaymentGateway::class)->supportsPush(),
            'loyaltyEnabled' => app(LoyaltyService::class)->enabled(),
        ]);
    }
}
