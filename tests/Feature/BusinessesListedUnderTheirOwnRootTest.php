<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «أجهزة كمبيوتر تحت مصانع تعرض حسابًا إعداداته شركات» — المالك، 2026-10-05. A child sits under several roots,
 * an account holds exactly ONE (root, child) pair: the list opened through a root shows only the accounts
 * filed under that root. Rolls back.
 */
class BusinessesListedUnderTheirOwnRootTest extends TestCase
{
    use DatabaseTransactions;

    private function ids(array $query): array
    {
        return collect($this->getJson('/api/v2/discovery/businesses?' . http_build_query($query + ['per_page' => 50]))->assertOk()->json('data.businesses.data'))->pluck('id')->all();
    }

    public function test_a_child_shared_by_roots_lists_only_the_accounts_filed_under_the_root_opened(): void
    {
        $child = (int) DB::table('category_parent_child')->select('child_id')->groupBy('child_id')->havingRaw('count(*) > 1')->orderBy('child_id')->value('child_id');
        $roots = DB::table('category_parent_child')->where('child_id', $child)->orderBy('parent_id')->pluck('parent_id')->all();
        $this->assertGreaterThan(1, count($roots));

        $a = User::query()->where('type', 'business')->orderByDesc('id')->firstOrFail();
        $b = User::query()->where('type', 'business')->where('id', '!=', $a->id)->orderByDesc('id')->firstOrFail();
        DB::table('users')->where('id', $a->id)->update(['category_id' => $roots[0], 'category_child_id' => $child]);
        DB::table('users')->where('id', $b->id)->update(['category_id' => $roots[1], 'category_child_id' => $child]);

        $under0 = $this->ids(['child_id' => $child, 'category_id' => $roots[0]]);
        $under1 = $this->ids(['child_id' => $child, 'category_id' => $roots[1]]);

        $this->assertContains($a->id, $under0);
        $this->assertNotContains($b->id, $under0, 'filed under another root');
        $this->assertContains($b->id, $under1);
        $this->assertNotContains($a->id, $under1);
    }

    public function test_without_a_root_the_list_is_the_childs(): void
    {
        $child = (int) DB::table('category_parent_child')->select('child_id')->groupBy('child_id')->havingRaw('count(*) > 1')->orderBy('child_id')->value('child_id');
        $a = User::query()->where('type', 'business')->orderByDesc('id')->firstOrFail();
        DB::table('users')->where('id', $a->id)->update(['category_child_id' => $child]);

        $this->assertContains($a->id, $this->ids(['child_id' => $child]));
    }

    public function test_the_specialties_of_a_root_count_the_accounts_filed_under_that_root(): void
    {
        $child = (int) DB::table('category_parent_child')->select('child_id')->groupBy('child_id')->havingRaw('count(*) > 1')->orderBy('child_id')->value('child_id');
        $roots = DB::table('category_parent_child')->where('child_id', $child)->orderBy('parent_id')->pluck('parent_id')->all();
        $a = User::query()->where('type', 'business')->orderByDesc('id')->firstOrFail();
        DB::table('users')->where('id', $a->id)->update(['category_id' => $roots[0], 'category_child_id' => $child]);
        // A shop with no priced service is still an activity: the count is of accounts, not of priced rows.
        DB::table('business_service_prices')->where('business_id', $a->id)->delete();

        $count = fn (int $root) => collect($this->getJson("/api/v2/categories/{$root}/specialties")->assertOk()->json('data.specialties'))->firstWhere('id', $child)['businesses'];
        $before = $count($roots[1]);

        $this->assertGreaterThanOrEqual(1, $count($roots[0]));
        $this->assertSame($before, $count($roots[1]), 'the other root does not gain it');
    }

    public function test_where_the_customer_is_orders_the_list_and_never_hides_anyone(): void
    {
        $child = (int) DB::table('category_parent_child')->select('child_id')->groupBy('child_id')->havingRaw('count(*) > 1')->orderBy('child_id')->value('child_id');
        $root = (int) DB::table('category_parent_child')->where('child_id', $child)->orderBy('parent_id')->value('parent_id');
        $city = DB::table('cities')->orderBy('id')->first();
        $other = DB::table('cities')->where('governorate_id', '!=', $city->governorate_id)->orderBy('id')->first();

        $users = User::query()->where('type', 'business')->orderByDesc('id')->limit(2)->get();
        // «a» is far away but sorts first by name; «b» is in the customer's city.
        DB::table('users')->where('id', $users[0]->id)->update(['category_id' => $root, 'category_child_id' => $child, 'name' => '0far', 'city_id' => $other->id, 'governorate_id' => $other->governorate_id]);
        DB::table('users')->where('id', $users[1]->id)->update(['category_id' => $root, 'category_child_id' => $child, 'name' => 'zzclose', 'city_id' => $city->id, 'governorate_id' => $city->governorate_id]);

        $all = $this->ids(['child_id' => $child, 'category_id' => $root]);
        $near = $this->ids(['child_id' => $child, 'category_id' => $root, 'near_city_id' => $city->id, 'near_governorate_id' => $city->governorate_id]);

        $this->assertEqualsCanonicalizing($all, $near, 'nobody is hidden by where the customer is');
        $this->assertLessThan(array_search($users[0]->id, $near), array_search($users[1]->id, $near), 'but the one in the customer city comes first');
    }
}
