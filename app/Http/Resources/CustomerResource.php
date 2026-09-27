<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'tin' => $this->tin,
            'address' => $this->address,
            'type' => $this->type,
            'credit_limit' => (string) $this->credit_limit,
            'balance' => (string) $this->balance,
            'store_credit' => (string) $this->store_credit,
            'loyalty_points' => $this->loyalty_points,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
