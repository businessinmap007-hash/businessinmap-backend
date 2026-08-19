<?php

namespace Database\Seeders;

use App\Enums\BookingPattern;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * يكتب نمط الحجز على كل إعداد حجزٍ نشط، من data/booking_patterns.php.
 *
 *     php artisan db:seed --class=BookingPatternSeeder
 *     php artisan db:seed --class=BookingPatternSeeder -- --dry-run
 *
 * ── ماذا يكتب ────────────────────────────────────────────────────────────────
 *
 * `booking_pattern` — الأساسى — و`booking_patterns` — كل ما يفتحه الطفل.
 * ثم يشتقّ المفاتيح الستّة القديمة من النمط (BookingPattern::legacyFlags)
 * بدل أن تُكتب يدويًا، وهو سببُ تشوُّهها أصلًا: ثمانية مفاتيح مستقلّة، كل
 * بذرةٍ تضبط ما تذكّرته.
 *
 * ── ولا يمسّ ─────────────────────────────────────────────────────────────────
 *
 * `allowed_item_types` و`item_groups` — تلك ملكُ بذرات الفروع، وهى مضبوطةٌ
 * ومحدودةٌ اليوم على ١٩٤ إعدادًا بلا استثناء. النمط يصف **شكل العملية**، لا
 * ما يُباع فيها.
 *
 * ── والخمسون تُصحَّح كنتيجة، لا كعملية ───────────────────────────────────────
 *
 * خمسون إعدادًا نوعُها الوحيد `booking_appointment` كانت تطالب بوحدةٍ محجوزة —
 * ٤٩ منها تحت جذر «شركات» وحده، وخمسةَ عشرَ ابنًا منها يحملون الصفَّ نفسه
 * تحت جذرٍ ثانٍ بالعلم المعاكس. ٢٥٤ نشاطًا يقفون عليها، بنَوا بينهم **صفر**
 * وحدة. لا يوجد سطرٌ هنا يستهدفها: «موعد» لا وحدة فيه، فيسقط العلم عنها
 * لأن النمط قال ذلك.
 *
 * ── والامتناع ليس نفيًا ──────────────────────────────────────────────────────
 *
 * نمط «مدّة» وحدَه `UNIT_OPTIONAL`: البلايستيشن يؤجّر أجهزة، والبولينج
 * حارات، والجيم لا يؤجّر شيئًا. الطفل الواحد فوقهم لا يعرف أيّهم هو، فلا
 * يحكم — و`legacyFlags()` تحذف المفتاح بدل أن تكتبه `false`. الصفوف الثمانية
 * التى تحمل `true` اليوم تبقى كما هى حتى تصل إعدادات النشاط (الخطوة ٣)، لأن
 * كتابة `false` عليها الآن حكمٌ لا امتناع.
 *
 * ── ويسحب ما لا يعلنه ────────────────────────────────────────────────────────
 *
 * كل إعداد حجزٍ نشطٍ لابنٍ غير مذكورٍ فى الملف يُبلَّغ عنه، ويُنزَع منه أىُّ
 * نمطٍ سابق. بذرةٌ لا تسحب هى تراكمٌ لا إعلان — والقاعدة مكتوبةٌ بثمنها فى
 * seeder-must-withdraw.
 *
 * Idempotent.
 */
class BookingPatternSeeder extends Seeder
{
    /** المفاتيح التى يملكها هذا الملف ولا يكتبها غيره. */
    private const OWNED = [
        'booking_pattern',
        'booking_patterns',
        'requires_bookable_item',
        'requires_start_end',
        'supports_quantity',
        'supports_guest_count',
        'supports_extras',
        'required_fields',
    ];

    public function run(): void
    {
        $dry = in_array('--dry-run', (array) ($_SERVER['argv'] ?? []), true);

        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('  ! خدمة الحجز غير موجودة.');

            return;
        }

        /** @var array<int, string[]> $map */
        $map = require __DIR__ . '/data/booking_patterns.php';

        $rows = DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->get(['id', 'child_id', 'category_id', 'config']);

