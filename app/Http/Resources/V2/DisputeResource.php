<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a party to a dispute may see about it.
 *
 * `meta` is never exposed: it carries the opening payload verbatim plus the
 * escalation trail, and `resolution_payload` carries the admin's private notes
 * and the penalty amounts. A party gets the ruling and the money, not the file.
 */
class DisputeResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewerId = (int) ($request->user()->id ?? 0);
        $isOpener = (int) $this->opened_by_user_id === $viewerId;

        $resolution = is_array($this->resolution_payload ?? null)
            ? ($this->resolution_payload['resolution_payload'] ?? [])
            : [];

        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'type' => $this->type,

            // Which side the caller is on — the app needs it to phrase
            // "you opened this" vs "this was opened against you".
            'my_role' => $isOpener ? 'opener' : 'respondent',

            'reason_code' => $this->reason_code,
            'reason_text' => $this->reason_text,

            'counterparty' => $this->whenLoaded(
                $isOpener ? 'againstUser' : 'openedBy',
                fn () => [
                    'id' => (int) ($isOpener ? $this->against_user_id : $this->opened_by_user_id),
                    'name' => $isOpener ? $this->againstUser?->name : $this->openedBy?->name,
                ]
            ),

            'booking_id' => $this->disputeable_type === \App\Models\Booking::class
                ? (int) $this->disputeable_id
                : null,
            'deposit_id' => $this->deposit_id !== null ? (int) $this->deposit_id : null,

            // Whether each side declared it is engaging with the settlement.
            // Both are visible to both: knowing the other party has not shown
            // up is exactly what should prompt you to.
            // Deliberately NOT a "mine" field: the opener is not always the
            // client (a business opens disputes too), so mapping viewer→side
            // here would need the booking loaded and would be wrong the moment
            // it was not. The app pairs these with `my_side` from the show
            // response, and the cooperate endpoint is idempotent anyway.
            'cooperation' => [
                'client_at' => optional($this->client_cooperated_at)->toIso8601String(),
                'business_at' => optional($this->business_cooperated_at)->toIso8601String(),
            ],

            // Both taps are shown to both sides: seeing that the other party
            // has already agreed is the whole prompt to confirm.
            'settlement' => [
                'client_agreed_at' => optional($this->client_settlement_agreed_at)->toIso8601String(),
                'business_agreed_at' => optional($this->business_settlement_agreed_at)->toIso8601String(),
                'complete' => $this->client_settlement_agreed_at !== null
                    && $this->business_settlement_agreed_at !== null,
            ],

            'opened_at' => optional($this->opened_at)->toIso8601String(),
            'mutual_resolution_deadline_at' => optional($this->mutual_resolution_deadline_at)->toIso8601String(),
            'warning_count' => (int) $this->warning_count,

            'resolution_type' => $this->resolution_type,
            // Only the split percentages: they are the ruling itself, and both
            // parties are entitled to know how the escrow was divided.
            'resolution' => $this->resolution_type === 'split' ? [
                'client_percent' => (float) ($resolution['client_percent'] ?? 0),
                'business_percent' => (float) ($resolution['business_percent'] ?? 0),
            ] : null,
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'closed_at' => optional($this->closed_at)->toIso8601String(),
            // A closed case says WHY: `complied` is the verdict that the ruling
            // was carried out, which is what a party points to to prove it is
            // genuinely over.
            'closed_reason' => $this->closed_reason,

            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
