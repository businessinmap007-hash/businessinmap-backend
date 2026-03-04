<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletHold extends Model
{
    protected $table = 'wallet_holds';

    protected $fillable = [
        'wallet_id',
        'user_id',
        'amount',
        'status',
        'context',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta'   => 'array',
    ];

    public const STATUS_HELD     = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_VOID     = 'void';
    public const STATUS_DISPUTED = 'disputed';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function reference()
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}