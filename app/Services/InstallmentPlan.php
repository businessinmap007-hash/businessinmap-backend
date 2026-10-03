<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderInstallment;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;

/**
 * An order's payments by month. A line bought on «تقسيط» carries the months the
 * merchant set for that price; its total is split evenly over them (the last
 * month takes the rounding), and the lines of an order add up month by month.
 * The first payment falls one month after the day the schedule is made, then
 * monthly. What is not on instalments (a cash line, the service fee) is paid
 * as the order always was and is not part of the schedule.
 */
class InstallmentPlan
{
    /**
     * @return list<array{seq:int,due_on:string,amount:float}> empty when no line is on instalments
     */
    public function forOrder(Order $order, ?Carbon $from = null): array
    {
        $lines = $order->items()
            ->where('installment_months', '>', 1)
            ->where(fn ($q) => $q->whereNull('resolution')->orWhere('resolution', '!=', OrderItem::RESOLUTION_REMOVED))
            ->get(['total_price', 'installment_months', 'installment_down', 'qty']);

        if ($lines->isEmpty()) {
            return [];
        }

        $months = (int) $lines->max('installment_months');
        $amounts = array_fill(1, $months, 0.0);

        foreach ($lines as $line) {
            $m = (int) $line->installment_months;
            $total = round((float) $line->total_price, 2);
            // The down payment (per unit) is paid with the first month; what is left
            // is split evenly over all the months.
            $down = min(round((float) $line->installment_down * max((int) $line->qty, 1), 2), $total);
            $each = round(($total - $down) / $m, 2);
            $paid = 0.0;

            for ($i = 1; $i <= $m; $i++) {
                $part = $i < $m ? round($each + ($i === 1 ? $down : 0), 2) : round($total - $paid, 2);
                $amounts[$i] = round($amounts[$i] + $part, 2);
                $paid = round($paid + $part, 2);
            }
        }

        $start = ($from ?? now())->copy()->startOfDay();
        $plan = [];

        foreach ($amounts as $seq => $amount) {
            $plan[] = ['seq' => $seq, 'due_on' => $start->copy()->addMonthsNoOverflow($seq)->toDateString(), 'amount' => $amount];
        }

        return $plan;
    }

    /** (Re)writes a PLACED order's schedule from its lines; a cart has none. */
    public function rebuild(Order $order): void
    {
        if ($order->status === 'cart') {
            return;
        }

        // The schedule counts from the day it was first made, so a rewrite keeps every date.
        $first = $order->installments()->orderBy('seq')->first();
        $from = $first ? $first->due_on->copy()->subMonthNoOverflow() : now();

        // Payments already made keep their place; the rest is rewritten.
        $paid = OrderInstallment::query()->where('order_id', $order->id)->whereNotNull('paid_at')->pluck('seq')->all();
        OrderInstallment::query()->where('order_id', $order->id)->whereNull('paid_at')->delete();

        foreach ($this->forOrder($order, $from) as $row) {
            if (in_array($row['seq'], $paid, true)) {
                continue;
            }
            OrderInstallment::query()->create(['order_id' => $order->id] + $row);
        }
    }
}
