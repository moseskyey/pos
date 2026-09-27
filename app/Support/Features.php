<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Per-business optional modules (config('dukapos.features')), switched on or
 * off in Settings → Features. Each feature is stored as a boolean setting.
 */
class Features
{
    /** @return array<string, array{label: string, icon: string, description: string, setting?: string}> */
    public static function all(): array
    {
        return config('dukapos.features', []);
    }

    public static function settingKey(string $feature): string
    {
        $definition = static::all()[$feature] ?? throw new InvalidArgumentException("Unknown feature [$feature].");

        return $definition['setting'] ?? "features.$feature";
    }

    public static function enabled(string $feature): bool
    {
        return (bool) setting(static::settingKey($feature), true);
    }

    public static function disabled(string $feature): bool
    {
        return ! static::enabled($feature);
    }

    /** @return array<string, array{label: string, icon: string, features: array<string, bool>}> */
    public static function presets(): array
    {
        return config('dukapos.business_presets', []);
    }

    /**
     * Setting values that apply a business-type preset.
     *
     * @return array<string, mixed>
     */
    public static function presetValues(string $preset): array
    {
        $definition = static::presets()[$preset] ?? throw new InvalidArgumentException("Unknown business preset [$preset].");
        $values = ['features.business_type' => $preset];
        foreach ($definition['features'] as $feature => $on) {
            $values[static::settingKey($feature)] = (bool) $on;
        }

        return $values;
    }
}
