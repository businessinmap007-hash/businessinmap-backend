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

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndAbilities, Rateable, SoftDeletes;

    public const TYPE_CLIENT   = 'client';
    public const TYPE_BUSINESS = 'business';
    public const TYPE_ADMIN    = 'admin';

    public const TYPES = [
        self::TYPE_CLIENT,
        self::TYPE_BUSINESS,
        self::TYPE_ADMIN,
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',

        'type',
        'activated_at',

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
        'category_child_id',
        'about',
        'paid_at',
        'pin_code',

        'api_token',
        'balance',
        'pin_attempts',
        'pin_locked_until',
        'deposit_policy_mode',
        'deposit_mode',
        'deposit_calculation_base',
        'deposit_type',
        'deposit_value',
        'max_deposit_percent',
        'min_deposit_amount',
        'max_deposit_amount',
        'external_verification_enabled',
        'wallet_hold_enabled',
        'business_counter_hold_enabled',
        'business_counter_hold_percent',
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
        'deposit_policy_mode' => 'string',
        'deposit_mode' => 'string',
        'deposit_calculation_base' => 'string',
        'deposit_type' => 'string',
        'deposit_value' => 'decimal:2',
        'max_deposit_percent' => 'decimal:2',
        'min_deposit_amount' => 'decimal:2',
        'max_deposit_amount' => 'decimal:2',
        'external_verification_enabled' => 'boolean',
        'wallet_hold_enabled' => 'boolean',
        'business_counter_hold_enabled' => 'boolean',
        'business_counter_hold_percent' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Mutators
    |--------------------------------------------------------------------------
    */

    public function setPasswordAttribute($value): void
    {
        if (! $value) {
            return;
        }

        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function setPinCodeAttribute($value): void
    {
        if (! $value) {
            return;
        }

        $this->attributes['pin_code'] =
            (str_starts_with((string) $value, '$2y$') || str_starts_with((string) $value, '$argon'))
                ? $value
                : Hash::make($value);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, ?string $q): Builder
    {
        $q = trim((string) $q);

        if ($q === '') {
            return $query;
        }

        return $query->where(function (Builder $qq) use ($q) {
            $qq->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%");
        });
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        $type = $type ? trim((string) $type) : null;

        if (! $type || ! in_array($type, self::TYPES, true)) {
            return $query;
        }

        return $query->where('type', $type);
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CLIENT);
    }

    public function scopeBusinesses(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BUSINESS);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ADMIN);
    }

    public function scopeActivated(Builder $query, $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return ((int) $value === 1)
            ? $query->whereNotNull('activated_at')
            : $query->whereNull('activated_at');
    }

    public function scopeHasActiveSubscription(Builder $query, $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        if ((int) $value === 1) {
            return $query->whereHas('subscriptions', function ($q) {
                $q->where('is_active', 1);
            });
        }

        return $query->whereDoesntHave('subscriptions', function ($q) {
            $q->where('is_active', 1);
        });
    }

    public function scopeForCategoryChild(Builder $query, ?int $childId): Builder
    {
        if (! $childId) {
            return $query;
        }

        return $query->where('category_child_id', (int) $childId);
    }

    public function scopeWithActivePlatformService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->whereHas('activePlatformServices', function ($q) use ($serviceId) {
            $q->where('platform_services.id', (int) $serviceId);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Type Helpers
    |--------------------------------------------------------------------------
    */

    public function userType(): string
    {
        return $this->type ?: self::TYPE_CLIENT;
    }

    public function isBusiness(): bool
    {
        return $this->type === self::TYPE_BUSINESS;
    }

    public function isClient(): bool
    {
        return $this->type === self::TYPE_CLIENT;
    }

    public function isAdmin(): bool
    {
        if ($this->type === self::TYPE_ADMIN) {
            return true;
        }

        if (method_exists($this, 'isAn')) {
            try {
                if ($this->isAn('owner')) {
                    return true;
                }
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

    public function isActivated(): bool
    {
        return ! is_null($this->activated_at);
    }

    /*
    |--------------------------------------------------------------------------
    | PIN Helpers
    |--------------------------------------------------------------------------
    */

    public function hasPin(): bool
    {
        return ! empty($this->pin_code);
    }

    public function checkPin(string $pin): bool
    {
        return ! empty($this->pin_code) && Hash::check($pin, $this->pin_code);
    }

    public function setPin(string $pin): void
    {
        $this->pin_code = $pin;
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Main Relationships
    |--------------------------------------------------------------------------
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

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'user_id')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Category / Options / Platform Services
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categoryChild()
    {
        return $this->belongsTo(CategoryChild::class, 'category_child_id');
    }

    public function options()
    {
        return $this->belongsToMany(Option::class, 'option_user', 'user_id', 'option_id');
    }

    public function platformServices()
    {
        return $this->belongsToMany(
            PlatformService::class,
            'user_platform_service',
            'user_id',
            'platform_service_id'
        )
            ->withPivot(['is_active'])
            ->withTimestamps();
    }

    public function activePlatformServices()
    {
        return $this->belongsToMany(
            PlatformService::class,
            'user_platform_service',
            'user_id',
            'platform_service_id'
        )
            ->wherePivot('is_active', 1)
            ->withPivot(['is_active'])
            ->withTimestamps();
    }

    public function hasActivePlatformService(int|string $serviceIdOrKey): bool
    {
        $query = $this->activePlatformServices();

        if (is_numeric($serviceIdOrKey)) {
            return $query->where('platform_services.id', (int) $serviceIdOrKey)->exists();
        }

        return $query->where('platform_services.key', (string) $serviceIdOrKey)->exists();
    }

    public function businessCategoryChildId(): int
    {
        return (int) ($this->category_child_id ?? 0);
    }

    public function businessCategoryId(): int
    {
        return (int) ($this->category_id ?? 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Service Fee Consent
    |--------------------------------------------------------------------------
    */

    public function serviceFeeConsent()
    {
        return $this->hasOne(UserServiceFeeConsent::class, 'user_id');
    }

    public function feeConsent()
    {
        return $this->serviceFeeConsent();
    }

    protected function loadedFeeConsent(): ?UserServiceFeeConsent
    {
        if (! $this->relationLoaded('serviceFeeConsent')) {
            $this->load('serviceFeeConsent');
        }

        return $this->serviceFeeConsent;
    }

    public function hasFeeAutoChargeEnabled(): bool
    {
        return (bool) optional($this->loadedFeeConsent())->fee_auto_charge_enabled;
    }

    public function hasRatingEnabled(): bool
    {
        return (bool) optional($this->loadedFeeConsent())->rating_enabled;
    }

    public function hasStatsEnabled(): bool
    {
        return (bool) optional($this->loadedFeeConsent())->stats_enabled;
    }

    public function canBeChargedServiceFees(): bool
    {
        return $this->hasFeeAutoChargeEnabled();
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Relationships
    |--------------------------------------------------------------------------
    */

    public function bookingsAsClient()
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function bookingsAsBusiness()
    {
        return $this->hasMany(Booking::class, 'business_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Business Pricing
    |--------------------------------------------------------------------------
    */

    public function businessServicePrices()
    {
        return $this->hasMany(BusinessServicePrice::class, 'business_id');
    }

    public function activeBusinessServicePrices()
    {
        return $this->hasMany(BusinessServicePrice::class, 'business_id')
            ->where('is_active', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Hold Helpers
    |--------------------------------------------------------------------------
    */

    public function bookingHoldEnabled(): bool
    {
        return (bool) $this->booking_hold_enabled;
    }

    public function bookingHoldAmount(): float
    {
        return round((float) ($this->booking_hold_amount ?? 0), 2);
    }

    public function requiresBookingHold(): bool
    {
        return $this->isBusiness()
            && $this->bookingHoldEnabled()
            && $this->bookingHoldAmount() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Custom User Codes
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name ?: ('User #' . $this->id));
    }

    public function businessDepositPolicy()
    {
        return $this->hasOne(BusinessDepositPolicy::class, 'business_id');
    }
}