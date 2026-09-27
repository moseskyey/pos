<?php

namespace App\Console\Commands\Concerns;

/**
 * For commands that work on one business's data: they must run through
 * "php artisan tenants:run <command>" so a business database is selected.
 */
trait RequiresTenant
{
    protected function missingTenant(): bool
    {
        if (tenant()) {
            return false;
        }

        $this->error('No business selected. Run it with: php artisan tenants:run "'.$this->getName().'" [--tenant=<id>]');

        return true;
    }
}
