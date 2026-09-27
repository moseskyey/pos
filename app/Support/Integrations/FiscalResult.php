<?php

namespace App\Support\Integrations;

final class FiscalResult
{
    public function __construct(
        public bool $submitted,
        public ?string $verificationCode = null,
        public ?string $qrPayload = null,
        public ?string $error = null,
    ) {}
}
