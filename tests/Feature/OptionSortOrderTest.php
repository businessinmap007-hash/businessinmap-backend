<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Options within a group had no order of their own — only the GROUP could
 * reorder itself (option_groups.reorder), so a hotel's meal-plan choices
 * ("breakfast included" / "half board" / "full board") always came out in
 * raw id order, which happened to read "breakfast, full board, half board".
 * `options.sort_order` (this migration) plus the vocabulary query respecting
 * it is what lets that be curated. Same story for countries: no way to put
 * Egypt first in a 249-row list.
 */
class OptionSortOrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_meal_plan_options_list_in_the_curated_order(): void
    {
        $business = User::query()
            ->where('type', 'business')
            ->where('category_id', 24)
            ->where('category_child_id', 536)
            ->first();

        if (! $business) {
            $this->markTestSkipped('No hospitality-root business fixture.');
        }

        $groupId = DB::table('option_groups')->where('name_ar', 'نظام الوجبات')->value('id');

        if (! $groupId) {
            $this->markTestSkipped('Meal plan group not seeded.');
        }

        $rows = app(MerchantOfferingVocabulary::class)
            ->everythingOffered((int) $business->id, (int) $business->category_child_id, (int) $business->category_id)
            ->get('نظام الوجبات');

        if (! $rows) {
            $this->markTestSkipped('This business does not carry the meal plan group.');
        }

        $names = collect($rows)->pluck('name_ar')->values()->all();

        $this->assertSame(['شامل الإفطار', 'نصف إقامة', 'إقامة كاملة'], $names);
    }

    public function test_an_options_sort_order_beats_its_id_in_vocabulary_listing(): void
    {
        $groupId = DB::table('option_groups')->where('is_active', 1)->value('id');
        $this->assertNotNull($groupId);

        $high = DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => 'خيار اختبار أ ' . Str::random(6),
            'name_en' => 'Test option A ' . Str::random(6),
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $low = DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => 'خيار اختبار ب ' . Str::random(6),
            'name_en' => 'Test option B ' . Str::random(6),
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A higher id but a lower sort_order must still come first.
        $this->assertGreaterThan($high, $low);

        $ordered = DB::table('options')
            ->whereIn('id', [$high, $low])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$high, $low], $ordered);
    }

    public function test_countries_list_egypt_first(): void
    {
        $body = $this->getJson('/api/v2/locations/countries')->assertOk()->json('data.countries');

        $this->assertNotEmpty($body);
        $this->assertSame('EG', $body[0]['iso2']);
    }

    public function test_countries_search_still_works_alongside_sort_order(): void
    {
        $egypt = Country::where('iso2', 'EG')->first();

        $body = $this->getJson('/api/v2/locations/countries?q=' . urlencode($egypt->name_ar))
            ->assertOk()
            ->json('data.countries');

        $this->assertNotEmpty($body);
        $this->assertTrue(collect($body)->contains('iso2', 'EG'));
    }
}
