<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-points price rows still addressed to a child that belongs to no root.
 *
 *   php artisan db:seed --class=StrandedPriceChildSeeder
 *
 * Detaching the «⭐» children moved فندق الاندلس (#212) onto «فندق», but its
 * nine `business_service_prices` rows kept `child_id = 1`. Discovery matches a
 * business by BOTH its own classification and a live price row for that same
 * child, so the hotel's entire price list — 500 to 5,000 — was unreachable:
 * present in the database, invisible to every customer.
 *
 * The rule is narrow on purpose: a row is only moved when its child sits under
 * no root at all AND the business that owns it now sits under a different one.
 * Where the business itself is unclassified there is nothing to move it to, so
 * the row is reported and left alone.
 */
class StrandedPriceChildSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $linked = DB::table('category_parent_child')->distinct()->pluck('child_id');

            $stranded = DB::table('business_service_prices as p')
                ->join('users as u', 'u.id', '=', 'p.business_id')
                ->whereNotIn('p.child_id', $linked)
                ->get(['p.id', 'p.business_id', 'p.child_id', 'u.category_child_id', 'u.name']);

            $moved = 0;
            $left = [];

            foreach ($stranded as $row) {
                if (! $row->category_child_id || ! $linked->contains($row->category_child_id)) {
                    $left[] = "#{$row->business_id} " . trim((string) $row->name);
                    continue;
                }

                DB::table('business_service_prices')->where('id', $row->id)->update([
                    'child_id' => $row->category_child_id,
                    'updated_at' => now(),
                ]);

                $moved++;
            }

            $this->command?->info('Stranded price rows:');
            $this->command?->line("  - أُعيد توجيهها إلى تصنيف صاحبها : {$moved}");

            foreach (array_unique($left) as $name) {
                $this->command?->warn("  ! {$name} نفسه غير مصنّف — تُركت أسعاره كما هي.");
            }
        });
    }
}
