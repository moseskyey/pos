<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public string $productName, public string $available, public string $requested)
    {
        parent::__construct(__('Not enough stock for :product. Available: :available, requested: :requested.', [
            'product' => $productName, 'available' => qty($available), 'requested' => qty($requested),
        ]));
    }
}
