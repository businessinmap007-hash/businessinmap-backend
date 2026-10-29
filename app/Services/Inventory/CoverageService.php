<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Level;
use App\Models\Inventory\Move;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «يكفي مبيعات اليوم؟» — the owner's question from the phone (his V Cola example). What is sold on a day is read from the
 * ledger's sale moves (net of returns): today so far, the same weekday over the last four weeks (a Thursday is not a
 * Monday), and the daily average of the last 28 days for «يكفي كم يوم».
 */
final class CoverageService
{
    private const WEEKS = 4;
    private const DAYS = 28;

    /** @return list<array<string,mixed>> worst first */
    public function forBusiness(int $businessId, ?array $itemIds = null): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $from = $todayStart->copy()->subDays(self::DAYS);

        $items = Item::query()->where('business_id', $businessId)->where('is_active', 1)
            ->when($itemIds, fn ($q) => $q->whereIn('id', $itemIds))->get();

        $onHand = Level::query()->where('business_id', $businessId)->groupBy('item_id')
            ->select('item_id', DB::raw('SUM(qty) as qty'))->pluck('qty', 'item_id');

        $daily = Move::query()
            ->where('business_id', $businessId)
            ->whereIn('type', [Move::SALE, Move::SALE_REVERSAL])
            ->where('occurred_at', '>=', $from)
            ->groupBy('item_id', DB::raw('DATE(occurred_at)'))
            ->select('item_id', DB::raw('DATE(occurred_at) as day'), DB::raw('-SUM(qty) as used'))
            ->get()
            ->groupBy('item_id');

        $sameDays = [];
        for ($w = 1; $w <= self::WEEKS; $w++) {
            $sameDays[] = $todayStart->copy()->subWeeks($w)->toDateString();
        }

        $rows = [];

        foreach ($items as $item) {
            $byDay = optional($daily->get($item->id))->pluck('used', 'day') ?? collect();
            $today = (float) ($byDay[$todayStart->toDateString()] ?? 0);
            $history = $byDay->except([$todayStart->toDateString()]);
            $weekday = array_sum(array_map(fn ($d) => (float) ($history[$d] ?? 0), $sameDays)) / self::WEEKS;
            $avg = (float) $history->sum() / self::DAYS;
            $have = round((float) ($onHand[$item->id] ?? 0), 4);
            $rest = max(0.0, $weekday - $today);
            $days = $avg > 0 ? round($have / $avg, 1) : null;

            $verdict = match (true) {
                $avg <= 0 && $weekday <= 0 => $have > 0 ? 'unknown' : 'empty',
                $have + 0.0001 < $rest => 'no',
                $days !== null && $days < 2 => 'tight',
                default => 'yes',
            };

            $rows[] = [
                'item_id' => $item->id,
                'name' => $item->name_ar,
                'base_unit' => $item->base_unit,
                'on_hand' => $have,
                'used_today' => round($today, 4),
                'weekday_average' => round($weekday, 4),
                'expected_rest_of_today' => round($rest, 4),
                'daily_average' => round($avg, 4),
                'days_left' => $days,
                'reorder_level' => $item->reorder_level,
                'below_reorder' => $item->reorder_level !== null && $have <= (float) $item->reorder_level,
                'verdict' => $verdict,
            ];
        }

        $rank = ['no' => 0, 'tight' => 1, 'empty' => 2, 'yes' => 3, 'unknown' => 4];
        usort($rows, fn ($a, $b) => [$rank[$a['verdict']], $a['days_left'] ?? INF] <=> [$rank[$b['verdict']], $b['days_left'] ?? INF]);

        return $rows;
    }

    /** @return array<string,mixed> */
    public function summary(int $businessId): array
    {
        $value = (float) DB::table('inv_levels as l')->join('inv_items as i', 'i.id', '=', 'l.item_id')
            ->where('l.business_id', $businessId)->sum(DB::raw('l.qty * i.avg_cost'));

        $below = Item::query()->where('business_id', $businessId)->where('is_active', 1)->whereNotNull('reorder_level')
            ->get()->filter(fn (Item $i) => $i->onHand() <= (float) $i->reorder_level)->count();

        $monthStart = Carbon::now()->startOfMonth();
        $waste = (float) Move::query()->where('business_id', $businessId)->where('type', Move::WASTE)
            ->where('occurred_at', '>=', $monthStart)->sum(DB::raw('-qty * unit_cost'));

        return [
            'stock_value' => round($value, 2),
            'below_reorder' => $below,
            'open_purchase_orders' => (int) DB::table('inv_purchase_orders')->where('business_id', $businessId)
                ->whereIn('status', ['pending_approval', 'approved', 'partially_received'])->count(),
            'waste_this_month' => round($waste, 2),
        ];
    }
}
