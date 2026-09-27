<?php

namespace App\Tenancy;

use App\Models\Platform\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates, migrates and drops a business's own database.
 */
class TenantDatabase
{
    public function __construct(protected TenantManager $tenancy) {}

    /** The database name (MySQL) or file name (SQLite) for a new business. */
    public function nameFor(Tenant $tenant): string
    {
        return $this->driver() === 'sqlite'
            ? 'tenant_'.$tenant->getKey().'.sqlite'
            : config('tenancy.database_prefix').$tenant->getKey();
    }

    public function create(Tenant $tenant): void
    {
        $database = (string) $tenant->database;
        if ($this->driver() === 'sqlite') {
            $path = TenantManager::databasePath('sqlite', $database);
            if ($path === ':memory:') {
                return;
            }
            File::ensureDirectoryExists(dirname($path));
            if (! File::exists($path)) {
                File::put($path, '');
            }

            return;
        }

        $this->server()->statement(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s',
            $this->quote($database),
            config('database.connections.tenant.charset', 'utf8mb4'),
            config('database.connections.tenant.collation', 'utf8mb4_unicode_ci'),
        ));
    }

    public function drop(Tenant $tenant): void
    {
        $database = (string) $tenant->database;
        if ($database === '' || $database === config('database.connections.central.database')) {
            return; // never drop the central database (adopted installs share it)
        }

        DB::purge($tenant->connectionName());
        if ($this->driver() === 'sqlite') {
            $path = TenantManager::databasePath('sqlite', $database);
            if ($path !== ':memory:') {
                File::delete($path);
            }

            return;
        }

        $this->server()->statement('DROP DATABASE IF EXISTS '.$this->quote($database));
    }

    public function migrate(Tenant $tenant, bool $fresh = false): int
    {
        $connection = $this->tenancy->configureConnection($tenant);

        return Artisan::call($fresh ? 'migrate:fresh' : 'migrate', [
            '--database' => $connection,
            '--path' => config('tenancy.migrations_path'),
            '--force' => true,
        ]);
    }

    public function exists(Tenant $tenant): bool
    {
        if ($this->driver() === 'sqlite') {
            $path = TenantManager::databasePath('sqlite', (string) $tenant->database);

            return $path === ':memory:' || File::exists($path);
        }

        return $this->server()->selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$tenant->database]) !== null;
    }

    protected function driver(): string
    {
        return config('database.connections.tenant.driver');
    }

    /** A connection to the server without a database, so DDL never touches the central connection. */
    protected function server(): Connection
    {
        config(['database.connections.tenancy_server' => array_merge(config('database.connections.tenant'), ['database' => null])]);

        return DB::connection('tenancy_server');
    }

    protected function quote(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Invalid database name [{$name}].");
        }
        if (strlen($name) > 64) {
            throw new RuntimeException("Database name [{$name}] is too long.");
        }

        return '`'.$name.'`';
    }
}
