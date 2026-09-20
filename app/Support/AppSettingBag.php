<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class AppSettingBag
{
    /**
     * Read an application setting from the app_settings store with a fallback.
     *
     * Settings are stored as JSON; the AppSetting "array" cast restores scalar
     * strings and arrays transparently.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = AppSetting::query()->where('key', $key)->first();

        if ($row === null || $row->value === null) {
            return $default;
        }

        return $row->value;
    }
}
