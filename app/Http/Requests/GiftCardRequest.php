<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\GiftCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GiftCard::class);
    }

    public function rules(): array
    {
        $paid = $this->input('kind') !== 'voucher';

        return [
            'kind' => ['required', Rule::in(['gift_card', 'voucher'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'expires_on' => ['nullable', 'date', 'after:today'],
            'payment_method' => [Rule::requiredIf($paid), 'nullable', Rule::enum(PaymentMethod::class), Rule::notIn(['credit', 'store_credit', 'gift_card'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
