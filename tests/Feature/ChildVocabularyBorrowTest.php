<?php

namespace Tests\Feature;

use Database\Seeders\ChildVocabularyBorrowSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «لدينا مجموعات كثيرة من الخيارات يمكن ان تعمل تحت اكثر من ابن بدون التكرار
 *  او التداخل» — approved 2026-08-12, sixteen children, six groups.
 *
 * A child could say what it DOES and not what it is made of, and what it is
 * made of is what sets the price: a furniture workshop named تنجيد and دهان
 * and could not say زان or MDF, though «أخشاب» held that list in full.
 *
 * @see \Database\Seeders\ChildVocabularyBorrowSeeder
 * @see database/seeders/data/child_vocabulary_borrows.php
 */
class ChildVocabularyBorrowTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,array<string,mixed>> */
    private function borrows(): array
    {
        return require database_path('seeders/data/child_vocabulary_borrows.php');
    }

    private function optionsOf(int $childId, string $group): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.name_ar', $group)
            ->distinct()
            ->pluck('o.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    public function test_every_recipient_can_now_say_what_it_is_made_of(): void
    {
        foreach ($this->borrows() as $borrow) {
            $group = (string) $borrow['group'];
            $donor = $this->optionsOf((int) $borrow['from'], $group);

            $this->assertNotEmpty($donor, "the donor of «{$group}» holds nothing to lend");

            foreach ($borrow['to'] as $childId) {
                $this->assertNotEmpty(
                    $this->optionsOf((int) $childId, $group),
                    "#{$childId} was lent «{$group}» and holds none of it"
                );
            }
        }
    }

    /** A borrow lends what the DONOR holds — never more than the donor says. */
    public function test_a_borrow_never_widens_past_the_donor(): void
    {
        foreach ($this->borrows() as $borrow) {
            $group = (string) $borrow['group'];
            $donor = $this->optionsOf((int) $borrow['from'], $group);

            foreach ($borrow['to'] as $childId) {
                $extra = array_diff($this->optionsOf((int) $childId, $group), $donor);

                $this->assertSame(
                    [],
                    array_values($extra),
                    "#{$childId} holds rows of «{$group}» the donor never had"
                );
            }
        }
    }

    /**
     * «بدون التكرار او التداخل».
     *
     * The borrowed group must not repeat a row the recipient could already say
     * in another group. Five candidates were dropped from the approved list for
     * exactly this, and the rule has to keep holding as the lists change.
     */
    public function test_no_borrowed_row_repeats_something_the_child_already_said(): void
    {
        foreach ($this->borrows() as $borrow) {
            $group = (string) $borrow['group'];

            $borrowedNames = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', $group)
                ->pluck('o.name_ar')
                ->map(fn ($n) => trim((string) $n))
                ->all();

            foreach ($borrow['to'] as $childId) {
                $others = DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('co.child_id', (int) $childId)
                    ->where('g.name_ar', '!=', $group)
                    ->pluck('o.name_ar')
                    ->map(fn ($n) => trim((string) $n))
                    ->all();

                $clash = array_intersect($borrowedNames, $others);

                $this->assertSame(
                    [],
                    array_values($clash),
                    "#{$childId} would say " . implode(', ', $clash) . " twice"
                );
            }
        }
    }

    /** Re-running writes nothing: the whole operation is add-only and settled. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')->count();

        (new ChildVocabularyBorrowSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->count());
    }

    /**
     * The rule that makes the whole thing safe to re-run: hand curation beats
     * the file. An add-only seeder blind to the withdrawal record restores
     * everything the owner unticked, one run later.
     */
    public function test_a_withdrawal_stops_the_borrow(): void
    {
        $borrow = $this->borrows()[0];
        $group = (string) $borrow['group'];
        $childId = (int) $borrow['to'][0];

        $optionId = $this->optionsOf($childId, $group)[0];

        // Untick it the way the bulk screen does: remove the link and record why.
        DB::table('category_child_option')
            ->where('child_id', $childId)->where('option_id', $optionId)->delete();

        DB::table('category_child_option_decisions')->insert([
            'child_id' => $childId,
            'option_id' => $optionId,
            'category_id' => 0,
            'kind' => 'withdrawn',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ChildVocabularyBorrowSeeder)->run();

        $this->assertFalse(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            'the seeder handed back an option the owner had taken away'
        );
    }

    /** Borrow, never clone: not one option or group is created by this. */
    public function test_the_borrow_creates_no_option_and_no_group(): void
    {
        $options = DB::table('options')->count();
        $groups = DB::table('option_groups')->count();

        (new ChildVocabularyBorrowSeeder)->run();

        $this->assertSame($options, DB::table('options')->count());
        $this->assertSame($groups, DB::table('option_groups')->count());
    }
}
