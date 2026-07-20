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

            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
