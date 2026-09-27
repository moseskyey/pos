<?php

namespace Tests;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SettingsService::class)->flush();
    }
}
