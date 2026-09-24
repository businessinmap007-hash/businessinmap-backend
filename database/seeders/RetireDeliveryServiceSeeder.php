<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «نلغى خدمة التوصيل ونحن لدينا مجموعة خيارات استلام وتسليم يمكن تعيين لها
 * سائقي المتجر او طلب مندوب خارجى» — المالك، 2026-09-24.
 *
 * A business that sells a product already carries the pickup/delivery option
 * group tied to its menu or catalog, and it shows in the cart; delivery as a
 * service of its own says nothing more. So the service is switched off
 * everywhere EXCEPT under «شحن وتوصيل» — the carriers, for whom delivery is
 * the business itself.
 *
 * Switched off, never deleted (ChildServiceWriter::disable keeps the config),
 * and idempotent. It runs last so the older delivery seeders cannot bring it
 * back on a full re-seed.
 */
class RetireDeliveryServiceSeeder extends Seeder
{
    private const CARRIERS_ROOT_SLUG = 'shipping-delivery';

    public function run(): void
    {
        $service = (int) DB::table('platform_services')->where('key', 'delivery')->value('id');
        $carriers = (int) DB::table('categories')->where('slug', self::CARRIERS_ROOT_SLUG)->value('id');

        if ($service <= 0) {
            return;
        }

        $writer = app(ChildServiceWriter::class);

        $rows = DB::table('category_platform_services')
            ->where('platform_service_id', $service)->where('is_active', 1)
            ->when($carriers > 0, fn ($q) => $q->where('category_id', '!=', $carriers))
            ->get(['category_id', 'child_id']);

        DB::transaction(function () use ($rows, $writer, $service) {
            foreach ($rows as $row) {
                $writer->disable((int) $row->category_id, (int) $row->child_id, $service);
            }
        });

        $this->command?->info('Delivery service retired:');
        $this->command?->line('  - روابط أُوقفت : ' . $rows->count());
    }
}
