<?php

use App\Support\Settings;

if (! function_exists('money')) {
    function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        return Settings::money($amount, $withSymbol);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}
