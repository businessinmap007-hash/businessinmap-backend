<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a guest says he paid a hotel outside the app, with the proof he holds. The app records the statement and the proof; it never
 * vouches for the money. Only the hotel — the payee — says it arrived. Never deleted: it is what an arbitrator reads.
 */
class StaySettlementPayment extends Model
{
    public const RECORDED = 'recorded';
    public const RECEIVED = 'received';
    public const REFUSED = 'refused';

    protected $fillable = ['booking_id', 'payer_id', 'amount', 'method', 'reference', 'proof_image', 'acknowledged_at', 'status', 'answer_note', 'answered_at', 'answered_by'];

    protected $casts = [
        'booking_id' => 'integer',
        'payer_id' => 'integer',
        'amount' => 'decimal:2',
        'acknowledged_at' => 'datetime',
        'answered_at' => 'datetime',
        'answered_by' => 'integer',
    ];

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'id' => (int) $this->id,
            'amount' => (float) $this->amount,
            'method' => (string) $this->method,
            'reference' => $this->reference,
            'status' => (string) $this->status,
            'answer_note' => $this->answer_note,
            'recorded_at' => $this->created_at?->toIso8601String(),
            'answered_at' => $this->answered_at?->toIso8601String(),
        ];
    }
}
