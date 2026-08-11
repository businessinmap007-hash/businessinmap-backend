<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «دفع مسبق» belongs to shipping and delivery, and nowhere else.
 *
 *     php artisan db:seed --class=PrepaymentScopeSeeder
 *
 * Owner, 2026-08-08: «الغِ اى ربط لخيار الدفع مقدما من كل التصنيفات الا الشحن
 * والتوصيل». It had reached **286 children** — a bakery, a gym, a barber, a
 * carpenter — because it sits in «الدفع والسداد», one of the widest groups on
 * the platform, and the whole group was granted per ROOT.
 *
 * Paying before you receive is not a payment TERM the way «كاش» or «تقسيط» are.
 * It is what a carrier asks for because the goods leave his hands and travel,
 * and on a shop that hands you the thing across a counter it says nothing. A
 * descriptive option that describes every business describes none.
 *
 * **The exception is a ROOT, not a list of ids.** «شحن وتوصيل» has three
 * children today — شركة، مكتب، مندوب — and a fourth added tomorrow inherits the
 * option without anyone remembering this file.
 *
 * Two other files used to hand it back, and both were changed with this:
 *   - `data/child_option_groups.php` — the option left `payment_terms.options`,
 *     so the group sync no longer manages it anywhere;
 *   - `SalonAndPharmacyOptionsSeeder` — it named «دفع مسبق» for both كوافير
 *     children and صيدلية by hand.
 *
 * A merchant's own tick (`option_user`) is never touched: it is his answer
 * about himself, not the taxonomy's claim about his trade. Nobody outside
 * shipping had ticked it when this ran.
 *
 * Idempotent, and it PRUNES — the point is the withdrawal.
 */
class PrepaymentScopeSeeder extends Seeder
{
    private const OPTION_NAME_AR = 'دفع مسبق';

    private const GROUP_NAME_AR = 'الدفع والسداد';

    private const ROOT_SLUG = 'shipping-delivery';

    public function run(): void
    {
        $optionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::GROUP_NAME_AR)
            ->where('o.name_ar', self::OPTION_NAME_AR)
            ->value('o.id');

        if ($optionId <= 0) {
            $this->command?->warn('  ! خيار «' . self::OPTION_NAME_AR . '» غير موجود — لم يُنفَّذ شيء.');

            return;
        }

        DB::transaction(function () use ($optionId) {
            $keep = DB::table('category_parent_child as pc')
                ->join('categories as r', 'r.id', '=', 'pc.parent_id')
                ->where('r.slug', self::ROOT_SLUG)
                ->pluck('pc.child_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            if ($keep->isEmpty()) {
                $this->command?->warn('  ! جذر «' . self::ROOT_SLUG . '» بلا أبناء — أُوقف الحذف احترازًا.');

                return;
            }

            $removed = DB::table('category_child_option')
                ->where('option_id', $optionId)
                ->whereNotIn('child_id', $keep)
                ->delete();

            // The carriers keep it whether or not anything else granted it.
            $have = DB::table('category_child_option')
                ->where('option_id', $optionId)
                ->whereIn('child_id', $keep)
                ->pluck('child_id')
                ->map(fn ($id) => (int) $id);

            /*
             * …unless the owner took it off one of them by hand.
             *
             * This was the sixth broad option seeder and the only one that had
             * never learned to read `category_child_option_decisions`. He
             * unticked «دفع مسبق» from «مكتب» and «مندوب» on 2026-08-11 and this
             * put it straight back — a full seed hid that, because
             * ChildOptionDecisionsSeeder runs dead last and took it off again,
             * but running this seeder alone undid his call. A declaration is a
             * DEFAULT; the database is the answer.
             */
            $blocked = app(ChildOptionDecisions::class)->blockedByChild();

            $missing = $keep->diff($have)
                ->reject(fn ($childId) => isset($blocked[(int) $childId][$optionId]))
                ->values();

            foreach ($missing->chunk(200) as $chunk) {
                DB::table('category_child_option')->insert(
                    $chunk->map(fn ($id) => ['child_id' => $id, 'option_id' => $optionId])->values()->all()
                );
            }

            // Said out loud rather than silently kept: a merchant outside
            // shipping who ticked it is now describing himself with a word his
            // trade no longer offers, and that is the owner's call, not mine.
            $stray = DB::table('option_user as ou')
                ->join('users as u', 'u.id', '=', 'ou.user_id')
                ->where('ou.option_id', $optionId)
                ->whereNotIn('u.category_child_id', $keep)
                ->count();

            $this->command?->info('Prepayment scope:');
            $this->command?->line("  - روابط أُزيلت خارج الشحن والتوصيل : {$removed}");
            $this->command?->line('  - أبناء الشحن والتوصيل : ' . $keep->count() . " (أُضيف {$missing->count()})");
            $this->command?->line("  - تجّار خارج النطاق ما زالوا مؤشِّرين عليه : {$stray}");
        });
    }
}
