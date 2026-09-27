<?php

namespace Tests;

use App\Models\Platform\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;

/**
 * RefreshDatabase for both databases: the central one and a test business
 * (#1, database DB_TENANT_DATABASE). Every test runs inside that business,
 * with both databases rolled back afterwards.
 */
trait RefreshTenantDatabase
{
    use RefreshDatabase;

    public const TEST_TENANT_ID = 1;

    protected array $connectionsToTransact = ['central', 'tenant_1'];

    protected function beforeRefreshingDatabase()
    {
        // Businesses created during a test get SQLite files here; the previous test's are gone.
        $path = storage_path('framework/testing/tenants/'.(ParallelTesting::token() ?: 'main'));
        File::ensureDirectoryExists($path);
        File::cleanDirectory($path);
        config(['tenancy.sqlite_path' => $path]);
        config(['database.connections.tenant_1' => TenantManager::connectionConfig($this->testTenant())]);
    }

    protected function migrateDatabases()
    {
        $this->artisan('migrate:fresh', ['--database' => 'central']);
        $this->artisan('migrate:fresh', ['--database' => 'tenant_1', '--path' => config('tenancy.migrations_path')]);

        $this->testTenant()->save();
    }

    protected function afterRefreshingDatabase()
    {
        app(TenantManager::class)->initialize(Tenant::findOrFail(self::TEST_TENANT_ID));
    }

    protected function testTenant(): Tenant
    {
        return (new Tenant)->forceFill([
            'id' => self::TEST_TENANT_ID,
            'name' => 'Test Business',
            'slug' => 'test-business',
            'database' => config('database.connections.tenant.database') ?: ':memory:',
            'owner_email' => 'owner@test.test',
            'paid_until' => '2099-12-31 23:59:59',
            'provisioned_at' => now(),
        ]);
    }
}
