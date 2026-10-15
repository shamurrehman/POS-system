<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'store_name' => 'Dr. Roohul Amin Pharmacy',
        'store_address' => 'Opposite Telenor Tower, Main Bazar, Buner Road, Rustam',
        'store_phone' => '03125501651',
        'store_email' => '',
        'store_license_no' => '',
        'currency_symbol' => 'Rs.',
        'tax_rate' => '0',
        'near_expiry_days' => '90',
        'new_arrival_days' => '7',
        'invoice_prefix' => 'INV',
        'receipt_footer' => 'Thank you for your visit. Get well soon!',
        'receipt_width' => '80',
        'cashier_max_discount' => '10',
        'auto_print_receipt' => '1',
        'backup_keep_days' => '30',
    ];

    private const CACHE_KEY = 'app_settings';

    public static function allSettings(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allSettings()[$key] ?? $default;
    }

    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
