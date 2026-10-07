<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value app settings (business name, address, …), editable by super admins.
 */
class Setting extends Model
{
    public const DEFAULTS = [
        'business_name' => '',
        'business_address' => '',
        'business_contact' => '',
        'default_reorder_level' => '5',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('app-settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Business name shown in both apps; falls back to APP_NAME until set.
     */
    public static function businessName(): string
    {
        return filled($name = static::get('business_name')) ? $name : (string) config('app.name');
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app-settings');
    }
}
