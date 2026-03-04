<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use willvincent\Rateable\Rateable;

use App\Models\Booking;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndAbilities, Rateable, SoftDeletes;

    public const TYPES = ['client', 'business', 'admin'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',

        'type',            // enum('admin','client','business')
        'activated_at',

        // booking hold settings (per business)
        'booking_hold_enabled',
        'booking_hold_amount',

        'action_code',
        'code',
        'logo',
        'cover',
        'image',
        'latitude',
        'longitude',
        'location_id',
        'category_id',
        'about',
        'paid_at',
        'pin_code',

        'api_token',
        'balance',
        'pin_attempts',
        'pin_locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pin_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'activated_at'      => 'datetime',
        'paid_at'           => 'datetime',
        'pin_locked_until'  => 'datetime',

        'latitude'          => 'float',
        'longitude'         => 'float',

        'booking_hold_enabled' => 'boolean',
        'booking_hold_amount'  => 'decimal:2',

        'deleted_at'        => 'datetime',
    ];

    /*
    |----------------------------------------------------------------------
    | Mutators
    |----------------------------------------------------------------------
    */

    public function setPasswordAttribute($value): void
    {
        if (!$value) return;

        $this->attributes['password'] =
            Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function setPinCodeAttribute($value): void
    {
        if (!$value) return;

        $this->attributes['pin_code'] =
            (str_starts_with((string)$value, '$2y$') || str_starts_with((string)$value, '$argon'))
                ? $value
                : Hash::make($value);
    }

    /*
    |----------------------------------------------------------------------
    | Scopes (Filters)
    |----------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, ?string $q): Builder
    {
        $q = trim((string)$q);
        if ($q === '') return $query;

        return $query->where(function (Builder $qq) use ($q) {
            $qq->where('name', 'like', "%{$q}%")
               ->orWhere('email', 'like', "%{$q}%")
               ->orWhere('phone', 'like', "%{$q}%");
        });
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        $type = $type ? trim((string)$type) : null;
        if (!$type || !in_array($type, self::TYPES, true)) return $query;

        return $query->where('type', $type);
    }

    // ✅ used by Booking selects / validation
    public function scopeClients($q)    { return $q->where('type', 'client'); }
    public function scopeBusinesses($q) { return $q->where('type', 'business'); }
    public function scopeAdmins($q)     { return $q->where('type', 'admin'); }

    /**
     * Active filter using activated_at
     * 1 => activated_at not null
     * 0 => activated_at null
     */
    public function scopeActivated(Builder $query, $value): Builder
    {
        if ($value === null || $value === '') return $query;

        return ((int)$value === 1)
            ? $query->whereNotNull('activated_at')
            : $query->whereNull('activated_at');
    }

    /**
     * Subscription filter:
     * 1 => has active subscription
     * 0 => has NO active subscription
     */
    public function scopeHasActiveSubscription(Builder $query, $value): Builder
    {
        if ($value === null || $value === '') return $query;

        if ((int)$value === 1) {
            return $query->whereHas('subscriptions', function ($q) {
                $q->where('is_active', 1);
            });
        }

        return $query->whereDoesntHave('subscriptions', function ($q) {
            $q->where('is_active', 1);
        });
    }

    /*
    |----------------------------------------------------------------------
    | Helpers
    |----------------------------------------------------------------------
    */

    public function userType(): string
    {
        return $this->type ?? 'client';
    }

    public function isBusiness(): bool
    {
        return $this->type === 'business';
    }

    public function isClient(): bool
    {
        return $this->type === 'client';
    }

    public function isAdmin(): bool
    {
        // type admin
        if ($this->type === 'admin') {
            return true;
        }

        // bouncer owner ability/role (اختياري)
        if (method_exists($this, 'isAn')) {
            try {
                if ($this->isAn('owner')) return true;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (method_exists($this, 'roles')) {
            try {
                return $this->roles()->where('name', 'owner')->exists();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return false;
    }

    public function hasPin(): bool
    {
        return !empty($this->pin_code);
    }

    public function checkPin(string $pin): bool
    {
        return !empty($this->pin_code) && Hash::check($pin, $this->pin_code);
    }

    public function setPin(string $pin): void
    {
        $this->pin_code = $pin; // hashed by mutator
        $this->save();
    }

    /*
    |----------------------------------------------------------------------
    | Relationships
    |----------------------------------------------------------------------
    */

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function social()
    {
        return $this->hasOne(Social::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function options()
    {
        return $this->belongsToMany(Option::class, 'option_user');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /*
    |----------------------------------------------------------------------
    | Custom User Codes
    |----------------------------------------------------------------------
    */

    public static function actionCode($code)
    {
        return static::where('action_code', $code)->exists()
            ? rand(1000, 9999)
            : $code;
    }

    public static function userCode($code)
    {
        return static::where('code', $code)->exists()
            ? rand(1000000000, 9999999999)
            : $code;
    }

    /*
    |----------------------------------------------------------------------
    | Bookings
    |----------------------------------------------------------------------
    */

    public function bookingsAsClient()
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function bookingsAsBusiness()
    {
        return $this->hasMany(Booking::class, 'business_id');
    }
    
}