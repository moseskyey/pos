<?php

namespace App\Support\Integrations;

final class PaymentResult
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $providerReference = null,
        public ?string $amount = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public function successful(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function pending(): bool
    {
        return $this->status === self::PENDING;
    }
}
