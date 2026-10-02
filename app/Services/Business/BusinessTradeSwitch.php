<?php

namespace App\Services\Business;

use App\Models\BookableItem;
use App\Models\BusinessCatalogListing;
use App\Models\BusinessServicePrice;
use App\Models\MenuBundle;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «بدل من عمل حسابات مختلفه كل مرة خلينى اقدر اغير التصنيف الرئيسي والفرعى وع
 * التغيير تحذف كل المنيو … لو غيرت حساب فهيم - خضروات وفاكهه لمصنع آثاث تحذف
 * منتجاتى وقائمتى وتحولنى لما هو محدد لمصنع الاثاث» — المالك، 2026-10-02.
 *
 * A business changes its trade (root + child) on the SAME account. What belongs
 * to the old trade goes — its menu, sections, bundles, retail listings, prices,
 * bookable units, tables, and the vocabulary ticks — so the account is exactly
 * what the new trade defines: its services, vocabulary and item types come from
 * the (root, child) itself, never from the account.
 *
 * What is the ACCOUNT's own stays: its history (orders, bookings, ledger), its
 * staff, working hours, payment accounts, photos, posts, followers.
 *
 * Money in flight blocks the switch — an open order or booking, a frozen
 * deposit — because deleting the menu under a customer who is mid-purchase is
 * not a decision this endpoint may take for the merchant.
 */
class BusinessTradeSwitch
{
    /** Order statuses that are finished — everything else is still the customer's business. */
    private const FINISHED_ORDER = ['cart', 'completed', 'cancelled', 'canceled', 'rejected'];

    private const OPEN_BOOKING = ['pending', 'accepted', 'in_progress'];

    /** Orders and bookings that never happened — they are not a sale or a booking. */
    private const NEVER_HAPPENED_ORDER = ['cart', 'cancelled', 'canceled', 'rejected'];
    private const NEVER_HAPPENED_BOOKING = ['cancelled', 'rejected'];

    /**
     * «لا يمكن تغيير النشاط الا بعد مرور 15 يوم على اخر عملية بيع او حجز حتى
     * تكون هذه الفترة ضمانا لعدم بيع منتج غير مطابق للمواصفات او خدمة وهمية» —
     * المالك، 2026-10-02. Changing the trade is also a way to shed a bad
     * record: sell a phone that is not as described, then become a restaurant.
     * The wait keeps the customer's window to complain, rate or open a dispute
     * open while the shop still IS what it sold.
     */
    public const COOLING_OFF_DAYS = 15;

    /** Does the request move the account to a different (root, child)? */
    public function isChange(User $business, ?int $rootId, ?int $childId): bool
    {
        if ($rootId === null && $childId === null) {
            return false;
        }

        $newRoot = $rootId ?? (int) $business->category_id;
        $newChild = $childId ?? (int) $business->category_child_id;

        return $newRoot !== (int) $business->category_id || $newChild !== (int) $business->category_child_id;
    }

    /**
     * The child must stand under the root — «مصنع آثاث» under «مصانع», not
     * under «المحلات». Anything else is a 422 naming the field.
     */
    public function assertValidPair(int $rootId, int $childId): void
    {
        $rootExists = DB::table('categories')->where('id', $rootId)->where('parent_id', 0)->exists();
        if (! $rootExists) {
            throw ValidationException::withMessages(['category_id' => [__('التصنيف الرئيسي غير صحيح.')]]);
        }

        $paired = DB::table('category_parent_child')->where('parent_id', $rootId)->where('child_id', $childId)->exists();
        if (! $paired) {
            throw ValidationException::withMessages(['category_child_id' => [__('هذا التخصص لا يتبع هذا التصنيف الرئيسي.')]]);
        }
    }

    /** The last sale or booking the account had, or null when it never had one. */
    public function lastActivity(User $business): ?\Illuminate\Support\Carbon
    {
        // The later of when it was made, last touched, and — for a booking —
        // when its service ended: a stay that finished yesterday was sold today.
        $order = DB::table('orders')->where('business_id', $business->id)
            ->whereNotIn('status', self::NEVER_HAPPENED_ORDER)
            ->selectRaw('MAX(GREATEST(created_at, COALESCE(updated_at, created_at))) as at')->value('at');

        $booking = DB::table('bookings')->where('business_id', $business->id)
            ->whereNotIn('status', self::NEVER_HAPPENED_BOOKING)
            ->selectRaw('MAX(GREATEST(created_at, COALESCE(updated_at, created_at), COALESCE(LEAST(ends_at, NOW()), created_at))) as at')->value('at');

        $latest = collect([$order, $booking])->filter()->map(fn ($t) => \Illuminate\Support\Carbon::parse($t))->max();

        return $latest;
    }

