<?php

namespace App\Contracts;

use App\Support\Integrations\SmsResult;

interface SmsGateway
{
    public function send(string $to, string $message): SmsResult;
}
