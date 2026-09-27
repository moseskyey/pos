<?php

namespace App\Support\Integrations;

final class PaymentRequest
{
    public function __construct(
        public string $reference,
        public string $amount,
        public string $phone,
        public string $method,
        public string $description = '',
        public array $meta = [],
    ) {}
}
