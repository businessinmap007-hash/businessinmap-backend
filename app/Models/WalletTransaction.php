<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'locked_before' => 'decimal:2',
        'locked_after' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'wallet_id','user_id','status','direction','type','amount',
        'balance_before','balance_after','locked_before','locked_after',
        'reference_type','reference_id','idempotency_key','note','meta',
    ];
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
    public function noteTemplate()
    {
        return $this->belongsTo(
            \App\Models\WalletNoteTemplate::class,
            'note_id'
        );
    }

}