        $written = 0;
        $unchanged = 0;
        $stripped = 0;
        $missing = [];

        foreach ($rows as $row) {
            $config = json_decode((string) $row->config, true);
            $config = is_array($config) ? $config : [];

            $declared = $map[(int) $row->child_id] ?? null;

            $next = $declared === null
                ? $this->withoutPattern($config)
                : $this->withPattern($config, $declared);

            if ($declared === null) {
                $missing[(int) $row->child_id] = true;
            }

            if ($this->same($config, $next)) {
                $unchanged++;

                continue;
            }

            $declared === null ? $stripped++ : $written++;

            if (! $dry) {
                DB::table('category_service_configs')->where('id', $row->id)->update([
                    'config' => json_encode($next, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->report($rows->count(), $written, $unchanged, $stripped, array_keys($missing), $dry);
    }

    /** النمط الأوّل هو الأساسى؛ وما بعده يفتحه الطفل ويختاره النشاط. */
    private function withPattern(array $config, array $declared): array
    {
        $patterns = array_values(array_filter(array_map(
            fn ($p) => BookingPattern::tryFrom((string) $p),
            $declared
        )));

        if ($patterns === []) {
            return $this->withoutPattern($config);
        }

        $primary = $patterns[0];

        $config['booking_pattern'] = $primary->value;
        $config['booking_patterns'] = array_map(fn (BookingPattern $p) => $p->value, $patterns);

        /*
         * الامتناع يُبقى ما كان، ولا يكتب شيئًا.
         *
         * `legacyFlags()` تُغفل `requires_bookable_item` حين يكون النمط
         * OPTIONAL، فالمفتاح المخزَّن يبقى على حاله بلا تدخُّل. وهذا مقصود:
         * ملعب الكرة يحمل `true` اليوم، وكتابة `false` عليه قبل أن تصل
         * إعدادات النشاط تنزع حارسًا ولا تضع مكانه أحدًا.
         */
        foreach ($primary->legacyFlags() as $key => $value) {
            $config[$key] = $value;
        }

        /*
         * `config_source` لا يُمَسّ.
         *
         * الحقل يقول مَن كتب الإعداد، وهذا الملف لا يكتب الإعداد — يكتب
         * ثمانية مفاتيح منه. ختمُه باسمى يمحو أن `allowed_item_types` جاءت من
         * شاشة الإدارة أو من بذرة الفروع، وهو الأثر الذى يُفكّ به ما فعلته
         * بذرةٌ بعينها. و`booking_pattern` يعرّف نفسه: لا أحد غيرى يكتبه.
         */
        return $config;
    }

    /** ابنٌ لا يعلنه الملف: يُنزَع نمطه ولا يُخمَّن له بديل. */
    private function withoutPattern(array $config): array
    {
        unset($config['booking_pattern'], $config['booking_patterns']);

        return $config;
    }

    /** المقارنة تتجاهل الطابع الزمنى وحده — وإلا كتب كل تشغيلٍ كلَّ صف. */
    private function same(array $a, array $b): bool
    {
        unset($a['config_updated_at'], $b['config_updated_at']);

        ksort($a);
        ksort($b);

        return json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
    }

    private function report(int $total, int $written, int $unchanged, int $stripped, array $missing, bool $dry): void
    {
        $this->command?->info('Booking patterns:' . ($dry ? '  [تجربة — لم يُكتب شىء]' : ''));
        $this->command?->line("  - إعدادات حجزٍ نشطة : {$total}");
        $this->command?->line("  - أنماط كُتبت        : {$written}");
        $this->command?->line("  - بلا تغيير          : {$unchanged}");
        $this->command?->line("  - أنماط نُزعت        : {$stripped}");

        if ($missing !== []) {
            $names = DB::table('category_children_master')->whereIn('id', $missing)
                ->pluck('name_ar', 'id')
                ->map(fn ($n, $id) => "#{$id} «{$n}»")->implode('، ');

            $this->command?->warn('  ! أبناء بإعداد حجزٍ نشط ولا نمطَ لهم فى الملف: ' . $names);
        }
    }
}
