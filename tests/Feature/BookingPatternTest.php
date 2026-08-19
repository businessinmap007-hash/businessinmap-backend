<?php

namespace Tests\Feature;

use App\Enums\BookingPattern;
use Database\Seeders\BookingPatternSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «booking تختلف من حجز الفنادق او ايجار الشقق وحجز موعد عند طبيب او وقت فى
 * البلايستيشن او ملعب كرة» — المالك، 2026-08-19.
 *
 * ما يحرسه هذا الملف هو أن الاختلاف صار مكتوبًا فى مكانٍ واحد يُقرأ، بدل
 * ثمانية مفاتيح كانت ستّةٌ منها مطفأةً على ١٩٤ إعدادًا بلا استثناء.
 */
class BookingPatternTest extends TestCase
{
    use DatabaseTransactions;

    private function serviceId(): int
    {
        return (int) DB::table('platform_services')->where('key', 'booking')->value('id');
    }

    /** @return array<int, string[]> */
    private function map(): array
    {
        return require database_path('seeders/data/booking_patterns.php');
    }

    private function activeConfigs()
    {
        return DB::table('category_service_configs')
            ->where('platform_service_id', $this->serviceId())
            ->where('is_active', 1)
            ->get(['child_id', 'category_id', 'config']);
    }

    private function apply(): void
    {
        $this->seed(BookingPatternSeeder::class);
    }

    private function fingerprint(): array
    {
        return $this->activeConfigs()->map(function ($r) {
            $c = json_decode((string) $r->config, true) ?: [];
            unset($c['config_updated_at']);
            ksort($c);

            return json_encode($c, JSON_UNESCAPED_UNICODE);
        })->all();
    }

    /** الملف يعلن أنماطًا معروفة، بلا تكرار، والأوّل هو الأساسى. */
    public function test_the_declaration_is_well_formed(): void
    {
        foreach ($this->map() as $childId => $patterns) {
            $this->assertIsInt($childId, 'المفتاح معرّفٌ لا اسم — الاسم يتغيّر والمعرّف لا');
            $this->assertNotEmpty($patterns, "#{$childId} أُعلن بقائمةٍ فارغة");
            $this->assertSame(
                array_values(array_unique($patterns)),
                array_values($patterns),
                "#{$childId} يكرّر نمطًا"
            );

            foreach ($patterns as $p) {
                $this->assertNotNull(BookingPattern::tryFrom($p), "#{$childId} يعلن نمطًا لا وجود له: «{$p}»");
            }
        }
    }

    /** لا إعداد حجزٍ نشطٍ بلا نمط — وإلا رأى العميل شاشةً لا أحد وصفها. */
    public function test_every_live_booking_config_is_covered(): void
    {
        $map = $this->map();
        $orphans = [];

        foreach ($this->activeConfigs() as $row) {
            if (! isset($map[(int) $row->child_id])) {
                $orphans[(int) $row->child_id] = true;
            }
        }

        $names = DB::table('category_children_master')->whereIn('id', array_keys($orphans))
            ->pluck('name_ar', 'id')->map(fn ($n, $id) => "#{$id} «{$n}»")->implode('، ');

        $this->assertSame([], array_keys($orphans), "أبناء بإعداد حجزٍ نشط ولا نمطَ لهم: {$names}");
    }

    /**
     * النمط صفةُ المهنة لا صفةُ الرفّ.
     *
     * خمسةَ عشرَ ابنًا يقفون على جذرين، وكانوا يحملون العلمَ المعاكس فى كلٍّ
     * منهما — «رخام» تحت «شركات» يطالب بوحدة وتحت «معارض» لا. لم يكن ذلك
     * تصميمًا؛ كان جذرًا لم تمرّ عليه المكنسة.
     */
    public function test_a_child_carries_one_pattern_across_its_roots(): void
    {
        $this->apply();

        $seen = [];

        foreach ($this->activeConfigs() as $row) {
            $pattern = BookingPattern::tryFromConfig($row->config)?->value;
            $childId = (int) $row->child_id;

            if (isset($seen[$childId])) {
                $this->assertSame($seen[$childId], $pattern, "#{$childId} يحمل نمطين مختلفين تحت جذرين");
            }

            $seen[$childId] = $pattern;
        }
    }

    /** «موعد» وقتٌ مع النشاط نفسه — ولا وحدةَ فيه تُحجَز. */
    public function test_an_appointment_never_demands_a_unit(): void
    {
        $this->apply();

        $map = $this->map();
        $checked = 0;

        foreach ($this->activeConfigs() as $row) {
            if (($map[(int) $row->child_id][0] ?? null) !== BookingPattern::APPOINTMENT->value) {
                continue;
            }

            $config = json_decode((string) $row->config, true) ?: [];
            $checked++;

            $this->assertFalse(
                (bool) ($config['requires_bookable_item'] ?? false),
                "#{$row->child_id} يبيع موعدًا ومع ذلك يطالب بوحدة"
            );
        }

        $this->assertGreaterThan(100, $checked, 'ثلثا الشجرة مواعيد — العدد أقلّ من المتوقّع');
    }

