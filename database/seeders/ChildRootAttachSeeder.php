<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives a child a second storefront.
 *
 *     php artisan db:seed --class=ChildRootAttachSeeder
 *
 * See data/child_root_attachments.php for the list and why each one is needed.
 * The short version: a fold cannot cross a root. ChildRootDetachSeeder moves
 * merchants onto the keeper without touching their `category_id`, so the keeper
 * must already stand where they stand or they land somewhere their root cannot
 * see.
 *
 * What is written:
 *
 *   1. the `category_parent_child` row, and only if it is missing;
 *   2. the service wiring, mirrored from a root the child already stands under
 *      — link and config together, through ChildServiceWriter, with the config
 *      COPIED. A new root with a link and no config offers a service bounded by
 *      nothing, which every reader takes as «every item type there is».
 *
 * Options are deliberately not copied. A shared row (`category_id = 0`) already
 * reaches the new root, and a root-scoped row was scoped because that root asks
 * something different.
 *
 * MUST run after ChildRenameSeeder (entries name the keeper by its new name)
 * and before ChildRootDetachSeeder.
 *
 * Idempotent: a second run attaches nothing and mirrors nothing.
 */
class ChildRootAttachSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/child_root_attachments.php';

        $this->command?->info('Child root attachments:');

        DB::transaction(function () use ($data) {
            foreach ($data as $entry) {
                $this->apply($entry);
            }
        });
    }

    /** @param array<string,mixed> $entry */
    private function apply(array $entry): void
    {
        $name = (string) $entry['child_name_ar'];
        $slug = (string) $entry['root_slug'];

        $rootId = (int) DB::table('categories')->where('slug', $slug)->where('parent_id', 0)->value('id');
        $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if ($rootId <= 0 || $childId <= 0) {
            $this->command?->warn("  ! «{$name}» أو «{$slug}» غير موجود — يُتخطّى.");

            return;
        }

        $already = DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', $childId)->exists();

        if ($already) {
            $this->command?->line("  · «{$name}» يقف تحت «{$slug}» بالفعل.");

            return;
        }

        DB::table('category_parent_child')->insert([
            'parent_id' => $rootId,
            'child_id' => $childId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mirrored = ($entry['mirror_services'] ?? true) ? $this->mirrorServices($rootId, $childId) : 0;

        $this->command?->line("  - «{$name}» → «{$slug}» : أُضيف · خدمات نُسخت {$mirrored}");
    }

    /** Every service the child offers elsewhere, offered here on the same terms. */
    private function mirrorServices(int $rootId, int $childId): int
    {
        $writer = app(ChildServiceWriter::class);

        $elsewhere = DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('category_id', '!=', $rootId)
            ->where('is_active', 1)
            ->distinct()
            ->pluck('platform_service_id');

        foreach ($elsewhere as $serviceId) {
            $writer->enable(
                rootId: $rootId,
                childId: $childId,
                serviceId: (int) $serviceId,
                configPatch: $writer->configElsewhere($childId, (int) $serviceId, $rootId),
                source: 'child_root_attach'
            );
        }

        return $elsewhere->count();
    }
}
