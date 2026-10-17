<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\ClinicAppointment;
use App\Models\Order;

/**
 * «عميل سابق»: how many dealings a customer has already COMPLETED with a business on the app — bookings, orders and
 * clinic visits together. The business sees it beside the customer's name (a badge and the count); the customer himself
 * is never told his own number this way and nobody else is.
 */
final class ClientHistory
{
    /**
     * @param  array<int,int|string>  $clientIds
     * @return array<int,int> client id → completed dealings with this business
     */
    public static function counts(int $businessId, array $clientIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $clientIds),
            fn (int $id) => $id > 0 && $id !== $businessId
        )));

        if ($ids === []) {
            return [];
        }

        $out = array_fill_keys($ids, 0);

        $add = function ($rows) use (&$out) {
            foreach ($rows as $row) {
                $out[(int) $row->cid] += (int) $row->n;
            }
        };

        $add(Booking::query()
            ->where('business_id', $businessId)->whereIn('user_id', $ids)->where('status', Booking::STATUS_COMPLETED)
            ->selectRaw('user_id as cid, count(*) as n')->groupBy('user_id')->get());

        $add(Order::query()
            ->where('business_id', $businessId)->whereIn('user_id', $ids)->where('status', 'completed')
            ->selectRaw('user_id as cid, count(*) as n')->groupBy('user_id')->get());

        $add(ClinicAppointment::query()
            ->where('clinic_id', $businessId)->whereIn('patient_id', $ids)->where('status', ClinicAppointment::STATUS_COMPLETED)
            ->selectRaw('patient_id as cid, count(*) as n')->groupBy('patient_id')->get());

        return $out;
    }
}
