<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «أدوات صحية» #138 becomes «سيراميك وأدوات صحية», and stands where it is
 * actually shopped for.
 *
 *     php artisan db:seed --class=CeramicsAndSanitaryWareSeeder
 *
 * Two findings, one child.
 *
 * **There was no ceramics trade on the platform at all.** «سيراميك وبورسلين»
 * existed as a row inside «أعمال الأرضيات», which is a flooring contractor's
 * job list, and «بورسلين» inside «الصيني والخزف», which is dinner plates.
 * Neither is the building material, and it is one of the largest trades in
 * Egypt.
 *
 * **And the sanitary ware could not be found.** #138 stood under «شركات» and
 * «مصانع» only — the wholesale and manufacturing ends — so a customer looking
 * for a bathroom shop under «معارض» or «المحلات» found nothing, which is where
 * he would actually look.
 *
 * ## One child, two vocabularies
 *
 * The same rule that merged «صرافة نقود» with «تحويل أموال» on the same day. A
 * معرض سيراميك وأدوات صحية is ONE shop, and Cleopatra and Lecico make both —
 * but a business carries one `category_child_id`, so with two children the
 * common merchant picks one and disappears from the other search. With one
 * child and two `line` groups all three cases work: a tile showroom ticks
 * «أنواع السيراميك والبورسلين», a plumbing supplier ticks «الأدوات الصحية», and
 * the company selling both ticks both. The vocabulary itself lives in
 * data/factory_child_vocabularies.php, where its neighbour already did.
 *
 * ## The wiring is copied, never guessed
 *
 * A child ADDED to a root arrives with no services, which makes it visible and
 * unsellable — the mirror image of the debris a detachment leaves. The shape
 * comes from a named DONOR standing under the same roots rather than from
 * ChildRootMovesSeeder's majority guess, because a majority moves and this is a
 * statement. «صينى وخزف» #228 is the donor and the fit is exact: it already
 * stands under all four of these roots, it is the same kind of trade — a
 * materials shop that is also a factory and a showroom — and under «شركات» and
 * «مصانع», where BOTH children already stand, the two carry identical service
 * sets today. Copying the two roots it has and #138 does not is therefore
 * continuing a shape, not inventing one.
 *
 * Idempotent: a second run renames nothing, adds no root and enables no
 * service.
 */
class CeramicsAndSanitaryWareSeeder extends Seeder
{
    private const CHILD_ID = 138;

    private const OLD_NAME = 'أدوات صحية';

    private const NEW_NAME = 'سيراميك وأدوات صحية';

    private const NEW_NAME_EN = 'Ceramics & Sanitary Ware';

    /** The child whose shape under the new roots is copied. */
    private const DONOR_ID = 228;   // صينى وخزف

    private const ADD_ROOTS = ['exhibitions', 'shops-online'];

    public function run(): void
    {
        DB::transaction(function () {
            $this->command?->info('Ceramics & sanitary ware:');

            $this->rename();

            foreach (self::ADD_ROOTS as $slug) {
                $this->stand($slug);
            }
        });
    }

    private function rename(): void
    {
        $current = (string) DB::table('category_children_master')
            ->where('id', self::CHILD_ID)->value('name_ar');

        if ($current === self::NEW_NAME) {
            $this->command?->line('  - الاسم بالفعل «' . self::NEW_NAME . '».');

            return;
        }

        if ($current !== self::OLD_NAME) {
            // Renaming something that is no longer what this file described is
            // how a by-name map ends up pointing at a trade nobody meant.
            $this->command?->warn("  ! #" . self::CHILD_ID . " اسمه «{$current}» لا «" . self::OLD_NAME . "» — لم يُعد التسمية.");

            return;
        }

        DB::table('category_children_master')->where('id', self::CHILD_ID)->update([
            'name_ar' => self::NEW_NAME,
            'name_en' => self::NEW_NAME_EN,
            'updated_at' => now(),
        ]);

        $this->command?->line('  - «' . self::OLD_NAME . '» ← «' . self::NEW_NAME . '»');
    }

    private function stand(string $slug): void
    {
        $rootId = (int) DB::table('categories')->where('slug', $slug)->value('id');

        if ($rootId <= 0) {
            $this->command?->warn("  ! الجذر «{$slug}» غير موجود — تُخطّى.");

            return;
        }

        $already = DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', self::CHILD_ID)->exists();

        if (! $already) {
            DB::table('category_parent_child')->insert([
                'parent_id' => $rootId,
                'child_id' => self::CHILD_ID,
                'updated_at' => now(),
            ]);
        }

        $enabled = $this->copyDonorShape($rootId);

        $this->command?->line("  - «{$slug}» : " . ($already ? 'قائم' : 'أُضيف') . " · خدمات مفعّلة {$enabled}");
    }

    /**
     * Enable under this root exactly the services the donor runs under it,
     * with the donor's own config.
     *
     * Only ADDS. A service already live on the child is left with whatever
     * config it has — the donor gives a starting shape, and after that the
     * child's own configuration is its own.
     */
    private function copyDonorShape(int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);
        $enabled = 0;

        $donorServices = DB::table('category_platform_services')
            ->where('category_id', $rootId)->where('child_id', self::DONOR_ID)
            ->where('is_active', 1)->pluck('platform_service_id');

        foreach ($donorServices as $serviceId) {
            $live = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', self::CHILD_ID)
                ->where('platform_service_id', (int) $serviceId)->where('is_active', 1)->exists();

            if ($live) {
                continue;
            }

            $config = json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', self::DONOR_ID)
                ->where('platform_service_id', (int) $serviceId)->value('config'), true) ?: [];

            $writer->enable($rootId, self::CHILD_ID, (int) $serviceId, $config, null, null, 'ceramics-sanitary');
            $enabled++;
        }

        return $enabled;
    }
}
