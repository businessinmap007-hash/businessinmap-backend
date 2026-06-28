<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferBoostPurchase extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'offer_boost_purchases';

    protected $fillable = [
        'offer_id',
        'business_id',
        'package_id',
        'wallet_transaction_id',
        'price',
        'currency',
        'starts_at',
        'ends_at',
        'boost_score',
        'is_featured',
        'status',
        'meta',
    ];

    protected $casts = [
        'offer_id' => 'integer',
        'business_id' => 'integer',
        'package_id' => 'integer',
        'wallet_transaction_id' => 'integer',
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'boost_score' => 'decimal:4',
        'is_featured' => 'boolean',
        'meta' => 'array',
    ];

    public function offer()
    {
        return $this->belongsTo(CommercialOffer::class, 'offer_id');
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function package()
    {
        return $this->belongsTo(OfferBoostPackage::class, 'package_id');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