    /**
     * The cooling-off the account is still inside, or null when it may switch.
     *
     * @return array{days:int,last_activity_at:string,available_at:string}|null
     */
    public function coolingOff(User $business): ?array
    {
        $last = $this->lastActivity($business);
        if (! $last) {
            return null;
        }

        $days = max(0, (int) config('bim.trade_switch_cooling_off_days', self::COOLING_OFF_DAYS));
        $available = $last->copy()->addDays($days);

        return $available->isFuture()
            ? ['days' => $days, 'last_activity_at' => $last->toIso8601String(), 'available_at' => $available->toIso8601String()]
            : null;
    }

    /**
     * What stops the switch: money or promises in flight.
     *
     * @return array<string,int> only the kinds that are non-zero
     */
    public function blockers(User $business): array
    {
        $found = [
            'open_orders' => DB::table('orders')->where('business_id', $business->id)->whereNotIn('status', self::FINISHED_ORDER)->count(),
            'open_bookings' => DB::table('bookings')->where('business_id', $business->id)->whereIn('status', self::OPEN_BOOKING)->count(),
            'frozen_deposits' => DB::table('deposits')->where('business_id', $business->id)->where('status', 'frozen')->count(),
        ];

        return array_filter($found);
    }

    /**
     * What the switch would delete, so the app can say it before asking.
     *
     * @return array<string,int>
     */
    public function inventory(User $business): array
    {
        $id = $business->id;

        return array_filter([
            'menu_items' => DB::table('menu_items')->where('business_id', $id)->count(),
            'menu_sections' => DB::table('menu_sections')->where('business_id', $id)->count(),
            'menu_bundles' => DB::table('menu_bundles')->where('business_id', $id)->count(),
            'retail_listings' => DB::table('business_catalog_listings')->where('business_id', $id)->count(),
            'prices' => DB::table('business_service_prices')->where('business_id', $id)->count(),
            'bookable_items' => DB::table('bookable_items')->where('business_id', $id)->count(),
            'tables' => DB::table('business_tables')->where('business_id', $id)->count(),
            'services' => DB::table('services')->where('business_id', $id)->count(),
            'vocabulary_ticks' => DB::table('option_user')->where('user_id', $id)->count(),
        ]);
    }

    /**
     * Wipe the old trade and move the account to the new one — one transaction,
     * so a failure half-way leaves the old trade whole.
     *
     * @return array<string,int> what was deleted
     */
    public function switch(User $business, int $rootId, int $childId): array
    {
        $deleted = $this->inventory($business);

        DB::transaction(function () use ($business, $rootId, $childId) {
            $id = $business->id;

            // One by one, not a bulk delete: a menu item's photos, offering
            // options and a price's history are cleaned by the model's own
            // delete (a query delete fires no events and leaves files behind).
            MenuItem::query()->where('business_id', $id)->get()->each->delete();
            MenuBundle::query()->where('business_id', $id)->get()->each->delete();
            BusinessCatalogListing::query()->where('business_id', $id)->get()->each->delete();
            BusinessServicePrice::query()->where('business_id', $id)->get()->each->delete();
            BookableItem::query()->where('business_id', $id)->get()->each->delete();

            // Carts of the menu that is gone.
            DB::table('menu_carts')->where('business_id', $id)->delete();

            foreach ([
                'menu_sections', 'retail_variant_groups', 'business_tables', 'services',
                'offering_price_changes', 'bookable_item_blocked_slots', 'bookable_item_price_rules',
                'business_menu_settings', 'business_booking_settings', 'business_option_group_roles',
            ] as $table) {
                DB::table($table)->where('business_id', $id)->delete();
            }

            // The vocabulary the old trade ticked means nothing to the new one;
            // an empty set offers the new trade's whole vocabulary.
            DB::table('option_user')->where('user_id', $id)->delete();

            $business->forceFill(['category_id' => $rootId, 'category_child_id' => $childId])->save();
        });

        return $deleted;
    }
}