    /** الغرفة والطاولة يُختاران — لا حجزَ بلا وحدة. */
    public function test_a_stay_and_a_table_always_demand_a_unit(): void
    {
        $this->apply();

        $map = $this->map();
        $always = [BookingPattern::STAY->value, BookingPattern::TABLE->value];

        foreach ($this->activeConfigs() as $row) {
            if (! in_array($map[(int) $row->child_id][0] ?? null, $always, true)) {
                continue;
            }

            $config = json_decode((string) $row->config, true) ?: [];

            $this->assertTrue(
                (bool) ($config['requires_bookable_item'] ?? false),
                "#{$row->child_id} يبيع غرفةً أو طاولة بلا وحدة تُختار"
            );
        }
    }

    /**
     * الامتناع ليس نفيًا.
     *
     * البلايستيشن يؤجّر أجهزة، والبولينج حارات، والجيم لا يؤجّر شيئًا. الطفل
     * الواحد فوقهم لا يعرف أيّهم هو، فلا يحكم — والبذرة لا تلمس ما كان.
     */
    public function test_duration_leaves_the_unit_question_to_the_business(): void
    {
        $map = $this->map();
        $before = [];

        foreach ($this->activeConfigs() as $row) {
            if (($map[(int) $row->child_id][0] ?? null) !== BookingPattern::DURATION->value) {
                continue;
            }

            $config = json_decode((string) $row->config, true) ?: [];
            $before["{$row->category_id}:{$row->child_id}"] = $config['requires_bookable_item'] ?? null;
        }

        $this->assertNotEmpty($before, 'لا إعدادات «مدّة» — الاختبار لا يقيس شيئًا');

        $this->apply();

        foreach ($this->activeConfigs() as $row) {
            $key = "{$row->category_id}:{$row->child_id}";

            if (! array_key_exists($key, $before)) {
                continue;
            }

            $config = json_decode((string) $row->config, true) ?: [];

            $this->assertSame(
                $before[$key],
                $config['requires_bookable_item'] ?? null,
                "«مدّة» حكمت على الوحدة عند {$key} بدل أن تمتنع"
            );
        }
    }

    /** المفاتيح الستّة تُشتقّ من النمط — لا تُكتب يدويًا، وهو سبب تشوُّهها. */
    public function test_the_legacy_flags_are_derived_from_the_pattern(): void
    {
        $this->apply();

        $map = $this->map();

        foreach ($this->activeConfigs() as $row) {
            $primary = BookingPattern::tryFrom($map[(int) $row->child_id][0] ?? '');

            if (! $primary) {
                continue;
            }

            $config = json_decode((string) $row->config, true) ?: [];

            foreach ($primary->legacyFlags() as $key => $expected) {
                $this->assertSame(
                    $expected,
                    $config[$key] ?? null,
                    "#{$row->child_id} «{$primary->label()}» — «{$key}» لا يطابق النمط"
                );
            }
        }
    }

    /** الفندق يسأل «كم نزيلًا»، والمطعم «كم فردًا» — أول مرّة على المنصّة. */
    public function test_a_stay_and_a_table_ask_for_the_head_count(): void
    {
        $this->assertContains('guest_count', BookingPattern::STAY->requires());
        $this->assertContains('party_size', BookingPattern::TABLE->requires());
        $this->assertContains('quantity', BookingPattern::DURATION->asks());

        $this->apply();

        $hotel = DB::table('category_service_configs')
            ->where('platform_service_id', $this->serviceId())
            ->where('child_id', 536)->where('is_active', 1)->value('config');

        $this->assertTrue((bool) (json_decode((string) $hotel, true)['supports_guest_count'] ?? false));
    }

    /** «مدرب يفتح كورس كنمط ثانى» — المالك، 2026-08-19. */
    public function test_the_trainer_opens_a_course_as_a_second_pattern(): void
    {
        $this->assertSame(
            [BookingPattern::DURATION->value, BookingPattern::COURSE->value],
            $this->map()[547] ?? [],
            'المدرّب فقد نمطه الثانى'
        );

        $this->apply();

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('platform_service_id', $this->serviceId())
            ->where('child_id', 547)->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertSame(BookingPattern::DURATION->value, $config['booking_pattern'] ?? null);
        $this->assertContains(BookingPattern::COURSE->value, $config['booking_patterns'] ?? []);
    }

    /** التشغيل الثانى لا يكتب شيئًا — وإلا فهى حلقةٌ لا بذرة. */
    public function test_the_seeder_is_idempotent(): void
    {
        $this->apply();
        $first = $this->fingerprint();

        $this->apply();

        $this->assertSame($first, $this->fingerprint(), 'التشغيل الثانى غيّر شيئًا');
    }
}
