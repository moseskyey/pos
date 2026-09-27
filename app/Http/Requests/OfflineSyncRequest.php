<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfflineSyncRequest extends FormRequest
{
    /** Methods the offline till can take without a server (no credit / store credit). */
    public const METHODS = ['cash', 'cash_usd', 'mpesa', 'tigopesa', 'airtel', 'halopesa', 'card', 'bank'];

    public function authorize(): bool
    {
        return $this->user()->can('pos.access') && $this->user()->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'sales' => ['required', 'array', 'min:1', 'max:50'],
            'sales.*.client_id' => ['required', 'uuid'],
            'sales.*.sold_at' => ['required', 'date'],
            'sales.*.shift_id' => ['nullable', 'integer'],
            'sales.*.note' => ['nullable', 'string', 'max:500'],
            'sales.*.lines' => ['required', 'array', 'min:1', 'max:200'],
            'sales.*.lines.*.product_id' => ['required', 'integer'],
            'sales.*.lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'sales.*.lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'sales.*.payments' => ['required', 'array', 'min:1', 'max:6'],
            'sales.*.payments.*.method' => ['required', Rule::in(self::METHODS)],
            'sales.*.payments.*.amount' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'sales.*.payments.*.foreign_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'sales.*.payments.*.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    public static function methodOptions(): array
    {
        return collect(PaymentMethod::enabled())->filter(fn (PaymentMethod $m) => in_array($m->value, self::METHODS, true))
            ->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label(), 'reference' => $m->needsReference(), 'foreign' => $m->isForeign()])
            ->values()->all();
    }
}
