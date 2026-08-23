<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «كرڤان» #47 could take no money, because its links and its configs were
 * standing under different roots.
 *
 *     php artisan db:seed --class=CaravanServiceWiringSeeder
 *
 * The child hangs from شركات #22 and مصانع #23. Its three service configs were
 * written under 22 on 2026-05-29 and its three links with them, in the same
 * second. On 2026-08-20 at 18:11:33 all four of its link rows were rewritten at
 * once and the three came out pointing at 23 — a root switch that moved the
 * link and left the config where it was.
 *
 * What that costs is not subtle. Under شركات the child had a delivery
 * configuration nothing could reach, and under مصانع it advertised delivery,
 * menu and booking with no configuration bounding them — which every reader
 * takes as «every item type on the platform». `ServiceWiringIntegrityTest` and
 * `ServiceKindsPruneTest` both call it, from opposite ends.
 *
 * ── What this writes ────────────────────────────────────────────────────────
 *
 * The pair, under BOTH roots, for every service the child already offers under
 * either. A child standing in two storefronts sells the same thing in both —
 * «مواد تعبئة وتغليف» #204 is the sibling in exactly the same two roots and is
 * wired that way — and the config JSON is copied from whichever root already
 * has it rather than invented here.
 *
 * Idempotent, and it never invents a service: a caravan maker gets no `retail`
 * out of this, because nobody has said it should have one.
 */
class CaravanServiceWiringSeeder extends Seeder
{
    private const CHILD = 47;          // كرڤان

    private const ROOTS = [22, 23];    // شركات، مصانع

    public function run(): void
    {
        DB::transaction(function () {
            $links = DB::table('category_platform_services')->where('child_id', self::CHILD)->get();
            $configs = DB::table('category_service_configs')->where('child_id', self::CHILD)->get();

            $serviceIds = $links->pluck('platform_service_id')
                ->merge($configs->pluck('platform_service_id'))
                ->map(fn ($id) => (int) $id)->unique()->values();

            if ($serviceIds->isEmpty()) {
                $this->command?->warn('  ! «كرڤان» يقدّم لا شيء — لا شيء ليُصلح.');

                return;
            }

            $this->command?->info('Caravan service wiring:');

            foreach ($serviceIds as $serviceId) {
                $config = $configs->firstWhere('platform_service_id', $serviceId);
                $link = $links->firstWhere('platform_service_id', $serviceId);

                if (! $config) {
                    // A link with no config anywhere is the half that says
                    // «everything». Nothing here can guess what it meant, so it
                    // is switched off rather than left reading as a grant.
                    DB::table('category_platform_services')
                        ->where('child_id', self::CHILD)->where('platform_service_id', $serviceId)
                        ->update(['is_active' => 0, 'updated_at' => now()]);

                    $this->command?->line("  ! خدمة #{$serviceId} بلا إعداد في أي جذر — أُطفئت.");

                    continue;
                }

                // The config is the statement of intent; the link only says
                // where it can be reached from. So the config's own switch is
                // what both roots take.
                $active = (int) $config->is_active;

                foreach (self::ROOTS as $rootId) {
                    $this->ensureConfig($rootId, $serviceId, $config, $active);
                    $this->ensureLink($rootId, $serviceId, $link, $active);
                }

                $name = DB::table('platform_services')->where('id', $serviceId)->value('key');

                $this->command?->line("  - {$name} : الرابط والإعداد على 22 و23 (active={$active})");
            }

            // Anything still pointing at a root the child does not stand under.
            $stray = DB::table('category_platform_services')
                ->where('child_id', self::CHILD)->whereNotIn('category_id', self::ROOTS)->delete()
                + DB::table('category_service_configs')
                    ->where('child_id', self::CHILD)->whereNotIn('category_id', self::ROOTS)->delete();

            if ($stray > 0) {
                $this->command?->line("  - صفوف تحت جذر لا يقف تحته : {$stray} حُذفت");
            }
        });
    }

    private function ensureConfig(int $rootId, int $serviceId, object $from, int $active): void
    {
        $existing = DB::table('category_service_configs')
            ->where(['category_id' => $rootId, 'child_id' => self::CHILD, 'platform_service_id' => $serviceId])
            ->first();

        if ($existing) {
            if ((int) $existing->is_active !== $active) {
                DB::table('category_service_configs')->where('id', $existing->id)
                    ->update(['is_active' => $active, 'updated_at' => now()]);
            }

            return;
        }

        DB::table('category_service_configs')->insert([
            'category_id' => $rootId,
            'child_id' => self::CHILD,
            'platform_service_id' => $serviceId,
            'config' => $from->config,
            'is_active' => $active,
            'sort_order' => $from->sort_order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureLink(int $rootId, int $serviceId, ?object $from, int $active): void
    {
        $existing = DB::table('category_platform_services')
            ->where(['category_id' => $rootId, 'child_id' => self::CHILD, 'platform_service_id' => $serviceId])
            ->first();

        if ($existing) {
            if ((int) $existing->is_active !== $active) {
                DB::table('category_platform_services')->where('id', $existing->id)
                    ->update(['is_active' => $active, 'updated_at' => now()]);
            }

            return;
        }

        DB::table('category_platform_services')->insert([
            'category_id' => $rootId,
            'child_id' => self::CHILD,
            'platform_service_id' => $serviceId,
            'is_active' => $active,
            'sort_order' => $from->sort_order ?? 0,
            'meta' => $from->meta ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
