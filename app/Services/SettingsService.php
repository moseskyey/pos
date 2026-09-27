<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Key/value settings with config defaults, cached forever and flushed on save.
 */
class SettingsService
{
    public const CACHE_KEY = 'dukapos.settings';

    protected ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        // Settings belong to a business; outside one (platform admin, console) only defaults apply.
        if (! tenant()) {
            return config('dukapos.settings', []);
        }

        $stored = [];
        try {
            $stored = Cache::rememberForever($this->cacheKey(), function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            // Database not ready (fresh install) – fall back to defaults.
        }

        $values = config('dukapos.settings', []);
        foreach ($stored as $key => $raw) {
            $values[$key] = $this->decode($key, $raw);
        }

        return $this->loaded = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string|array $key, mixed $value = null): void
    {
        $pairs = is_array($key) ? $key : [$key => $value];

        foreach ($pairs as $k => $v) {
            Setting::query()->updateOrCreate(['key' => $k], ['value' => $this->encode($k, $v)]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget($this->cacheKey());
        $this->loaded = null;
    }

    /** Forget the in-memory copy only (after switching business). */
    public function reset(): void
    {
        $this->loaded = null;
    }

    /** Per business, so cache stores without a key prefix (file) never mix businesses. */
    protected function cacheKey(): string
    {
        return static::CACHE_KEY.'.t'.tenant()?->getKey();
    }

    public function isEncrypted(string $key): bool
    {
        return in_array($key, config('dukapos.encrypted_settings', []), true);
    }

    protected function encode(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value);

        return $this->isEncrypted($key) ? Crypt::encryptString($json) : $json;
    }

    protected function decode(string $key, ?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        try {
            if ($this->isEncrypted($key)) {
                $raw = Crypt::decryptString($raw);
            }

            return json_decode($raw, true);
        } catch (Throwable) {
            return null;
        }
    }
}
