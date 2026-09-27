<?php

namespace App\Support;

use App\Models\Platform\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Platform-wide settings (trial length, billing credentials, support contacts),
 * stored in the central database. Loaded once per request or queued job.
 */
class PlatformSettings
{
    public const DEFAULTS = [
        'name' => 'DukaPOS',
        'trial_days' => 14,
        'grace_days' => 3,
        'signups_enabled' => true,
        'default_plan_id' => null,
        'reminder_days' => '7,3,1',
        'support_phone' => null,
        'support_email' => null,
        'support_whatsapp' => null,
        'billing_instructions' => null,
        'fastlipa_enabled' => false,
        'fastlipa_api_key' => null,
        'fastlipa_base_url' => 'https://api.fastlipa.com',
        'fastlipa_webhook_secret' => null,
    ];

    public const ENCRYPTED = ['fastlipa_api_key', 'fastlipa_webhook_secret'];

    protected ?array $loaded = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        return app(static::class)->all()[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $raw = $value === null ? null : json_encode($value);
            if ($raw !== null && in_array($key, self::ENCRYPTED, true)) {
                $raw = Crypt::encryptString($raw);
            }
            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $raw]);
        }
        app(static::class)->loaded = null;
    }

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $values = self::DEFAULTS;
        try {
            foreach (PlatformSetting::query()->pluck('value', 'key') as $key => $raw) {
                $values[$key] = $this->decode($key, $raw);
            }
        } catch (Throwable) {
            // Central database not migrated yet.
        }

        return $this->loaded = $values;
    }

    protected function decode(string $key, ?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        try {
            return json_decode(in_array($key, self::ENCRYPTED, true) ? Crypt::decryptString($raw) : $raw, true);
        } catch (Throwable) {
            return null;
        }
    }
}
