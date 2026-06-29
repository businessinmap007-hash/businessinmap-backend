<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserPushToken extends Model
{
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_WEB = 'web';

    protected $table = 'user_push_tokens';

    protected $fillable = [
        'user_id',
        'platform',
        'provider',
        'device_id',
        'token',
        'app_version',
        'locale',
        'timezone',
        'is_active',
        'last_seen_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function platforms(): array
    {
        return [self::PLATFORM_ANDROID, self::PLATFORM_IOS, self::PLATFORM_WEB];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }
}
