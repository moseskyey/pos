<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\Pos\CartCalculator;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(protected SaleService $sales, protected DocumentNumberService $numbers) {}

    /** @param array{lines: array, customer_id?: ?int, cart_discount_type?: ?string, cart_discount_value?: mixed, note?: ?string} $cart */
    public function save(array $cart, User $user, int $branchId, string $validUntil, ?Sale $quotation = null, array $approvals = []): Sale
    {
        return DB::transaction(function () use ($cart, $user, $branchId, $validUntil, $quotation, $approvals) {
            $customer = ! empty($cart['customer_id']) ? Customer::find($cart['customer_id']) : null;
            ['lines' => $lines] = $this->sales->priceLines($cart['lines'], $customer, $user, $approvals);
            $calcLines = array_map(fn ($l) => Arr::only($l, ['qty', 'unit_price', 'discount_type', 'discount_value', 'tax_rate']), $lines);
            $totals = CartCalculator::fromSettings()->calculate($calcLines, $cart['cart_discount_type'] ?? null, $cart['cart_discount_value'] ?? null);

            $attributes = [
                'customer_id' => $customer?->id,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'rounding' => $totals['rounding'],
                'total' => $totals['total'],
                'balance_due' => $totals['total'],
                'cart_discount_type' => $cart['cart_discount_type'] ?? null,
                'cart_discount_value' => $cart['cart_discount_value'] ?? null,
                'valid_until' => $validUntil,
                'note' => $cart['note'] ?? null,
            ];

            if ($quotation) {
                if ($quotation->status !== SaleStatus::Quotation) {
                    throw new BusinessRuleException(__('Converted quotations cannot be edited.'));
                }
                $quotation->update($attributes);
                $quotation->items()->delete();
            } else {
                $quotation = Sale::withoutGlobalScopes()->create($attributes + [
                    'branch_id' => $branchId,
                    'user_id' => $user->id,
                    'status' => SaleStatus::Quotation,
                    'number' => $this->numbers->next('quotation', $branchId),
                ]);
            }

            foreach ($lines as $key => $line) {
                $calc = $totals['lines'][$key];
                $quotation->items()->create([
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
            }
            activity('sales')->causedBy($user)->performedOn($quotation)->log('Quotation saved');

            return $quotation->fresh('items');
        });
    }

    /** Cart payload for the POS from a quotation. */
    public function toCart(Sale $quotation): array
    {
        if ($quotation->status !== SaleStatus::Quotation) {
            throw new BusinessRuleException(__('This quotation has already been converted.'));
        }

        return [
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'cart_discount_type' => $quotation->cart_discount_type,
            'cart_discount_value' => $quotation->cart_discount_value !== null ? (float) $quotation->cart_discount_value : null,
            'lines' => $quotation->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'product_unit_id' => $i->product_unit_id, 'qty' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price, 'price_override' => $i->price_tier === 'override',
                'discount_type' => $i->discount_type, 'discount_value' => $i->discount_value !== null ? (float) $i->discount_value : null,
            ])->all(),
        ];
    }

    public function markConverted(Sale $quotation, Sale $sale): void
    {
        $quotation->update(['status' => SaleStatus::Converted, 'converted_sale_id' => $sale->id]);
        activity('sales')->performedOn($quotation)->withProperties(['sale' => $sale->number])->log('Quotation converted to sale');
    }
}
