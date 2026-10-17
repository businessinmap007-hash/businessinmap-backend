<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\ClinicAppointment;
use App\Models\Dispute;
use App\Models\Order;

/**
 * «عميل سابق»: what a customer and a business have been through on the app — bookings, orders and clinic visits
 * together. Told to BOTH parties (the business sees it beside the customer's name, the customer sees it for the business
 * he is dealing with) and to nobody else.
 */
final class ClientHistory
{
    /**
     * What a customer and a business have been through together — told to BOTH of them (the customer sees how often he
     * dealt with this business, how many succeeded and whether there was a dispute; the business sees the same of him).
     * Bookings, orders and clinic visits together; a cart is not a dealing; one booking can be left out (the one in hand).
     *
     * @return array{total:int,completed:int,not_completed:int,ongoing:int,disputes:int,open_disputes:int}
     */
    public static function relationship(int $businessId, int $clientId, ?int $exceptBookingId = null): array
    {
        $summary = ['total' => 0, 'completed' => 0, 'not_completed' => 0, 'ongoing' => 0, 'disputes' => 0, 'open_disputes' => 0];

        if ($clientId <= 0 || $clientId === $businessId) {
            return $summary;
        }

        $tally = function (array $perStatus, array $completed, array $notCompleted, array $ignore = []) use (&$summary) {
            foreach ($perStatus as $status => $n) {
                if (in_array($status, $ignore, true)) {
                    continue;
                }

                $key = in_array($status, $completed, true) ? 'completed' : (in_array($status, $notCompleted, true) ? 'not_completed' : 'ongoing');
                $summary[$key] += (int) $n;
                $summary['total'] += (int) $n;
            }
        };

        $tally(
            Booking::query()->where('business_id', $businessId)->where('user_id', $clientId)
                ->when($exceptBookingId, fn ($q) => $q->where('id', '!=', $exceptBookingId))
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            [Booking::STATUS_COMPLETED], [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED]
        );

        $tally(
            Order::query()->where('business_id', $businessId)->where('user_id', $clientId)
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            ['completed'], ['cancelled', 'rejected'], ['cart']
        );

        $tally(
            ClinicAppointment::query()->where('clinic_id', $businessId)->where('patient_id', $clientId)
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            [ClinicAppointment::STATUS_COMPLETED], [ClinicAppointment::STATUS_CANCELLED, ClinicAppointment::STATUS_NO_SHOW]
        );

        // a dispute opened by either of them against the other (one withdrawn is not a problem)
        $disputes = Dispute::query()
            ->where('status', '!=', Dispute::STATUS_CANCELLED)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('opened_by_user_id', $clientId)->where('against_user_id', $businessId))
                ->orWhere(fn ($w) => $w->where('opened_by_user_id', $businessId)->where('against_user_id', $clientId)))
            ->pluck('status');

        $summary['disputes'] = $disputes->count();
        $summary['open_disputes'] = $disputes->filter(fn ($s) => in_array($s, [Dispute::STATUS_OPEN, Dispute::STATUS_MUTUAL_RESOLUTION, Dispute::STATUS_UNDER_REVIEW], true))->count();

        return $summary;
    }

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
