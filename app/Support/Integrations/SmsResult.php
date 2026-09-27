<?php

namespace App\Support\Integrations;

final class SmsResult
{
    public function __construct(public bool $ok, public ?string $messageId = null, public ?string $error = null) {}
}
