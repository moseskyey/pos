<?php

namespace App\Contracts;

use App\Models\Sale;
use App\Support\Integrations\FiscalResult;

/**
 * TRA VFD / EFD integration hook. Implementations submit a completed sale and
 * return the verification code / QR payload to print on the receipt.
 */
interface FiscalDevice
{
    public function submit(Sale $sale): FiscalResult;
}
