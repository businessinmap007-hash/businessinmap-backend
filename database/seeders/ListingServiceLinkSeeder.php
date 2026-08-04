<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Wires the LISTING surface to the children that sell by listing a thing.
 *
 *   php artisan db:seed --class=ListingServiceLinkSeeder
 *
 * `menu_items` — a row with a name, description, images and a price — is how an
 * estate agent puts up «شقة — غرفتين — سوبر لوكس» and a showroom «غرفة نوم —
 * مودرن». But the `menu` service that owns it reached 19 children, all of them
 * restaurants and groceries, and every one of its item types was a food branch.
 * The listings worked only because nothing gates menu_items on the service: no
 * config said the child could make them, and nothing reading the catalogue to
 * decide what a business may sell saw them at all.
 *
 * Two tables, as always — see the landmine in ChildOptionRedistributionTest:
 * `category_service_configs` says what MAY be listed, and
 * `category_platform_services` is what the owner panel and discovery actually
 * read. A config without its link is unreachable, silently.
 *
 * Idempotent, and it MERGES into any stored config rather than replacing it, so
 * a child that already has menu settings keeps them.
 */
class ListingServiceLinkSeeder extends Seeder
{
    public function run(): void
    {
        $map = require database_path('seeders/data/listing_service_children.php');

        $serviceId = (int) DB::table('platform_services')
            ->where('key', PlatformService::KEY_MENU)
            ->value('id');

        if (! $serviceId) {
            $this->command?->warn('  ! خدمة «القائمة» غير موجودة — لم يُربط شيء.');

            return;
        }

        DB::transaction(function () use ($map, $serviceId) {
            $branchId = $this->branch($serviceId, $map['branch']);

            $types = 0;
            $configs = 0;
            $links = 0;

            foreach ($map['types'] as $key => $spec) {
                $typeId = $this->itemType($serviceId, $key, $spec, $types);
                $this->fileUnderBranch($branchId, $typeId);

                foreach ($spec['children'] as $childId) {
                    foreach ($this->rootsOf((int) $childId) as $rootId) {
                        $configs += $this->writeConfig($rootId, (int) $childId, $serviceId, $key, $branchId);
                        $links += $this->link($rootId, (int) $childId, $serviceId);
                    }
                }
            }

            $this->command?->info('Listing service:');
            $this->command?->line("  - أنواع عناصر جديدة : {$types}");
            $this->command?->line("  - إعدادات كُتبت : {$configs}");
            $this->command?->line("  - روابط إتاحة : {$links}");
            $this->command?->line(
                '  - أبناء القائمة الآن : ' .
                DB::table('category_platform_services')
                    ->where('platform_service_id', $serviceId)->where('is_active', 1)
                    ->distinct()->count('child_id')
            );
        });
    }

    /** A branch of its own: these are not food, and must not sit among it. */
    private function branch(int $serviceId, array $spec): int
    {
        $id = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', $spec['key'])
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('platform_service_item_groups')->insertGetId([
            'platform_service_id' => $serviceId,
            'key' => $spec['key'],
            'name_ar' => $spec['name_ar'],
            'name_en' => $spec['name_en'],
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function itemType(int $serviceId, string $key, array $spec, int &$created): int
    {
        $id = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('key', $key)
            ->value('id');

        if ($id) {
            DB::table('platform_service_item_types')->where('id', $id)
                ->update(['is_active' => 1, 'updated_at' => now()]);

            return (int) $id;
        }

        $created++;

        return (int) DB::table('platform_service_item_types')->insertGetId([
            'platform_service_id' => $serviceId,
            'key' => $key,
            'name_ar' => $spec['name_ar'],
            'name_en' => $spec['name_en'],
            'is_default' => 0,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fileUnderBranch(int $branchId, int $typeId): void
    {
        DB::table('platform_service_item_group_type')->insertOrIgnore([
            'group_id' => $branchId,
            'item_type_id' => $typeId,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function rootsOf(int $childId)
    {
        return DB::table('category_parent_child')
            ->where('child_id', $childId)
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Merge, never replace: a child may already carry menu settings this map
     * knows nothing about, and overwriting them is how a service config gets
     * quietly emptied.
     */
    private function writeConfig(int $rootId, int $childId, int $serviceId, string $typeKey, int $branchId): int
    {
        $row = DB::table('category_service_configs')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->first(['id', 'config']);

        $stored = $row ? (json_decode($row->config ?: '{}', true) ?: []) : [];

        $allowed = collect($stored['allowed_item_types'] ?? [])->push($typeKey)->unique()->values()->all();
        $groups = collect($stored['item_groups'] ?? [])->push($branchId)->unique()->values()->all();

        $config = json_encode(
            array_merge($stored, ['allowed_item_types' => $allowed, 'item_groups' => $groups]),
            JSON_UNESCAPED_UNICODE
        );

        if ($row) {
            DB::table('category_service_configs')->where('id', $row->id)
                ->update(['config' => $config, 'is_active' => 1, 'updated_at' => now()]);

            return 1;
        }

        DB::table('category_service_configs')->insert([
            'category_id' => $rootId,
            'child_id' => $childId,
            'platform_service_id' => $serviceId,
            'config' => $config,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }

    /** The half that decides whether the merchant is offered the service at all. */
    private function link(int $rootId, int $childId, int $serviceId): int
    {
        $existing = DB::table('category_platform_services')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->value('id');

        if ($existing) {
            DB::table('category_platform_services')->where('id', $existing)
                ->update(['is_active' => 1, 'updated_at' => now()]);

            return 1;
        }

        DB::table('category_platform_services')->insert([
            'category_id' => $rootId,
            'child_id' => $childId,
            'platform_service_id' => $serviceId,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }
}
