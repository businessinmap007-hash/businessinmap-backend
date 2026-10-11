<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * «يجب التواجد قبل الموعد بـ ١٥ دقيقة» — the one notice a business may attach to every booking it takes (a booking, a
 * clinic appointment): how many minutes early to be there, and a sentence of its own. Shown to the customer before
 * and after booking and repeated in the reminder.
 */
final class ArrivalNotice
{
    public const MAX_MINUTES = 240;

    /**
     * The composed sentence, or null when the business said nothing.
     *
     * @param  string|null  $locale  null = the request's own
     */
    public static function message(?int $minutes, ?string $text, ?string $locale = null): ?string
    {
        $parts = [];

        if ($minutes !== null && $minutes > 0) {
            $parts[] = __('يجب التواجد قبل الموعد بـ :minutes دقيقة.', ['minutes' => $minutes], $locale);
        }

        $text = trim((string) $text);
        if ($text !== '') {
            $parts[] = $text;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * The notice of each business, in one query — `[business id => {minutes, text, message}]`; a business that said
     * nothing is absent.
     *
     * @param  list<int>  $businessIds
     * @return array<int,array{minutes:?int,text:?string,message:string}>
     */
    public static function forBusinesses(array $businessIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $businessIds))));
        if ($ids === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('business_booking_settings')->whereIn('business_id', $ids)
            ->get(['business_id', 'arrival_notice_minutes', 'arrival_notice_text']);

        foreach ($rows as $row) {
            $minutes = $row->arrival_notice_minutes !== null ? (int) $row->arrival_notice_minutes : null;
            $message = self::message($minutes, $row->arrival_notice_text);

            if ($message !== null) {
                $out[(int) $row->business_id] = [
                    'minutes' => $minutes,
                    'text' => $row->arrival_notice_text !== null && trim($row->arrival_notice_text) !== '' ? trim($row->arrival_notice_text) : null,
                    'message' => $message,
                ];
            }
        }

        return $out;
    }

    public static function forBusiness(int $businessId): ?array
    {
        return self::forBusinesses([$businessId])[$businessId] ?? null;
    }
}
