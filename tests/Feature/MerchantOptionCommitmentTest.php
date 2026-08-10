<?php

namespace Tests\Feature;

use App\Services\Catalog\MerchantOptionCommitments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A merchant's commitment to an option outranks every map that describes it.
 *
 * That rule was already here and every seeder implemented it — against
 * `option_user`, and only against `option_user`. Ticking an option says «this
 * describes me»; putting a PRICE on it says «this is what I sell and here is
 * what it costs». The weaker of the two was the protected one.
 *
 * The bill arrived as «فندق الاندلس»: 2,000 on «شقة» and 5,000 on «ڤيلا», both
 * still in `business_service_prices`, both pointing at options the hotel child
 * stopped offering when hotels were narrowed to «الغرف». Two priced rows the
 * merchant cannot reach, and nothing told him.
 */
class MerchantOptionCommitmentTest extends TestCase
{
    use DatabaseTransactions;

    private MerchantOptionCommitments $commitments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commitments = app(MerchantOptionCommitments::class);
    }

    /** A business with a child, and an option nobody has committed to. */
    private function subject(): array
    {
        $row = DB::table('users')->whereNotNull('category_child_id')
            ->whereExists(fn ($q) => $q->from('category_child_option')
                ->whereColumn('category_child_option.child_id', 'users.category_child_id'))
            ->first(['id', 'category_child_id']);

        $this->assertNotNull($row, 'no business stands on a child with options');

        // An option on that child NOBODY has committed to yet, so the test owns
        // the state it is about to create.
        $optionId = (int) DB::table('category_child_option as cco')
            ->where('cco.child_id', $row->category_child_id)
            ->whereNotExists(fn ($q) => $q->from('option_user')
                ->whereColumn('option_user.option_id', 'cco.option_id'))
            ->whereNotExists(fn ($q) => $q->from('business_service_prices')
                ->whereColumn('business_service_prices.line_option_id', 'cco.option_id'))
            ->value('cco.option_id');

        if ($optionId <= 0) {
            $this->markTestSkipped('every option on that child is already committed');
        }

        return [(int) $row->id, (int) $row->category_child_id, $optionId];
    }

    /** A tick counts, as it always has. */
    public function test_a_ticked_option_is_a_commitment(): void
    {
        [$userId, $childId, $optionId] = $this->subject();

        $this->assertNotContains($optionId, $this->commitments->forChild($childId, [$optionId]));

        DB::table('option_user')->insert(['user_id' => $userId, 'option_id' => $optionId]);

        $this->assertContains($optionId, $this->commitments->forChild($childId, [$optionId]));
        $this->assertContains($optionId, $this->commitments->anywhere([$optionId]));
    }

    /** And so does a price, which is the half that was missing. */
    public function test_a_priced_option_is_a_commitment(): void
    {
        [$userId, $childId, $optionId] = $this->subject();

        $this->assertNotContains($optionId, $this->commitments->forChild($childId, [$optionId]));

        DB::table('business_service_prices')->insert([
            'business_id' => $userId,
            'child_id' => $childId,
            'service_id' => (int) DB::table('platform_services')->where('key', 'booking')->value('id'),
            'line_option_id' => $optionId,
            'price' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertContains($optionId, $this->commitments->forChild($childId, [$optionId]));
        $this->assertContains($optionId, $this->commitments->anywhere([$optionId]));
    }

    /**
     * The guard, end to end: a seeder that means to drop a link leaves it alone
     * once a merchant has priced it.
     *
     * `ChildOptionScopeSeeder` is used because its declared empties are the
     * bluntest drop in the codebase — a child listed with `[]` loses the whole
     * group. «نجار موبيليا» #49 is declared out of «أثاث وتشطيب منزلي», so a
     * price on one of those options is the exact collision.
     */
    public function test_a_seeder_will_not_drop_a_line_a_merchant_has_priced(): void
    {
        $childId = 49;
        $optionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'أثاث وتشطيب منزلي')->value('o.id');

        $userId = (int) DB::table('users')->where('category_child_id', $childId)->value('id');

        if ($userId <= 0) {
            $this->markTestSkipped('no merchant stands on «نجار موبيليا»');
        }

        DB::table('category_child_option')->insertOrIgnore([
            'child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0,
        ]);

        DB::table('business_service_prices')->insert([
            'business_id' => $userId,
            'child_id' => $childId,
            'service_id' => (int) DB::table('platform_services')->where('key', 'booking')->value('id'),
            'line_option_id' => $optionId,
            'price' => 3500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new \Database\Seeders\ChildOptionScopeSeeder)->run();

        $this->assertTrue(
            DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            'the seeder dropped a line the merchant had put a price on'
        );
    }

    /**
     * And the standing state, as a rule.
     *
     * A priced row pointing at an option the taxonomy no longer offers is money
     * the merchant entered and cannot reach. Two exist today, both «فندق
     * الاندلس» — a hotel that priced an apartment and a villa before hotels were
     * narrowed to rooms — and they are listed here rather than deleted, because
     * nothing in this codebase deletes a merchant's own data to make a test
     * pass. Whether that hotel should be able to let a villa, or those rows
     * belong to «شقق فندقية», is the owner's call.
     */
    public function test_no_new_priced_row_is_orphaned_by_the_taxonomy(): void
    {
        $known = ['فندق' => ['شقة', 'ڤيلا']];

        $unexpected = [];

        foreach ($this->commitments->orphaned() as $row) {
            $child = (string) DB::table('category_children_master')->where('id', $row->child_id)->value('name_ar');
            $option = (string) DB::table('options')->where('id', $row->option_id)->value('name_ar');

            if (in_array($option, $known[$child] ?? [], true)) {
                continue;
            }

            $unexpected[] = "«{$child}» → «{$option}» ({$row->price_rows} rows)";
        }

        $this->assertSame(
            [],
            $unexpected,
            "merchants have priced rows the taxonomy no longer offers:\n  " . implode("\n  ", $unexpected)
        );
    }
}
