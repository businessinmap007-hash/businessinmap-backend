<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Widens «فنادق سياحية» to «سياحة وفنادق» and gives it the owner who lets one
 * chalet.
 *
 *     php artisan db:seed --class=TourismRootSeeder
 *
 * See data/tourism_root.php for the reasoning — including why «مدن سياحية» is
 * NOT a child and why the slug does not move.
 *
 * The child is shaped from a named sibling rather than invented: its service
 * link and config are copied from «شقق فندقية», so it takes bookings the same
 * way on the day it is created instead of arriving with a link and no config,
 * which reads as «every item type on the platform».
 *
 * Its vocabulary is named row by row and consults the withdrawal ledger, so a
 * re-run after the owner has curated it hands nothing back.
 *
 * Idempotent: a second run renames nothing, creates nothing and links nothing.
 */
class TourismRootSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/tourism_root.php';

        DB::transaction(function () use ($data) {
            $rootId = (int) DB::table('categories')
                ->where('slug', $data['root_slug'])->where('parent_id', 0)->value('id');

            if ($rootId <= 0) {
                $this->command?->warn("  ! الجذر «{$data['root_slug']}» غير موجود.");

                return;
            }

            $this->command?->info('Tourism root:');

            $this->rename($rootId, $data['rename_to']);

            $childId = $this->ensureChild($rootId, $data['child']);

            if ($childId > 0) {
                $this->grantVocabulary($childId, $data['vocabulary']);
            }
        });
    }

    /** @param array{ar:string,en:string} $to */
    private function rename(int $rootId, array $to): void
    {
        $row = DB::table('categories')->where('id', $rootId)->first(['name_ar', 'name_en']);

        if ((string) $row->name_ar === $to['ar'] && (string) $row->name_en === $to['en']) {
            $this->command?->line("  · الجذر «{$to['ar']}» بالفعل.");

            return;
        }

        DB::table('categories')->where('id', $rootId)
            ->update(['name_ar' => $to['ar'], 'name_en' => $to['en'], 'updated_at' => now()]);

        $this->command?->line("  - «{$row->name_ar}» → «{$to['ar']}»");
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return int the child id, or 0 when it could not be shaped
     */
    private function ensureChild(int $rootId, array $spec): int
    {
        $name = (string) $spec['name_ar'];

        $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if ($childId <= 0) {
            $childId = (int) DB::table('category_children_master')->insertGetId([
                'name_ar' => $name,
                'name_en' => (string) $spec['name_en'],
                'reorder' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command?->line("  - «{$name}» #{$childId} أُنشئ");
        }

        $attached = DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', $childId)->exists();

        if (! $attached) {
            DB::table('category_parent_child')->insert([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->shapeServices($rootId, $childId, (string) $spec['shaped_from'], $name);

        return $childId;
    }

    /**
     * Every service the named sibling offers under this root, offered here on
     * the same terms.
     *
     * Copied and not invented. A child created with a link and an empty config
     * is a child allowed to list anything, and nothing downstream can tell that
     * from a decision.
     */
    private function shapeServices(int $rootId, int $childId, string $siblingName, string $name): void
    {
        $siblingId = (int) DB::table('category_children_master')->where('name_ar', $siblingName)->value('id');

        if ($siblingId <= 0) {
            $this->command?->warn("  ! الشبيه «{$siblingName}» غير موجود — «{$name}» بلا خدمات.");

            return;
        }

        $writer = app(ChildServiceWriter::class);

        $offered = DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $siblingId)->where('is_active', 1)
            ->get(['platform_service_id', 'config']);

        foreach ($offered as $row) {
            $serviceId = (int) $row->platform_service_id;

            $already = DB::table('category_service_configs')
                ->where(['category_id' => $rootId, 'child_id' => $childId, 'platform_service_id' => $serviceId])
                ->exists();

            if ($already) {
                continue;
            }

            $writer->enable(
                rootId: $rootId,
                childId: $childId,
                serviceId: $serviceId,
                configPatch: json_decode((string) $row->config, true) ?: [],
                source: 'tourism_root'
            );

            $key = DB::table('platform_services')->where('id', $serviceId)->value('key');

            $this->command?->line("  - «{$name}» ← {$key} (من «{$siblingName}»)");
        }
    }

    /**
     * @param  array<string,array<int,string>|string>  $vocabulary
     */
    private function grantVocabulary(int $childId, array $vocabulary): void
    {
        $decisions = app(ChildOptionDecisions::class);
        $granted = $refused = 0;

        foreach ($vocabulary as $group => $rows) {
            $options = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', $group)
                ->when($rows !== 'all', fn ($q) => $q->whereIn('o.name_ar', (array) $rows))
                ->pluck('o.id')->map(fn ($id) => (int) $id)->all();

            if ($options === []) {
                $this->command?->warn("  ! «{$group}» لا يحمل شيئًا مما طُلب.");

                continue;
            }

            // Shared (`category_id = 0`): the child stands under one root, and
            // a scoped row there is noise.
            $allowed = $decisions->filter($childId, ChildOptionDecisions::ALL_ROOTS, $options);

            $refused += count($options) - count($allowed);

            foreach ($allowed as $optionId) {
                $granted += DB::table('category_child_option')->insertOrIgnore([
                    'child_id' => $childId,
                    'category_id' => 0,
                    'option_id' => $optionId,
                    'reorder' => 0,
                ]);
            }
        }

        $this->command?->line("  - خيارات مُنحت : {$granted} · رفضها سجل السحب : {$refused}");
    }
}
