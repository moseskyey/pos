<?php

use App\Providers\AppServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    TenancyServiceProvider::class,
    AppServiceProvider::class,
    IntegrationServiceProvider::class,
];
