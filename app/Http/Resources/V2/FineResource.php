<?php

namespace App\Http\Resources\V2;

use App\Models\FineAppeal;
use Illuminate\Http\Resources\Json\JsonResource;

/** A fine as the fined user sees it, with whether and until when they can appeal. */
class FineResource extends JsonResource
{
    public function toArray($request): array
    {
        $pendingAppeal = $this->whenLoaded('appeals', fn () => $this->appeals
            ->firstWhere('status', FineAppeal::STATUS_PENDING));

        return [
            'id' => (int) $this->id,
            'amount' => (float) $this->amount,
            'frozen_amount' => (float) $this->frozen_amount,
            'collected_amount' => (float) $this->collected_amount,
            'shortfall' => $this->shortfall(),
            'reason' => $this->reason,
            'status' => $this->status,
            'is_appealable' => (bool) $this->is_appealable,
            'can_appeal' => $this->appealWindowOpen(),
            'appeal_deadline_at' => optional($this->appeal_deadline_at)->toIso8601String(),
            'has_pending_appeal' => $pendingAppeal !== null,
            'frozen_at' => optional($this->frozen_at)->toIso8601String(),
            'collected_at' => optional($this->collected_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
