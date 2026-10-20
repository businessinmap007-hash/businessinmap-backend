<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «متابعة المشاريع» for the trades that run a job in phases (owner, 2026-10-20: contractors, finishing and craft trades,
 * furniture and metal workshops and factories, maintenance centres — a car in the shop needs bodywork, mechanics and
 * electrical in order — engineering and decor offices, software, marketing, installers). Before this only «هندسية» had it.
 *
 * Written through {@see ChildServiceWriter} so the link and its config stay together; a pair that does not exist in
 * category_parent_child is skipped, never invented. Additive: nothing is switched off.
 */
class ProjectsServiceDistributionSeeder extends Seeder
{
    /** root id => child ids */
    public const PAIRS = [
        6 => [147, 106, 80, 133, 220, 89, 227, 179, 174, 129, 49, 26, 287, 75, 18, 259, 299, 15, 206],
        10 => [39, 84, 240, 543, 544, 545, 546],
        19 => [123, 78, 70, 11],
        22 => [72, 152, 90, 177, 11, 70, 261, 250],
        23 => [116, 115, 50, 17, 47],
        15 => [233, 67, 254],
        18 => [518],
    ];

    public function run(): void
    {
        $service = PlatformService::query()->where('key', 'projects')->first();

        if (! $service) {
            return;
        }

        $writer = app(ChildServiceWriter::class);

        foreach (self::PAIRS as $rootId => $children) {
            foreach ($children as $childId) {
                $exists = DB::table('category_parent_child')->where('parent_id', $rootId)->where('child_id', $childId)->exists();

                if ($exists) {
                    $writer->enable($rootId, $childId, (int) $service->id, [], null, null, 'projects_distribution');
                }
            }
        }
    }
}
