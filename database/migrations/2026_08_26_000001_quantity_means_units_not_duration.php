<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `bookings.quantity` تعنى عددَ الوحدات، ولا تعنى المدّة.
 *
 * كان `AdminV2\BookingController` يكتب `quantity = duration_value`، فامتلأت
 * خانةُ العدد بعدد الليالى فى كلِّ صفٍّ قائم. ونتج عن ذلك أمران: لا بابَ
 * يحجز به نزيلٌ غرفتين، ولا طريقَ يعرف به المحرّك الفرقَ بين ثلاث ليالٍ
 * وثلاث غرف — فكان يضرب فى الرقم مرّةً واحدة ويسمّيه أحيانًا هذا وأحيانًا ذاك.
 *
 * الزمنُ الآن من النافذة (`ServiceExecutionEngine::periodsBetween`)، والعددُ
 * من `quantity`. وهما يُضربان.
 *
 * ── ولمَ تُمسّ الصفوفُ القائمة ──────────────────────────────────────────────
 *
 * لأن المعنى تغيّر تحتها. صفٌّ يقول «الكمّية ٦» ويعنى ستَّ ليالٍ سيُقرأ الآن
 * «ستُّ غرف»، فتحريرُه من لوحة الإدارة يعيد حسابه ستَّ ليالٍ × ستَّ غرف. ولم
 * يكن أىٌّ منها لغرفتين أصلًا: لا شاشةَ فى المنصّة تسأل «كم غرفة؟».
 *
 * و`price` لا يُمسّ: رقمٌ اتُّفق عليه، لا حسابٌ يُعاد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        /*
         * القيمةُ القديمة تُحفظ فى `meta` لا تُرمى.
         *
         * فمن أراد أن يعرف ماذا كان مكتوبًا فى الخانة قبل أن يتغيّر معناها
         * يجده على الصفّ نفسه، لا فى ملفٍّ إلى جانب القاعدة.
         */
        DB::table('bookings')->where('quantity', '>', 1)
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $meta = json_decode((string) ($row->meta ?? ''), true);
                    $meta = is_array($meta) ? $meta : [];
                    $meta['legacy_quantity'] = (int) $row->quantity;

                    DB::table('bookings')->where('id', $row->id)->update([
                        'quantity' => 1,
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        DB::table('bookings')->whereNotNull('meta')->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $meta = json_decode((string) ($row->meta ?? ''), true);

                    if (! is_array($meta) || ! isset($meta['legacy_quantity'])) {
                        continue;
                    }

                    $was = (int) $meta['legacy_quantity'];
                    unset($meta['legacy_quantity']);

                    DB::table('bookings')->where('id', $row->id)->update([
                        'quantity' => max($was, 1),
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }
};
