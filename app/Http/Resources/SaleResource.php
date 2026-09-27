<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profit = $request->user()->can('reports.profit.view');

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'branch_id' => $this->branch_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'cashier' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'subtotal' => (string) $this->subtotal,
            'discount_total' => (string) $this->discount_total,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'paid_total' => (string) $this->paid_total,
            'balance_due' => (string) $this->balance_due,
            'due_date' => $this->due_date?->toDateString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'name' => $i->name, 'sku' => $i->sku, 'quantity' => (string) $i->quantity, 'unit' => $i->unit_name,
                'unit_price' => (string) $i->unit_price, 'discount' => (string) ($i->discount_amount + $i->promo_discount), 'tax_rate' => (string) $i->tax_rate,
                'line_total' => (string) $i->line_total, 'cost_price' => $profit ? (string) $i->cost_price : null,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => ['method' => $p->method->value, 'amount' => (string) $p->amount, 'reference' => $p->reference])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
