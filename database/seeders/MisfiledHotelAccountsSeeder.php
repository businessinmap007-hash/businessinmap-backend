<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-files the accounts that the hotel star children collected by accident
 * (owner-approved list, 2026-08-02). Those six children were acting as a signup
 * dumping ground: 67 accounts sat on them and only one — #212 فندق الاندلس —
 * had any hotel data, the rest reading «اعمال سباكة», «خدمات ليموزين», «ملابس».
 *
 *   php artisan db:seed --class=MisfiledHotelAccountsSeeder
 *
 * Only the 22 whose trade the NAME states outright are moved. «اعمال» (#4040)
 * is deliberately left behind despite matching a craft keyword — the word alone
 * means "works" and names no trade, and a wrong root is worse than an obviously
 * wrong one. The remaining 44 are personal names or test rows that cannot be
 * classified without asking their owners.
 *
 * Both category_id and category_child_id are updated, since a child means
 * nothing without its root. Idempotent: an account already off the star
 * children is skipped, so a re-run reports zero.
 */
class MisfiledHotelAccountsSeeder extends Seeder
{
    private const STAR_CHILD_IDS = [1, 2, 3, 4, 5, 6];

    /** account id => [root slug, child name_ar, why] */
    private const MOVES = [
        // ── شحن وتوصيل: company-run fleets vs. individual couriers
        1570 => ['shipping-delivery', 'شركة', 'خدمات ليموزين'],
        2827 => ['shipping-delivery', 'شركة', 'خدمه ليموزين'],
        3010 => ['shipping-delivery', 'شركة', 'ايجار سيارات عقد سنوي'],
        2304 => ['shipping-delivery', 'شركة', 'توصيل رحلات وسفاريات'],
        2194 => ['shipping-delivery', 'مندوب', 'مندوب شحن'],
        3387 => ['shipping-delivery', 'مندوب', 'شحن وتوصيل موتسكل'],
        2002 => ['shipping-delivery', 'مندوب', 'مالك سيارة'],

        // ── مهن وحرفيين: the trade is named outright
        914 => ['professions', 'سباك', 'اعمال سباكة'],
        1166 => ['professions', 'نجار موبيليا', 'ورشة نجارة'],
        1613 => ['professions', 'نجار موبيليا', 'اعمال نجاره'],
        1643 => ['professions', 'نجار موبيليا', 'ورشة نجارة موبيليا ومطابخ'],
        1489 => ['professions', 'نقاش', 'نقاش'],

        // ── شركات: travel desks
        1561 => ['companies', 'سياحة', 'حجز فنادق'],
        452 => ['companies', 'سياحة', 'Tkit travel'],
        3379 => ['companies', 'رحلات', 'ام ايمى للرحلات'],

        // ── معارض: showroom retail
        500 => ['exhibitions', 'ملابس جاهزة', 'ملابس'],
        1352 => ['exhibitions', 'آثاث', 'موبليات'],
        1884 => ['exhibitions', 'مفروشات', 'فرش اساس'],

        // ── عقارات: an owner letting units, and a property office
        403 => ['property-and-land', 'مالك عقار', 'شقق مفروشة للايجار'],
        2481 => ['property-and-land', 'مكتب عقاري', 'مكتب عقاري براس البر'],

        // ── مطاعم ومصانع
        498 => ['restaurants-cafes', 'مطعم', 'كاترينج'],
        190 => ['factories', 'آثاث', 'مصنع أثاث فندقي'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $moved = [];
            $skipped = 0;

            foreach (self::MOVES as $userId => [$rootSlug, $childName, $why]) {
                $user = DB::table('users')
                    ->where('id', $userId)
                    ->where('type', 'business')
                    ->whereIn('category_child_id', self::STAR_CHILD_IDS)
                    ->first(['id', 'name']);

                if (! $user) {
                    $skipped++; // already re-filed
                    continue;
                }

                $rootId = (int) DB::table('categories')->where('slug', $rootSlug)->value('id');

                // duplicate child names exist (ملابس جاهزة twice under معارض) —
                // take the lowest id so the choice is stable across runs
                $childId = (int) DB::table('category_parent_child as pc')
                    ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', $rootId)
                    ->where('ch.name_ar', $childName)
                    ->orderBy('ch.id')
                    ->value('ch.id');

                if (! $rootId || ! $childId) {
                    $this->command?->warn("  ! تعذّر إيجاد «{$childName}» تحت «{$rootSlug}» — تُرك #{$userId}.");
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'category_id' => $rootId,
                    'category_child_id' => $childId,
                ]);

                $moved[] = "#{$user->id} " . trim((string) $user->name) . " → {$rootSlug}/{$childName} ({$why})";
            }

            $left = DB::table('users')->whereIn('category_child_id', self::STAR_CHILD_IDS)
                ->where('type', 'business')->count();

            $this->command?->info('Misfiled hotel accounts re-filed: ' . count($moved) . " (skipped as already moved: {$skipped})");

            foreach ($moved as $line) {
                $this->command?->line('      ' . $line);
            }

            $this->command?->warn("  ! ما زال {$left} حسابًا على أبناء النجوم (أسماء شخصية/اختبارية لا تدل على نشاط).");
        });
    }
}
