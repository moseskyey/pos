<?php

namespace App\Gateways\Fiscal;

use App\Contracts\FiscalDevice;
use App\Models\Sale;
use App\Support\Integrations\FiscalResult;

/** No fiscal device connected. Extension point for TRA VFD/EFD. */
class NullFiscalDevice implements FiscalDevice
{
    public function submit(Sale $sale): FiscalResult
    {
        return new FiscalResult(false);
    }
}
