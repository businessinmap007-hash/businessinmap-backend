<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «مراكز التجميل والسبا والأظافر والمكياج» — a trade the salon root had no door for — and the men's barber trimmed
 * back to what a barber does.
 *
 *     php artisan db:seed --class=BeautyCenterChildSeeder
 *
 * ── Why a trade of its own ──────────────────────────────────────────────────
 *
 * Under «كوافير» the platform has «كوافير حريمى» and «كوافير رجالي»: a hairdresser, by the audience he cuts for. A
 * beauty centre, a spa, a nail bar or a make-up studio is neither — it sells the seventeen treatments of «خدمات
 * الكوافير والتجميل» minus the men's barbering, plus massage and facials, and it said so by filing itself under a
 * hairdresser. Those words already exist as the LINE group (priced services); what was missing is the trade that
 * carries them whole.
 *
 * ── How it is built (the butcher's rule: MIRROR, never grant a group) ──────
 *
 * • The child sits under root #443 beside the two hairdressers and **mirrors «كوافير حريمى»** for everything that is
 *   not a priced line (payment, venue, facilities, how the service is given): a whole group carries every ruling ever
 *   made inside it, backwards.
 * • Its lines are the salon group minus the two men's-barbering rows, plus «مساج وتدليك» and «تنظيف بشرة وفيشل» —
 *   new rows of the same group, so the price screen lists them with the rest.
 * • Booking is the hairdresser's own: an appointment with the business, no unit — written through the one writer that
 *   keeps link and config in step, copied from the neighbour's live row. The id is FIXED (`CHILD_ID`) because
 *   `data/booking_patterns.php` names children by id and the pattern seeder withdraws what it does not list.
 *
 * ── The men's barber ────────────────────────────────────────────────────────
 *
 * «كوافير رجالي» was handed three rows by the services-gaps pass that a barber does not sell — «تركيب رموش»،
 * «تركيب أظافر»، «حنة ونقش». They are withdrawn: the link is deleted AND the decision recorded in the ledger, which
 * is what every seeder asks first, so no later run hands them back ([[seeder-must-withdraw]]).
 *
 * Idempotent.
 */
class BeautyCenterChildSeeder extends Seeder
{
    /** Fixed: the booking-pattern map is keyed by child id. */
    public const CHILD_ID = 554;

    private const NAME_AR = 'مركز تجميل وسبا';

    private const NAME_EN = 'Beauty and spa centre';

    private const ROOT_NAME = 'كوافير';

    private const MIRRORS = 'كوافير حريمى';

    private const MENS = 'كوافير رجالي';

    private const LINE_GROUP = 'خدمات الكوافير والتجميل';

    /** What a centre sells that a hairdresser's list never had. */
    private const NEW_LINES = [
        'مساج وتدليك' => 'Massage',
        'تنظيف بشرة وفيشل' => 'Facial cleansing',
    ];

    /** Men's barbering: a barber's own words, not a centre's. */
    private const NOT_FOR_A_CENTRE = ['حلاقة ذقن وتهذيب', 'عناية بالشعر للرجال'];

    /** What a barber does not sell. */
    private const NOT_FOR_A_BARBER = ['تركيب رموش', 'تركيب أظافر', 'حنة ونقش'];

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('name_ar', self::ROOT_NAME)->where('parent_id', 0)->value('id');

            if ($rootId <= 0) {
                $this->command?->warn('  ! جذر «' . self::ROOT_NAME . '» غير موجود.');

                return;
            }

            $childId = $this->child();

            if ($childId <= 0) {
                return;
            }

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created = $this->newLines();
            $this->giveToKeeper();
            $this->mirrorRulings($childId);
            $linked = $this->linkOptions($childId, $rootId);
            $wired = $this->wireServices($childId, $rootId);
            $withdrawn = $this->withdrawFromBarber($rootId);

            $this->command?->info('Beauty centre child:');
            $this->command?->line("  - «" . self::NAME_AR . "» #{$childId}");
            $this->command?->line("  - خدمات جديدة : {$created}");
            $this->command?->line("  - خيارات رُبطت : {$linked}");
            $this->command?->line("  - خدمات وُصلت : {$wired}");
            $this->command?->line("  - سُحب من الكوافير الرجالي : {$withdrawn}");
        });
    }

    private function child(): int
    {
        $byName = (int) DB::table('category_children_master')->where('name_ar', self::NAME_AR)->value('id');

        if ($byName > 0) {
            return $byName;
        }

        if (DB::table('category_children_master')->where('id', self::CHILD_ID)->exists()) {
            $this->command?->warn('  ! المعرّف ' . self::CHILD_ID . ' مشغولٌ بابنٍ آخر — اضبط CHILD_ID وملف booking_patterns.php معًا.');

            return 0;
        }

        DB::table('category_children_master')->insert([
            'id' => self::CHILD_ID,
            'name_ar' => self::NAME_AR,
            'name_en' => self::NAME_EN,
            'reorder' => (int) DB::table('category_children_master')->max('reorder') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::CHILD_ID;
    }

    /** New rows of the salon group — found by name first, so a re-run (or an admin's own row) is never duplicated. */
    private function newLines(): int
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', self::LINE_GROUP)->value('id');

        if ($groupId <= 0) {
            $this->command?->warn('  ! مجموعة «' . self::LINE_GROUP . '» غير موجودة.');

            return 0;
        }

        $n = 0;

        foreach (self::NEW_LINES as $ar => $en) {
            $exists = DB::table('options')->where('name_ar', $ar)->orWhere('name_en', $en)->exists();

            if ($exists) {
                continue;
            }

            DB::table('options')->insert([
                'group_id' => $groupId,
                'name_ar' => $ar,
                'name_en' => $en,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * The neighbour's RULINGS come with the mirror: what the owner took off «كوافير حريمى» (cash, family, instalments,
     * online…) or put there on purpose is recorded for this child too. Without them a broad group seeder — which
     * grants whole groups to every child under the root — hands back the same day everything the neighbour had
     * refused, and the ledger tests fail on the first run ([[seeder-must-withdraw]]).
     */
    private function mirrorRulings(int $childId): int
    {
        $mirrorId = (int) DB::table('category_children_master')->where('name_ar', self::MIRRORS)->value('id');

        if ($mirrorId <= 0) {
            return 0;
        }

        $n = 0;

        foreach (DB::table('category_child_option_decisions')->where('child_id', $mirrorId)->get() as $ruling) {
            $exists = DB::table('category_child_option_decisions')
                ->where('child_id', $childId)->where('category_id', $ruling->category_id)
                ->where('option_id', $ruling->option_id)->where('kind', $ruling->kind)->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option_decisions')->insert([
                'child_id' => $childId,
                'category_id' => $ruling->category_id,
                'option_id' => $ruling->option_id,
                'kind' => $ruling->kind,
                'source' => 'beauty-centre-child',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $n++;
        }

        // a link a ruling forbids is removed (a broad seeder may have handed it over before the rulings existed)
        $forbidden = DB::table('category_child_option_decisions')
            ->where('child_id', $childId)->where('kind', 'withdrawn')->pluck('option_id');

        if ($forbidden->isNotEmpty()) {
            DB::table('category_child_option')->where('child_id', $childId)->whereIn('option_id', $forbidden)->delete();
        }

        return $n;
    }

    /**
     * «متخصص كوافير» #136 is the child that holds the whole salon group — `SalonRemodelTest` guards that it can name
     * every service the group has. A row added to the group therefore reaches it too (the specialist who does a
     * facial or a massage at home is the same person the centre employs).
     */
    private function giveToKeeper(): int
    {
        $keeper = (int) DB::table('category_children_master')->where('name_ar', 'متخصص كوافير')->value('id');

        if ($keeper <= 0) {
            return 0;
        }

        $n = 0;

        foreach (array_keys(self::NEW_LINES) as $name) {
            $optionId = (int) DB::table('options')->where('name_ar', $name)->value('id');
            $exists = DB::table('category_child_option')->where('child_id', $keeper)->where('option_id', $optionId)->exists();

            if ($optionId <= 0 || $exists) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $keeper, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0,
            ]);
            $n++;
        }

        return $n;
    }

    private function linkOptions(int $childId, int $rootId): int
    {
        $lines = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::LINE_GROUP)
            ->whereNotIn('o.name_ar', self::NOT_FOR_A_CENTRE)
            ->pluck('o.id');

        // everything the neighbour answers that is NOT one of its priced lines: payment, venue, facilities, how served
        $mirrorId = (int) DB::table('category_children_master')->where('name_ar', self::MIRRORS)->value('id');

        $axes = $mirrorId <= 0 ? collect() : DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $mirrorId)
            ->where('g.price_role', '!=', 'line')
            ->pluck('o.id');

        $wanted = $lines->merge($axes)->unique()->map(fn ($id) => (int) $id)->values()->all();

        // the ledger first, like every option seeder: what the owner takes off this centre tomorrow stays off
        $allowed = app(ChildOptionDecisions::class)->filter($childId, $rootId, $wanted);

        $n = 0;

        foreach ($allowed as $optionId) {
            $exists = DB::table('category_child_option')
                ->where('child_id', $childId)->where('category_id', 0)->where('option_id', $optionId)->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => 0,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Booking = the hairdresser's own appointment config (copied from the neighbour's live row, so the two never
     * drift), and offers like every shop beside it.
     */
    private function wireServices(int $childId, int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);
        $n = 0;

        $bookingId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
        $mirrorId = (int) DB::table('category_children_master')->where('name_ar', self::MIRRORS)->value('id');

        $copied = $bookingId > 0 && $mirrorId > 0
            ? DB::table('category_service_configs')
                ->where('platform_service_id', $bookingId)->where('child_id', $mirrorId)->where('is_active', 1)->value('config')
            : null;

        $config = is_string($copied) ? (json_decode($copied, true) ?: []) : [];
        // the neighbour's bookkeeping about WHO wrote it is not part of its shape
        unset($config['config_source'], $config['config_updated_at']);

        $config = $config + [
            'booking_modes' => [],
            'item_family' => null,
            'requires_bookable_item' => false,
            'requires_start_end' => false,
            'supports_quantity' => false,
            'supports_guest_count' => false,
            'supports_extras' => false,
            'required_fields' => ['datetime'],
            'item_groups' => [84],
            'allowed_item_types' => ['booking_appointment'],
            'booking_pattern' => 'appointment',
            'booking_patterns' => ['appointment'],
        ];

        $plan = ['booking' => $config, 'business_offers' => []];

        foreach ($plan as $key => $patch) {
            $serviceId = (int) DB::table('platform_services')->where('key', $key)->value('id');

            if ($serviceId <= 0) {
                $this->command?->warn("  ! خدمة «{$key}» غير موجودة.");

                continue;
            }

            $writer->enable($rootId, $childId, $serviceId, $patch, null, null, 'beauty-centre-child');
            $n++;
        }

        return $n;
    }

    /** Take three rows off the men's barber: the link goes, and the ledger remembers why it must not come back. */
    private function withdrawFromBarber(int $rootId): int
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', self::MENS)->value('id');

        if ($childId <= 0) {
            return 0;
        }

        $ids = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::LINE_GROUP)
            ->whereIn('o.name_ar', self::NOT_FOR_A_BARBER)
            ->pluck('o.id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return 0;
        }

        // a service this barber has already PRICED stays (and is reported): removing a word he has sold in would
        // strand a live row he could no longer edit
        $priced = DB::table('business_service_prices as p')
            ->join('users as u', 'u.id', '=', 'p.business_id')
            ->where('u.category_child_id', $childId)
            ->whereIn('p.line_option_id', $ids)
            ->pluck('p.line_option_id')->map(fn ($id) => (int) $id)->unique()->all();

        foreach ($priced as $optionId) {
            $this->command?->warn("  ! الخيار #{$optionId} مسعَّرٌ عند نشاطٍ فبقى.");
        }

        $ids = array_values(array_diff($ids, $priced));

        if ($ids === []) {
            return 0;
        }

        $removed = DB::table('category_child_option')->where('child_id', $childId)->whereIn('option_id', $ids)->delete();

        app(ChildOptionDecisions::class)->record($childId, $rootId, $ids, 'beauty-centre-child');

        return $removed;
    }
}
