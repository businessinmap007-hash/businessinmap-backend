<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Service;
use App\Models\User;

class Booking extends Model
{
   use SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'user_id','business_id','service_id',
        'date','time','price','status','notes',
        'starts_at','ends_at','duration_value','duration_unit',
        'all_day','timezone','quantity','party_size',
        'bookable_type','bookable_id',
        'meta',
    ];

    protected $casts = [
        'date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'bool',
        'price' => 'decimal:2',
        'meta' => 'array',
    ];

    // ✅ لعرض أسماء الطرفين بسهولة
    protected $appends = [
        'user_name',
        'business_name',
        'user_code',
        'business_code',
    ];

    /* =========================
     * Polymorphic (optional)
     * ========================= */
    public function bookable()
    {
        return $this->morphTo();
    }

    /* =========================
     * Status
     * ========================= */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_IN_PROGRESS = 'in_progress'; // لو مستخدمها في الكنترول

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_ACCEPTED  => 'Accepted',
            self::STATUS_REJECTED  => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_IN_PROGRESS => 'In Progress',
        ];
    }

    /* =========================
     * Relations
     * ========================= */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function holds()
    {
        return $this->hasMany(\App\Models\WalletHold::class, 'reference_id', 'id')
            ->where('reference_type', static::class)
            ->where('context', 'booking');
    }

    /* =========================
     * Accessors (Computed)
     * ========================= */
    protected function userName(): Attribute
    {
        return Attribute::get(function () {
            // لو withNames شغال هتلاقي user_name column جاهز
            if (array_key_exists('user_name', $this->attributes ?? [])) {
                return $this->attributes['user_name'] ?: null;
            }
            return $this->user?->name ?: ($this->user_id ? ('User #'.$this->user_id) : null);
        });
    }

    protected function businessName(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('business_name', $this->attributes ?? [])) {
                return $this->attributes['business_name'] ?: null;
            }
            return $this->business?->name ?: ($this->business_id ? ('Business #'.$this->business_id) : null);
        });
    }

    protected function userCode(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('user_code', $this->attributes ?? [])) {
                return $this->attributes['user_code'] ?: null;
            }
            return $this->user?->code ?: null;
        });
    }

    protected function businessCode(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('business_code', $this->attributes ?? [])) {
                return $this->attributes['business_code'] ?: null;
            }
            return $this->business?->code ?: null;
        });
    }

    /* =========================
     * Scopes
     * ========================= */
    public function scopeWithNames(Builder $q): Builder
    {
        // ✅ يضيف أعمدة user_name / business_name مباشرة من users
        return $q->select('bookings.*')
            ->selectSub(
                User::query()->select('name')
                    ->whereColumn('users.id', 'bookings.user_id')
                    ->limit(1),
                'user_name'
            )
            ->selectSub(
                User::query()->select('name')
                    ->whereColumn('users.id', 'bookings.business_id')
                    ->limit(1),
                'business_name'
            )
            ->selectSub(
                User::query()->select('code')
                    ->whereColumn('users.id', 'bookings.user_id')
                    ->limit(1),
                'user_code'
            )
            ->selectSub(
                User::query()->select('code')
                    ->whereColumn('users.id', 'bookings.business_id')
                    ->limit(1),
                'business_code'
            );
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') return $q;

        return $q->where(function (Builder $qq) use ($term) {

            if (ctype_digit($term)) {
                $qq->orWhere('bookings.id', (int)$term)
                   ->orWhere('bookings.user_id', (int)$term)
                   ->orWhere('bookings.business_id', (int)$term);
            }

            $qq->orWhere('bookings.notes', 'like', "%{$term}%");

            // ✅ لو بتستخدم withNames() هتقدر تبحث بالاسم مباشرة
            $qq->orWhere('user_name', 'like', "%{$term}%")
               ->orWhere('business_name', 'like', "%{$term}%");
        });
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        $status = trim($status);
        return $status === '' ? $q : $q->where('status', $status);
    }

    public function scopeOnDate(Builder $q, ?string $date): Builder
    {
        $date = trim((string)$date);
        return $date === '' ? $q : $q->whereDate('starts_at', $date);
    }
}