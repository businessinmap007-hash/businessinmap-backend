<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-files a child under the root a customer would actually look in.
 *
 *     php artisan db:seed --class=ChildRootMovesSeeder
 *
 * See data/child_root_moves.php for what moves and why. This is deliberately
 * NOT a remodel: nothing is retired, nothing is merged, no vocabulary changes.
 * The child keeps its options, its services and its accounts — only the root it
 * hangs from changes.
 *
 * The wiring is MOVED, not re-created. `category_platform_services`,
 * `category_service_configs` and `category_child_service_fees` are all keyed on
 * (root, child, service), so each row simply changes root — which keeps the
 * stored config and its `config_source` intact. Re-creating them through the
 * writer would have stamped every one with this seeder's name and thrown away
 * whichever admin screen had last touched it.
 *
 * **Anything scoped by ROOT must run after this.** A move changes who belongs to
 * a root, so a seeder that derives its set from root membership is stale the
 * moment a child arrives or leaves. `PrepaymentScopeSeeder` is exactly that —
 * «دفع مسبق» belongs to the children of `shipping-delivery`, whoever they are —
 * and moving «عفشجى» in turned its test red until it was re-run. The order in
 * DatabaseSeeder already has this seeder first; keep it that way.
 *
 * Idempotent: once the child hangs from the target root, a re-run does nothing.
 */
class ChildRootMovesSeeder extends Seeder
{
    /** Every table keyed on (category_id, child_id, platform_service_id). */
    private const WIRING = [
        'category_platform_services',
        'category_service_configs',
        'category_child_service_fees',
    ];

    public function run(): void
    {
        $moves = require __DIR__ . '/data/child_root_moves.php';

        DB::transaction(function () use ($moves) {
            $this->command?->info('Child root moves:');

            foreach ($moves as $move) {
                $this->apply($move);
            }
        });
    }

    /** @param array<string,string> $move */
    private function apply(array $move): void
    {
        $name = $move['child_name_ar'];

        $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');
        $from = (int) DB::table('categories')->where('slug', $move['from_root_slug'])->value('id');
        $to = (int) DB::table('categories')->where('slug', $move['to_root_slug'])->value('id');

        if ($childId <= 0 || $from <= 0 || $to <= 0) {
            $this->command?->warn("  ! «{$name}»: الابن أو أحد الجذرين غير موجود — تُخطّي.");

            return;
        }

        $hangsFromSource = DB::table('category_parent_child')
            ->where('parent_id', $from)->where('child_id', $childId)->exists();

        if (! $hangsFromSource) {
            // Already moved. The SHAPE is still checked, because adopting it is
            // a corrective step in its own right — the four moves that needed it
            // were made before the flag existed, and a child left offering its
            // old root's services is exactly the fault the flag was added for.
            $adopted = ! empty($move['adopt_services']) ? $this->adoptRootShape($childId, $to) : 0;

            $this->command?->line("  - «{$name}» ليس تحت «{$move['from_root_slug']}» — لا شيء ليُنقل."
                . ($adopted > 0 ? " (خدمات صُحّحت: {$adopted})" : ''));

            return;
        }

        DB::table('category_parent_child')->updateOrInsert(
            ['parent_id' => $to, 'child_id' => $childId],
            ['updated_at' => now()]
        );

        DB::table('category_parent_child')
            ->where('parent_id', $from)->where('child_id', $childId)->delete();

        $moved = $this->moveWiring($childId, $from, $to);
        $adopted = ! empty($move['adopt_services']) ? $this->adoptRootShape($childId, $to) : 0;

        // Nobody may be left pointing at a root the child no longer hangs from.
        $accounts = DB::table('users')
            ->where('category_child_id', $childId)
            ->where('category_id', $from)
            ->update(['category_id' => $to]);

        $this->command?->line("  - «{$name}» #{$childId} : {$move['from_root_slug']} → {$move['to_root_slug']}");
        $this->command?->line("      صفوف ربط نُقلت : {$moved} · حسابات نُقلت : {$accounts} · خدمات تبنّاها : {$adopted}");
        $this->command?->line("      السبب : {$move['why']}");
    }

    /**
     * Make the child offer what its NEW siblings offer.
     *
     * A move that changes what the business is must change what it sells, and
     * carrying the old root's wiring across is how «مكملات غذائية» landed in
     * المحلات still offering booking and training and unable to list one
     * product. The shape is the commonest service set among the root's other
     * children — the root's own answer, not a guess — and each config is COPIED
     * from a sibling that already has it, so nothing here invents a setting.
     *
     * Services the child keeps from its old life are deactivated, never deleted:
     * the config holds work, and a wrong call here has to be undoable.
     */
    private function adoptRootShape(int $childId, int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);

        $shape = $this->majorityShape($childId, $rootId);

        if ($shape === []) {
            return 0;
        }

        $changed = 0;

        foreach ($shape as $serviceId => $donorChildId) {
            $already = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

            if ($already) {
                continue;
            }

            $config = json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', $donorChildId)
                ->where('platform_service_id', $serviceId)->value('config'), true) ?: [];

            $writer->enable($rootId, $childId, $serviceId, $config, null, null, 'child-root-move');
            $changed++;
        }

        foreach (
            DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)->where('is_active', 1)
                ->pluck('platform_service_id') as $serviceId
        ) {
            if (array_key_exists((int) $serviceId, $shape)) {
                continue;
            }

            $writer->disable($rootId, $childId, (int) $serviceId);
            $changed++;
        }

        return $changed;
    }

    /**
     * service id => a sibling child that carries it, for every service the
     * majority of this root's OTHER children offer.
     *
     * @return array<int,int>
     */
    private function majorityShape(int $childId, int $rootId): array
    {
        $siblings = DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', '!=', $childId)
            ->pluck('child_id')->map(fn ($id) => (int) $id);

        if ($siblings->isEmpty()) {
            return [];
        }

        $carriers = [];

        foreach (
            DB::table('category_platform_services')
                ->where('category_id', $rootId)->whereIn('child_id', $siblings)->where('is_active', 1)
                ->get(['child_id', 'platform_service_id']) as $row
        ) {
            $carriers[(int) $row->platform_service_id][] = (int) $row->child_id;
        }

        $threshold = $siblings->count() / 2;
        $shape = [];

        foreach ($carriers as $serviceId => $children) {
            if (count(array_unique($children)) > $threshold) {
                $shape[$serviceId] = $children[0];
            }
        }

        return $shape;
    }

    private function moveWiring(int $childId, int $from, int $to): int
    {
        $moved = 0;

        foreach (self::WIRING as $table) {
            $rows = DB::table($table)
                ->where('category_id', $from)
                ->where('child_id', $childId)
                ->get(['id', 'platform_service_id']);

            foreach ($rows as $row) {
                // (root, child, service) is UNIQUE on all three tables. If the
                // target root already carries this service for this child, the
                // row standing there wins and the source row goes — moving it
                // would violate the key, and overwriting would discard whatever
                // the target had been configured with.
                $occupied = DB::table($table)
                    ->where('category_id', $to)
                    ->where('child_id', $childId)
                    ->where('platform_service_id', (int) $row->platform_service_id)
                    ->exists();

                if ($occupied) {
                    DB::table($table)->where('id', $row->id)->delete();

                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    'category_id' => $to,
                    'updated_at' => now(),
                ]);

                $moved++;
            }
        }

        return $moved;
    }
}
