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
}
