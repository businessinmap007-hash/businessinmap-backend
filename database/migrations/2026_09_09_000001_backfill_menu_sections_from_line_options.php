<?php

use App\Models\MenuItem;
use App\Services\Menu\MenuSectionFromOptionGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * `applyVocabulary()` on BusinessMenuItemController::store()/update() has
 * always grown a `menu_sections` row from a picked line option's group when
 * the merchant didn't hand-pick one ({@see MenuSectionFromOptionGroup}) —
 * but that only runs on a SAVE through the API. An item inserted directly
 * (a seed, a factory, a data import) carries its line option but never
 * passed through that path, so it sits with `menu_section_id` NULL forever:
 * "hhh" (vegetables/fruit) had two such items, and its real
 * `menu_sections` table stayed empty despite "الخضروات"/"الفواكه" already
 * showing as headings everywhere the app groups by the vocabulary directly
 * — the merchant's own "Menu sections" screen (which lists real rows, not
 * a live grouping) had nothing to show, and its hand-picked section filter
 * chips had nothing to filter by.
 *
 * One-time backfill: same resolver, same rule (skip anything that already
 * has a section), run once over what already exists. Nothing to undo — the
 * grown sections are indistinguishable from ones a live save would have
 * made, exactly like `down()` on the migration that first added the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        $resolver = app(MenuSectionFromOptionGroup::class);

        MenuItem::query()
            ->whereNull('menu_section_id')
            ->whereHas('offeringOptions', fn ($q) => $q->where('role', 'line'))
            ->with('offeringOptions.option.group')
            ->chunkById(200, function ($items) use ($resolver) {
                foreach ($items as $item) {
                    $lineOption = $item->lineOption();

                    if (! $lineOption) {
                        continue;
                    }

                    $section = $resolver->resolve((int) $item->business_id, $lineOption);

                    if ($section) {
                        $item->forceFill(['menu_section_id' => $section->id])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo — see the class doc.
    }
};
