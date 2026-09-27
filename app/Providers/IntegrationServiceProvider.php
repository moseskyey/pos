<?php

namespace App\Providers;

use App\Contracts\FiscalDevice;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Gateways\Fiscal\NullFiscalDevice;
use App\Gateways\Payment\FastLipaGateway;
use App\Gateways\Payment\ManualGateway;
use App\Gateways\Sms\BeemSmsGateway;
use App\Gateways\Sms\LogSmsGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the pluggable integration drivers selected in Settings.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    public const PAYMENT_DRIVERS = [
        'manual' => ManualGateway::class,
        'fastlipa' => FastLipaGateway::class,
    ];

    public const SMS_DRIVERS = [
        'log' => LogSmsGateway::class,
        'beem' => BeemSmsGateway::class,
    ];

    public const FISCAL_DRIVERS = [
        'null' => NullFiscalDevice::class,
    ];

    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make($this->driver(self::PAYMENT_DRIVERS, setting('payments.gateway', 'manual'), ManualGateway::class)));
        $this->app->bind(SmsGateway::class, fn ($app) => $app->make($this->driver(self::SMS_DRIVERS, setting('sms.driver', 'log'), LogSmsGateway::class)));
        $this->app->bind(FiscalDevice::class, fn ($app) => $app->make($this->driver(self::FISCAL_DRIVERS, setting('fiscal.driver', 'null'), NullFiscalDevice::class)));
    }

    protected function driver(array $map, ?string $name, string $fallback): string
    {
        $class = $map[$name] ?? $fallback;

        return class_exists($class) ? $class : $fallback;
    }
}
