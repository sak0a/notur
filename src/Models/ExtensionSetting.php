<?php

declare(strict_types=1);

namespace Notur\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionSetting extends Model
{
    protected $table = 'notur_settings';

    protected $fillable = [
        'extension_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get a setting value for an extension.
     */
    public static function getValue(string $extensionId, string $key, mixed $default = null): mixed
    {
        $setting = static::where('extension_id', $extensionId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value for an extension.
     */
    public static function setValue(string $extensionId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['extension_id' => $extensionId, 'key' => $key],
            ['value' => $value],
        );
    }
}
