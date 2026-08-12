<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CategoryChildOptionScope;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «الخيارات فى صفحة المستخدم تعرض كل مجموعة الخيارات للابن وليس الخيارات
 * المحددة لهذا الابن — هناك انفصال بين ما تم وما هو موجود بالفعل».
 *
 * The picker screen grants a child's options PER ROOT: «دعاية وإعلان» was given
 * 19 under «خدمات» and a different 19 under a goods root it also sits in. The
 * admin user screens read `category_child_option` by CHILD alone, so they
 * showed the union — 45 — and the extra groups (نطاق التعامل، حالة المنتج،
 * التسليم والاستلام) had never been granted to this shop at all. 75 of the 169
 * live root/child pairs over-showed this way, covering 262 businesses.
 *
 * Worse than the display: `update()` validated against the same unscoped set,
 * so the panel would happily save a vocabulary that the merchant's own profile
 * endpoint — which has always scoped by root — refuses. Two doors into one
 * column, disagreeing about what is in it.
 *
 * @see \App\Services\CategoryChildOptionScope
 * @see \App\Http\Controllers\Api\V2\ProfileController
 */
class AdminUserOptionScopeTest extends TestCase
{
    use DatabaseTransactions;

    private CategoryChildOptionScope $scope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scope = app(CategoryChildOptionScope::class);
    }

    private function admin(): User
    {
        $admin = User::query()->orderBy('id')->firstOrFail();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::USERS);
        Bouncer::refresh();

        return $admin;
    }

    /**
     * A real child under two real roots, plus one freshly minted option granted
     * to each root alone.
     *
     * The options are new rather than borrowed so their names cannot collide
     * with anything else on the page — what is asserted is that a name appears
     * or does not, and a shared name would make both answers true.
     *
     * @return array{0:int,1:int,2:int,3:array{id:int,name:string},4:array{id:int,name:string}}
     */
    private function twoRootedChild(): array
    {
        $row = DB::table('category_parent_child')
            ->select('child_id')
            ->groupBy('child_id')
            ->havingRaw('COUNT(DISTINCT parent_id) > 1')
            ->first();

        if (! $row) {
            $this->markTestSkipped('No child sits under more than one root.');
        }

        $childId = (int) $row->child_id;

        $roots = DB::table('category_parent_child')
            ->where('child_id', $childId)
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $groupId = (int) DB::table('option_groups')->where('is_active', 1)->value('id');

        $mint = function (string $tag) use ($groupId): array {
            $name = 'اختبار-النطاق-' . $tag . '-' . uniqid();

            $id = (int) DB::table('options')->insertGetId([
                'group_id' => $groupId ?: null,
                'name_ar' => $name,
                'name_en' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (DB::getSchemaBuilder()->hasColumn('options', 'is_active')) {
                DB::table('options')->where('id', $id)->update(['is_active' => 1]);
            }

            return ['id' => $id, 'name' => $name];
        };

        $mine = $mint('لي');
        $theirs = $mint('لغيري');

        $this->scope->grantFor($childId, (int) $roots[0], [$mine['id']]);
        $this->scope->grantFor($childId, (int) $roots[1], [$theirs['id']]);

        return [$childId, (int) $roots[0], (int) $roots[1], $mine, $theirs];
    }

    /** Point a business at one root/child pair without disturbing anything else. */
    private function businessAt(int $rootId, int $childId): User
    {
        $business = User::query()->where('type', 'business')->firstOrFail();

        DB::table('users')->where('id', $business->id)->update([
            'category_id' => $rootId,
            'category_child_id' => $childId,
        ]);

        return $business->refresh();
    }

    /** The endpoint answers for a PAIR, and the other root's grant is not in it. */
    public function test_the_catalog_answers_for_one_root_only(): void
    {
        [$childId, $mineRoot, , $mine, $theirs] = $this->twoRootedChild();

        $ids = collect(
            $this->actingAs($this->admin())
                ->getJson(route('admin.users.catalog', ['child_id' => $childId, 'category_id' => $mineRoot]))
                ->assertOk()
                ->json('options')
        )->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertContains($mine['id'], $ids->all(), 'the root\'s own grant is missing');
        $this->assertNotContains($theirs['id'], $ids->all(), 'another root\'s vocabulary leaked into this one');
    }

    /** The grouped shape has to obey the same scope as the flat one. */
    public function test_the_grouped_shape_obeys_the_scope_too(): void
    {
        [$childId, $mineRoot, , $mine, $theirs] = $this->twoRootedChild();

        $grouped = collect(
            $this->actingAs($this->admin())
                ->getJson(route('admin.users.catalog', ['child_id' => $childId, 'category_id' => $mineRoot]))
                ->assertOk()
                ->json('groups')
        )->pluck('options')->flatten(1)->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertContains($mine['id'], $grouped->all());
        $this->assertNotContains($theirs['id'], $grouped->all());
    }

    /**
     * No root named is not the same question. A screen with no root in hand has
     * always meant "every root", and that stays the answer.
     */
    public function test_without_a_root_the_union_is_still_the_answer(): void
    {
        [$childId, , , $mine, $theirs] = $this->twoRootedChild();

        $ids = collect(
            $this->actingAs($this->admin())
                ->getJson(route('admin.users.catalog', ['child_id' => $childId]))
                ->assertOk()
                ->json('options')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($mine['id'], $ids);
        $this->assertContains($theirs['id'], $ids);
    }

    /** The edit form seeds the child the business already has — under HIS root. */
    public function test_the_edit_form_shows_only_the_businesss_own_root(): void
    {
        [$childId, $mineRoot, , $mine, $theirs] = $this->twoRootedChild();
        $business = $this->businessAt($mineRoot, $childId);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.edit', $business->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine['name'], $html, 'the shop cannot see its own vocabulary');
        $this->assertStringNotContainsString(
            $theirs['name'],
            $html,
            'the edit form offered a vocabulary granted to a different root'
        );
    }

    /** …and so does the list filter, once a root is picked. */
    public function test_the_list_filter_is_scoped_to_the_chosen_root(): void
    {
        [$childId, $mineRoot, , $mine, $theirs] = $this->twoRootedChild();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['category_id' => $mineRoot, 'category_child_id' => $childId]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine['name'], $html);
        $this->assertStringNotContainsString($theirs['name'], $html, 'the filter offered another root\'s options');
    }

    /**
     * The hole that mattered: saving. The panel used to accept any option the
     * child carried anywhere, writing a row the merchant's own API would reject.
     */
    public function test_saving_refuses_an_option_granted_to_another_root(): void
    {
        [$childId, $mineRoot, , , $theirs] = $this->twoRootedChild();
        $business = $this->businessAt($mineRoot, $childId);

        $this->actingAs($this->admin())
            ->from(route('admin.users.edit', $business->id))
            ->put(route('admin.users.update', $business->id), [
                'name' => $business->name,
                'name_en' => $business->name_en ?: 'Scope Test',
                'email' => $business->email,
                'phone' => $business->phone,
                'type' => 'business',
                'category_id' => $mineRoot,
                'category_child_id' => $childId,
                'options' => [$theirs['id']],
            ])
            ->assertSessionHasErrors('options');

        $this->assertDatabaseMissing('option_user', [
            'user_id' => $business->id,
            'option_id' => $theirs['id'],
        ]);
    }

    /** The same save with the root's own option goes through. */
    public function test_saving_accepts_an_option_granted_to_this_root(): void
    {
        [$childId, $mineRoot, , $mine] = $this->twoRootedChild();
        $business = $this->businessAt($mineRoot, $childId);

        $this->actingAs($this->admin())
            ->from(route('admin.users.edit', $business->id))
            ->put(route('admin.users.update', $business->id), [
                'name' => $business->name,
                'name_en' => $business->name_en ?: 'Scope Test',
                'email' => $business->email,
                'phone' => $business->phone,
                'type' => 'business',
                'category_id' => $mineRoot,
                'category_child_id' => $childId,
                'options' => [$mine['id']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('option_user', [
            'user_id' => $business->id,
            'option_id' => $mine['id'],
        ]);
    }

    /**
     * The screens must agree with the merchant's own door, not merely with each
     * other: whatever the panel offers, `ProfileController` has to accept.
     */
    public function test_the_panel_offers_exactly_what_the_merchant_api_allows(): void
    {
        [$childId, $mineRoot] = $this->twoRootedChild();

        $offered = collect(
            $this->actingAs($this->admin())
                ->getJson(route('admin.users.catalog', ['child_id' => $childId, 'category_id' => $mineRoot]))
                ->assertOk()
                ->json('options')
        )->pluck('id')->map(fn ($id) => (int) $id);

        // What ProfileController::update checks an incoming pick against.
        $accepted = DB::table('category_child_option')
            ->where('child_id', $childId)
            ->whereIn('category_id', [0, $mineRoot])
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);

        $this->assertEmpty(
            $offered->diff($accepted)->all(),
            'the panel offered options the merchant API would refuse'
        );
    }
}
