<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Switches a service back on where it was off by accident.
 *
 *     php artisan db:seed --class=ServiceReinstatementSeeder
 *
 * See data/service_reinstatements.php. Each row is a named finding, not a rule:
 * this seeder walks a list somebody read and agreed with, and it will never turn
 * a service on because a heuristic liked the look of it. An admin who switched
 * something off deliberately keeps that decision unless his child is on the list.
 *
 * The config is copied from a named sibling under the SAME root that already
 * offers the service, so a reinstated service arrives with the shape its
 * neighbours use rather than a default nobody chose.
 */
class ServiceReinstatementSeeder extends Seeder
{
    public function run(): void
    {
        $rows = require __DIR__ . '/data/service_reinstatements.php';

        DB::transaction(function () use ($rows) {
            $writer = app(ChildServiceWriter::class);
            $done = 0;

            $this->command?->info('Service reinstatements:');

            foreach ($rows as $row) {
                $childId = (int) DB::table('category_children_master')->where('name_ar', $row['child_name_ar'])->value('id');
                $donorId = (int) DB::table('category_children_master')->where('name_ar', $row['copy_from_child_ar'])->value('id');
                $rootId = (int) DB::table('categories')->where('slug', $row['root_slug'])->value('id');
                $serviceId = (int) DB::table('platform_services')->where('key', $row['service_key'])->value('id');

                if ($childId <= 0 || $donorId <= 0 || $rootId <= 0 || $serviceId <= 0) {
                    $this->command?->warn("  ! «{$row['child_name_ar']}»: مفقود أحد الأطراف — تُخطّي.");

                    continue;
                }

                $live = DB::table('category_platform_services')
                    ->where('category_id', $rootId)->where('child_id', $childId)
                    ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

                if ($live) {
                    $this->command?->line("  - «{$row['child_name_ar']}» · {$row['service_key']} — مفعّل بالفعل.");

                    continue;
                }

                $config = json_decode((string) DB::table('category_service_configs')
                    ->where('category_id', $rootId)->where('child_id', $donorId)
                    ->where('platform_service_id', $serviceId)->value('config'), true);

                if (! $config) {
                    $this->command?->warn("  ! «{$row['copy_from_child_ar']}» لا يحمل «{$row['service_key']}» تحت هذا الجذر — تُخطّي.");

                    continue;
                }

                $writer->enable($rootId, $childId, $serviceId, $config, null, null, 'service-reinstatement');
                $done++;

                $this->command?->line("  - «{$row['child_name_ar']}» · {$row['service_key']} — أُعيد تفعيله (نسخة من «{$row['copy_from_child_ar']}»)");
                $this->command?->line("      السبب : {$row['why']}");
            }

            $this->command?->line("  الإجمالي : {$done}");
        });
    }
}
