<?php

namespace App\Tenancy;

use App\Models\Platform\Tenant;
use App\Services\SettingsService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Permission\PermissionRegistrar;

/**
 * Points the application at one business's database, files, cache keys and
 * settings. Everything that is not central (sessions, queue, billing) follows
 * the default database connection, which this class switches.
 */
class TenantManager
{
    protected ?Tenant $current = null;

    /** Config values as they were before any business was initialised. */
    protected ?array $base = null;

    protected const TENANT_CONFIG = [
        'cache.prefix',
        'permission.cache.key',
        'filesystems.disks.local.root',
        'filesystems.disks.public.root',
        'filesystems.disks.public.url',
        'backup.backup.name',
        'backup.backup.source.databases',
        'backup.backup.source.files.include',
        'backup.backup.source.files.exclude',
        'backup.monitor_backups',
        'app.timezone',
    ];

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function initialized(): bool
    {
        return $this->current !== null;
    }

    public function initialize(Tenant $tenant): void
    {
        if ($this->current?->is($tenant)) {
            $this->current = $tenant;

            return;
        }

        $this->base ??= collect(self::TENANT_CONFIG)->mapWithKeys(fn ($key) => [$key => config($key)])->all();

        $name = $this->configureConnection($tenant);
        DB::setDefaultConnection($name);
        $this->current = $tenant;

        $this->applyConfig($tenant, $name);
        $this->resetServices();
        URL::defaults(['tenant' => $tenant->getKey()]);
        $this->applyTimezone();
    }

    /** Back to the central database only (platform admin, console). */
    public function end(): void
    {
        if (! $this->current) {
            return;
        }

        DB::setDefaultConnection('central');
        $this->current = null;
        config($this->base ?? []);
        $this->resetServices();
        URL::defaults(['tenant' => null]);
        date_default_timezone_set(config('app.timezone'));
    }

    /**
     * Run a callback inside a business, then return to whatever was active.
     *
     * @template T
     *
     * @param  callable(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->current;
        $this->initialize($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->initialize($previous) : $this->end();
        }
    }

    /** Register the business's connection (without connecting) and return its name. */
    public function configureConnection(Tenant $tenant): string
    {
        $name = $tenant->connectionName();
        $config = static::connectionConfig($tenant);

        if (config("database.connections.{$name}") !== $config) {
            config(["database.connections.{$name}" => $config]);
            DB::purge($name);
        }

        return $name;
    }

    public static function connectionConfig(Tenant $tenant): array
    {
        $template = config('database.connections.tenant');

        return array_merge($template, ['database' => static::databasePath($template['driver'], (string) $tenant->database)]);
    }

    /** SQLite files are stored by name and resolved against the tenants folder. */
    public static function databasePath(string $driver, string $database): string
    {
        if ($driver !== 'sqlite' || $database === ':memory:' || str_starts_with($database, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $database)) {
            return $database;
        }

        return rtrim(config('tenancy.sqlite_path'), '/').'/'.$database;
    }

    protected function applyConfig(Tenant $tenant, string $connection): void
    {
        $base = $this->base;
        $id = $tenant->getKey();
        $folder = $tenant->storage_folder;
        $local = $folder ? $base['filesystems.disks.local.root'].'/'.$folder : $base['filesystems.disks.local.root'];
        $public = $folder ? $base['filesystems.disks.public.root'].'/'.$folder : $base['filesystems.disks.public.root'];
        $backupName = $folder ? 'dukapos-'.$id : $base['backup.backup.name'];

        $monitors = $base['backup.monitor_backups'] ?? [];
        foreach ($monitors as $i => $monitor) {
            $monitors[$i]['name'] = $backupName;
        }

        config([
            'cache.prefix' => $base['cache.prefix'].'t'.$id.'_',
            'permission.cache.key' => $base['permission.cache.key'].'.t'.$id,
            'filesystems.disks.local.root' => $local,
            'filesystems.disks.public.root' => $public,
            'filesystems.disks.public.url' => $folder ? $base['filesystems.disks.public.url'].'/'.$folder : $base['filesystems.disks.public.url'],
            'backup.backup.name' => $backupName,
            'backup.backup.source.databases' => [$connection],
            // A business's backup holds its own files only, never other businesses' or the app's.
            'backup.backup.source.files.include' => [$local, $public],
            'backup.backup.source.files.exclude' => [$local.'/'.$backupName],
            'backup.monitor_backups' => $monitors,
        ]);
    }

    protected function resetServices(): void
    {
        app('cache')->forgetDriver(array_keys(config('cache.stores', [])));
        app()->forgetInstance('cache.store');
        app('filesystem')->forgetDisk(['local', 'public']);
        app(SettingsService::class)->reset();
        app()->forgetInstance(BranchContext::class);
        if (app()->resolved(BackupConfig::class)) {
            app()->forgetInstance(BackupConfig::class);
        }

        $permissions = app(PermissionRegistrar::class);
        $permissions->initializeCache();
        $permissions->clearPermissionsCollection();
    }

    protected function applyTimezone(): void
    {
        $timezone = setting('locale.timezone', $this->base['app.timezone']);
        if (! $timezone || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = $this->base['app.timezone'];
        }
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }
}
