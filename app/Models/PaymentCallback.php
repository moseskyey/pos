<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw inbound gateway callback log (audit & replay). */
class PaymentCallback extends Model
{
    protected $fillable = ['gateway', 'reference', 'signature_valid', 'ip', 'payload', 'result'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean'];
    }
}
