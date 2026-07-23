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
        'pin_attempts',
        'pin_locked_until',

        // Deliberately NOT fillable (privilege / money): a mass-assigned
        // create()/update() from a request must never set these. Kept in step
        // with the ban/deletion columns above.
        //   - balance                        → wallet money; written only by the
        //                                        Sync*Balance commands (direct
        //                                        property assignment).
        //   - guarantee_enabled / rating_enabled / commercial_operations_enabled
        //                                     → trust & fee-consent flags; written
        //                                        only via forceFill() by the
        //                                        guarantee services + ServiceFeeConsentEnforcer.
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

        'guarantee_enabled' => 'boolean',
        'rating_enabled' => 'boolean',
        'commercial_operations_enabled' => 'boolean',

        'deleted_at'        => 'datetime',

        // BIM-15.1. Deliberately absent from $fillable: these decide whether an
        // account still exists and whether its balance may be seized, so they
        // are set explicitly by AccountDeletionService and never by a request
        // payload reaching a create()/update().
        'deletion_requested_at' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'anonymized_at'         => 'datetime',
        'banned_at'             => 'datetime',
    ];

    /** A ban is permanent and blocks login, deletion and re-registration. */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /** Requested deletion and still inside the grace window (restorable). */
    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null && $this->anonymized_at === null;
    }

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

    /**
     * Job applications this account has submitted. Was missing entirely in
     * v1 (a live BadMethodCallException on PostResource::isApplied for any
     * authenticated /get/posts request) — v2 needs it to exist and be right.
     */
    public function applies()
    {
        return $this->hasMany(Apply::class, 'user_id');
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

    public function addresses()
    {
        return $this->hasMany(Address::class, 'user_id');
    }

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
        return (bool) ($this->rating_enabled ?? false)
            || (bool) optional($this->loadedFeeConsent())->rating_enabled;
    }

    public function hasStatsEnabled(): bool
    {
        return (bool) optional($this->loadedFeeConsent())->stats_enabled;
    }

    public function canBeChargedServiceFees(): bool
    {
        return $this->hasFeeAutoChargeEnabled();
    }

    public function hasGuaranteeEnabled(): bool
    {
        return (bool) ($this->guarantee_enabled ?? false);
    }

    public function hasCommercialOperationsEnabled(): bool
    {
        return (bool) ($this->commercial_operations_enabled ?? false);
    }

    public function bookingsAsClient()
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function bookingsAsBusiness()
    {
        return $this->hasMany(Booking::class, 'business_id');
    }

    public function businessServicePrices()
    {
        return $this->hasMany(BusinessServicePrice::class, 'business_id');
    }

    public function activeBusinessServicePrices()
    {
        return $this->hasMany(BusinessServicePrice::class, 'business_id')
            ->where('is_active', 1);
    }

    public function guarantees()
    {
        return $this->hasMany(UserGuarantee::class, 'user_id');
    }

    public function guaranteeTransactions()
    {
        return $this->hasMany(GuaranteeTransaction::class, 'user_id')
            ->orderByDesc('id');
    }

    public function clientGuarantee()
    {
        return $this->hasOne(UserGuarantee::class, 'user_id')
            ->where('target_type', GuaranteeLevel::TARGET_CLIENT)
            ->latestOfMany();
    }

    public function businessGuarantee()
    {
        return $this->hasOne(UserGuarantee::class, 'user_id')
            ->where('target_type', GuaranteeLevel::TARGET_BUSINESS)
            ->latestOfMany();
    }

    public function activeClientGuarantee()
    {
        return $this->hasOne(UserGuarantee::class, 'user_id')
            ->where('target_type', GuaranteeLevel::TARGET_CLIENT)
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->latestOfMany();
    }

    public function activeBusinessGuarantee()
    {
        return $this->hasOne(UserGuarantee::class, 'user_id')
            ->where('target_type', GuaranteeLevel::TARGET_BUSINESS)
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->latestOfMany();
    }

    public function activeGuaranteeForTarget(?string $targetType = null): ?UserGuarantee
    {
        $targetType = $targetType ?: ($this->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT);

        return $this->guarantees()
            ->where('target_type', $targetType)
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->latest('id')
            ->first();
    }

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

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name ?: ('User #' . $this->id));
    }

    public function businessDepositPolicy()
    {
        return $this->hasOne(BusinessDepositPolicy::class, 'business_id');
    }
}