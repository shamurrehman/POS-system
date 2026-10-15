<?php

namespace App\Support;

use App\Models\Setting;

class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    public static function int(string $key): int
    {
        return (int) Setting::get($key, 0);
    }

    public static function float(string $key): float
    {
        return (float) Setting::get($key, 0);
    }

    public static function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $withSymbol ? trim(Setting::get('currency_symbol', '').' '.$formatted) : $formatted;
    }
}
