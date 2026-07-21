<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's contest of a levied fine, awaiting an admin decision. */
class FineAppeal extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'fine_id', 'user_id', 'statement', 'status', 'decided_by', 'decision_note', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'قيد النظر',
            self::STATUS_ACCEPTED => 'مقبول',
            self::STATUS_REJECTED => 'مرفوض',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function fine()
    {
        return $this->belongsTo(Fine::class, 'fine_id');
    }
}
