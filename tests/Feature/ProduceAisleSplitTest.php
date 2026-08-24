<?php

namespace Tests\Feature;

use Database\Seeders\OptionPriceRolesSeeder;
use Database\Seeders\ProduceAisleSplitSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «فواكة تحتها كل الفواكة» — المالك، 2026-08-24.
 *
 * «أصناف الخضار والفاكهة» was the whole trade in one flat list, which is fine
 * for a picker and wrong for an arrangement: the option GROUP is the section
 * (see MenuOutline), so a greengrocer had one section of forty-five bands and
 * the owner's own example had nowhere to be.
 *
 * Rolls back.
 */
class ProduceAisleSplitTest extends TestCase
{
    use DatabaseTransactions;

    private const SOURCE = 'أصناف الخضار والفاكهة';

    /** @return \Illuminate\Support\Collection<int,string> */
    private function optionsOf(string $groupName)
    {
        return DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupName)
            ->pluck('o.name_ar');
    }

    private function declaredCounts(): array
    {
        $data = require base_path('database/seeders/data/produce_aisle_split.php');

        return collect($data['groups'])->map(fn ($g) => count($g['options']))->all();
    }

    /**
     * The split's promise is that everything it moved arrived — not that the
     * stall never grew afterwards. It did grow, on 2026-08-24: «كل نوع منفرد
     * يُسعّر ويكون له كمية» put forty-five varieties into الفواكه and eighteen
     * missing vegetables into الخضروات, and an equality here would report the
     * owner filling his own list as a regression.
     */
    public function test_the_two_stalls_hold_everything_the_file_moved_into_them(): void
    {
        foreach ($this->declaredCounts() as $name => $count) {
            $this->assertGreaterThanOrEqual($count, $this->optionsOf($name)->count(), "«{$name}»");
        }
    }

    public function test_a_fruit_is_a_fruit_and_a_vegetable_is_a_vegetable(): void
    {
        $fruit = $this->optionsOf('الفواكه');
        $veg = $this->optionsOf('الخضروات');

        foreach (['مانجو', 'فراولة', 'عنب', 'بطيخ', 'بلح وتمر'] as $name) {
            $this->assertContains($name, $fruit->all());
        }

        foreach (['طماطم', 'بطاطس', 'بصل', 'خرشوف', 'ذرة'] as $name) {
            $this->assertContains($name, $veg->all());
        }

        $this->assertEmpty(
            array_intersect($fruit->all(), $veg->all()),
            'No crop stands in both stalls.'
        );
    }

    public function test_nothing_was_created_or_lost_in_the_split(): void
    {
        $data = require base_path('database/seeders/data/produce_aisle_split.php');

        $declared = collect($data['groups'])->pluck('options')->flatten();
        $live = $this->optionsOf('الفواكه')->merge($this->optionsOf('الخضروات'));

        // Every crop that went in is still in one of the two stalls. Rows added
        // since are the owner's, not the split's — see the test above.
        $this->assertEmpty(
            $declared->diff($live)->all(),
            'a crop that moved is in neither stall'
        );

        $this->assertSame(
            0,
            $this->optionsOf(self::SOURCE)->count(),
            'Everything left the source — nothing stayed behind unfiled.'
        );
    }

    public function test_the_emptied_source_is_stopped_not_deleted(): void
    {
        $source = DB::table('option_groups')->where('name_ar', self::SOURCE)->first();

        $this->assertNotNull($source, 'Nothing in this taxonomy is deleted.');
        $this->assertSame(0, (int) $source->is_active, 'An emptied group is stopped, and stays as the record.');
    }

    public function test_no_greengrocer_lost_a_word(): void
    {
        // The split moves `options.group_id` and nothing else. A child that
        // carried forty-five words still carries forty-five — under two titles.
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'خضار وفاكهة')->value('id');

        if ($childId <= 0) {
            $this->markTestSkipped('«خضار وفاكهة» is not in this database.');
        }

        $declared = collect((require base_path('database/seeders/data/produce_aisle_split.php'))['groups'])
            ->pluck('options')->flatten();

        $linked = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $childId)
            ->whereIn('o.name_ar', $declared)
            ->distinct()
            ->count('o.id');

        $this->assertSame($declared->count(), $linked, 'Every crop is still linked to the trade that sells it.');
    }

    public function test_both_stalls_are_priceable_and_stay_priceable(): void
    {
        // The recorded landmine: a group missing from data/option_price_roles.php
        // is pushed back to `descriptive` on the next run — which would turn a
        // greengrocer's crop into a filter he cannot price.
        (new OptionPriceRolesSeeder())->run();

        foreach (['الفواكه', 'الخضروات'] as $name) {
            $this->assertSame(
                'line',
                DB::table('option_groups')->where('name_ar', $name)->value('price_role'),
                "«{$name}» must survive a run of OptionPriceRolesSeeder as a line."
            );
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $before = [
            'fruit' => $this->optionsOf('الفواكه')->count(),
            'veg' => $this->optionsOf('الخضروات')->count(),
            'groups' => DB::table('option_groups')->count(),
            'options' => DB::table('options')->count(),
        ];

        (new ProduceAisleSplitSeeder())->run();

        $this->assertSame($before['fruit'], $this->optionsOf('الفواكه')->count());
        $this->assertSame($before['veg'], $this->optionsOf('الخضروات')->count());
        $this->assertSame($before['groups'], DB::table('option_groups')->count(), 'A second run creates no group.');
        $this->assertSame($before['options'], DB::table('options')->count(), 'A second run creates no option.');
    }
}
