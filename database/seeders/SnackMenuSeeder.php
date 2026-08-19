<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * منيو طعامٍ لمكانِ ترفيهٍ يبيع شايًا وساندويتشًا بجوار خدمته الأصلية.
 *
 *     php artisan db:seed --class=SnackMenuSeeder
 *
 * «البلايستشن يمكن ان يقدم مشروبات ساخنة وسناكس وساندويتشات خفيفة» — المالك،
 * 2026-08-19. والقائمة فى data/snack_menu_children.php، مفتاحُها (جذر : ابن)
 * ومقصورةٌ على ما أُقرّ بالاسم.
 *
 * ── لا يمسّ الحجز ────────────────────────────────────────────────────────────
 *
 * يضيف `menu` ولا شىء غيرها. نمطُ الحجز وإعدادُه ووحداتُه تبقى كما هى: صالةُ
 * البلايستيشن تبيع وقتًا وتبيع معه طعامًا، ولا يجعلها الطعامُ مطعمًا.
 *
 * و«الطاولات» و«نداءات الطاولات» ستظهران له، لأنهما محروستان بـ`menu_food`
 * وهو الآن يبيعه. وهذا مقصود لا أثرٌ جانبىّ: صالةٌ تقدّم ساندويتشات على
 * طاولاتٍ تستعملهما فعلًا، ومن لا طاولاتِ عنده يتركهما فارغتين بلا كلفة.
 *
 * ── والنوع `menu_food` وحده ─────────────────────────────────────────────────
 *
 * لا قائمةٌ فارغة: الفراغُ يُقرأ «كلَّ نوعٍ تعرفه الخدمة»، فتصير صالةُ الألعاب
 * معروضةً كعقارٍ للبيع. وهى الحال التى استغرق تنظيفُها أسبوعًا.
 *
 * Idempotent.
 */
class SnackMenuSeeder extends Seeder
{
    private const FOOD_KIND = 'menu_food';

    public function run(): void
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', 'menu')->where('is_active', 1)->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('  ! خدمة المنيو غير مفعّلة.');

            return;
        }

        $writer = app(ChildServiceWriter::class);
        $pairs = require __DIR__ . '/data/snack_menu_children.php';

        $added = 0;
        $already = 0;

        foreach ($pairs as $pair) {
            [$rootId, $childId] = array_map('intval', explode(':', (string) $pair) + [1 => 0]);

            if ($rootId <= 0 || $childId <= 0) {
                continue;
            }

            $live = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

            if ($live) {
                $already++;

                continue;
            }

            /*
             * `config_source` لا يُختم باسمٍ لا يملك الإعداد كلَّه — القاعدة
             * التى كلّفت ١٩٤ صفًّا نسبتَها. هذا الإعداد جديدٌ بالكامل، فالختم
             * صادق.
             */
            $writer->enable(
                rootId: $rootId,
                childId: $childId,
                serviceId: $serviceId,
                configPatch: [
                    'has_variants' => false,
                    'has_addons' => true,
                    'supports_notes' => true,
                    'supports_stock' => false,
                    'allowed_item_types' => [self::FOOD_KIND],
                ],
                source: 'snack_menu'
            );

            $added++;
        }

        $this->command?->info('Snack menu:');
        $this->command?->line("  - منيو طعامٍ أُضيف : {$added}");
        $this->command?->line("  - كان مفعَّلًا     : {$already}");
    }
}
