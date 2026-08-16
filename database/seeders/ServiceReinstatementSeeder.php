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
                $rootId = (int) DB::table('categories')->where('slug', $row['root_slug'])->value('id');
                $childId = $this->standingChild($row['child_name_ar'], $rootId);
                /*
                 * The donor normally stands beside the recipient — that is what
                 * makes this safe, a shape somebody under that root already
                 * chose. It does not have to: a carrier's schedules shape is a
                 * carrier's wherever the carrier is filed, and «شحن بري وبحري
                 * وجوى» moved to its own root on 2026-08-16. Named explicitly
                 * when it differs, never guessed, because a donor found under
                 * the wrong root is how a child takes somebody else's shelf.
                 */
                $donorRootId = isset($row['copy_from_root_slug'])
                    ? (int) DB::table('categories')->where('slug', $row['copy_from_root_slug'])->value('id')
                    : $rootId;

                $donorId = $this->standingChild($row['copy_from_child_ar'], $donorRootId);
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
                    ->where('category_id', $donorRootId)->where('child_id', $donorId)
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

    /**
     * The child of that name STANDING UNDER THIS ROOT.
     *
     * Not `where(name_ar)->value('id')`, which returns the lowest id: several
     * names have two master rows and the low one is the retired twin. No name on
     * today's list has a twin, so this changes nothing now — it is here because
     * the next row added to the file is one lookup away from silently reinstating
     * a service on a child hanging from no root, and the seeder would report
     * success. Scoping to the root also means a donor is only ever a genuine
     * sibling, which is the whole premise of copying its shape.
     */
    private function standingChild(string $nameAr, int $rootId): int
    {
        if ($rootId <= 0) {
            return 0;
        }

        return (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)
            ->where('c.name_ar', $nameAr)
            ->value('c.id');
    }
}
