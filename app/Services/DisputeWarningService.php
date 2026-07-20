<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\DisputeWarning;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DisputeWarningService
{
    public function sendDueWarnings(?int $limit = 100): Collection
    {
        if (! Schema::hasTable('dispute_warnings')) {
            return collect();
        }

        $rows = Dispute::query()
            ->where('status', 'mutual_resolution')
            ->whereNotNull('next_warning_at')
            ->where('next_warning_at', '<=', now())
            ->whereNull('resolved_at')
            ->limit($limit ?? 100)
            ->get();

        return $rows->map(fn (Dispute $dispute) => $this->sendForDispute($dispute));
    }

    public function sendForDispute(Dispute $dispute, string $channel = 'database'): array
    {
        $booking = $this->bookingFromDispute($dispute);
        $warningNo = (int) ($dispute->warning_count ?? 0) + 1;

        $targets = array_values(array_filter([
            (int) ($dispute->opened_by_user_id ?? 0),
            (int) ($dispute->against_user_id ?? 0),
        ]));

        $created = [];

        foreach ($targets as $userId) {
            $created[] = DisputeWarning::create([
                'dispute_id' => (int) $dispute->id,
                'booking_id' => $booking ? (int) $booking->id : null,
                'deposit_id' => (int) ($dispute->deposit_id ?? 0) ?: null,
                'sent_to_user_id' => $userId,
                'warning_no' => $warningNo,
                'channel' => $channel,
                'message' => __('تنبيه: يوجد نزاع مفتوح يحتاج إلى حل بالتراضي قبل انتهاء مهلة 15 يوم.'),
                'sent_at' => now(),
            ]);
        }

        $every = max((int) ($dispute->warning_every_days ?? 3), 1);
        $dispute->warning_count = $warningNo;
        $dispute->last_warning_sent_at = now();
        $dispute->next_warning_at = now()->addDays($every);
        $dispute->save();

        return [
            'dispute_id' => (int) $dispute->id,
            'warning_no' => $warningNo,
            'created' => count($created),
        ];
    }

    protected function bookingFromDispute(Dispute $dispute): ?Booking
    {
        if ($dispute->disputeable_type === Booking::class && $dispute->disputeable_id) {
            return Booking::query()->find((int) $dispute->disputeable_id);
        }

        return null;
    }
}
