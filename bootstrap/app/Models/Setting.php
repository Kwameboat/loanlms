<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'label', 'type', 'is_public'];
    protected $casts    = ['is_public' => 'boolean'];

    // ─── Static helpers ───────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
        Cache::forget("setting_{$key}");
    }

    public static function getGroup(string $group): array
    {
        return static::where('group', $group)->pluck('value', 'key')->toArray();
    }

    public static function allPublic(): array
    {
        return static::where('is_public', true)->pluck('value', 'key')->toArray();
    }

    public function clearCache(): void
    {
        Cache::forget("setting_{$this->key}");
    }

    protected static function booted(): void
    {
        static::saved(fn ($m) => Cache::forget("setting_{$m->key}"));
        static::deleted(fn ($m) => Cache::forget("setting_{$m->key}"));
    }
}
