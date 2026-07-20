<?php

namespace Tests\Feature;

use App\Models\FeedPost;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use App\Services\Posts\PostSubjectService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A post may point at something the business actually sells, so the reader can
 * act on it instead of hitting a dead end.
 *
 * The link is optional by design and unset on every existing post: menu_items
 * is empty platform-wide, so a post's own title is still the only thing most
 * businesses have to advertise with. These tests cover both shapes.
 */
class PostSubjectLinkTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        return User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
    }

    private function otherBusiness(User $not): User
    {
        return User::query()->where('type', 'business')->where('id', '!=', $not->id)->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a second business user.');
    }

    private function menuItem(User $business, string $nameAr = 'ساندويتش', string $nameEn = 'Sandwich'): MenuItem
    {
        $section = MenuSection::create([
            'business_id' => $business->id,
            'name_ar' => 'الساندويتشات', 'name_en' => 'Sandwiches',
            'is_active' => true, 'sort_order' => 1,
        ]);

        return MenuItem::create([
            'business_id' => $business->id,
            'menu_section_id' => $section->id,
            'name_ar' => $nameAr, 'name_en' => $nameEn,
            'base_price' => 45, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_a_post_can_be_published_linked_to_an_own_menu_item(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $id = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/posts', [
                'body' => 'عرض خاص اليوم',
                'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
                'subject_id' => $item->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $post = FeedPost::findOrFail($id);

        $this->assertSame(PostSubjectService::TYPE_MENU_ITEM, $post->subject_type);
        $this->assertSame((int) $item->id, (int) $post->subject_id);
    }

    /** The whole point: the reader gets somewhere to tap. */
    public function test_the_payload_carries_a_deep_link_to_the_item(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $subject = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/posts', [
                'body' => 'جربه',
                'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
                'subject_id' => $item->id,
            ])
            ->assertCreated()
            ->json('data.subject');

        $this->assertSame('open_menu_item', $subject['action_type']);
        $this->assertSame("/menu/{$business->id}/items/{$item->id}", $subject['action_url']);
    }

    /** A linked post needs no title of its own — the item names it. */
    public function test_a_linked_post_without_a_title_is_named_by_its_item(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $body = $this->actingAs($business, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/v2/posts', [
                'body' => 'offer',
                'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
                'subject_id' => $item->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Sandwich', $body['title'], 'the title falls back to the item, in the reader language');
    }

    /** Unlinked posts — every post today — still require their own title. */
    public function test_an_unlinked_post_still_requires_a_title(): void
    {
        $this->actingAs($this->business(), 'sanctum')
            ->postJson('/api/v2/posts', ['body' => 'بلا عنوان وبلا ربط'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_an_unlinked_post_reports_no_subject(): void
    {
        $business = $this->business();

        $data = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/posts', ['title' => 'إعلان حر', 'body' => 'نص'])
            ->assertCreated()
            ->json('data');

        $this->assertNull($data['subject']);
        $this->assertSame('إعلان حر', $data['title']);
    }

    /**
     * The leak this feature would otherwise open: advertising somebody else's
     * item and routing the orders to it.
     */
    public function test_a_business_cannot_link_an_item_it_does_not_own(): void
    {
        $owner = $this->business();
        $item = $this->menuItem($owner);
        $stranger = $this->otherBusiness($owner);

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/v2/posts', [
                'body' => 'صنف ليس لي',
                'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
                'subject_id' => $item->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject_id');
    }

    public function test_an_unknown_subject_type_is_rejected(): void
    {
        $this->actingAs($this->business(), 'sanctum')
            ->postJson('/api/v2/posts', [
                'body' => 'نص',
                'subject_type' => 'competitor_item',
                'subject_id' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject_type');
    }

    /** A deleted item must not leave a tap target that goes nowhere. */
    public function test_a_post_whose_item_was_deleted_reports_no_subject(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $post = FeedPost::create([
            'user_id' => $business->id,
            'is_active' => true,
            'title' => 'عرض',
            'body' => 'نص',
            'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
            'subject_id' => $item->id,
        ]);

        $item->delete();

        $data = $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/posts/' . $post->id)
            ->assertOk()
            ->json('data');

        $this->assertNull($data['subject']);
        $this->assertSame('عرض', $data['title'], 'its own title still stands');
    }

    /** Editing must not silently drop a link the caller never mentioned. */
    public function test_an_edit_that_says_nothing_about_the_link_keeps_it(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $post = FeedPost::create([
            'user_id' => $business->id,
            'is_active' => true, 'body' => 'نص',
            'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
            'subject_id' => $item->id,
        ]);

        $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/posts/' . $post->id, ['body' => 'نص محدث'])
            ->assertOk();

        $this->assertSame((int) $item->id, (int) $post->fresh()->subject_id);
    }

    public function test_sending_an_empty_subject_type_unlinks_the_post(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $post = FeedPost::create([
            'user_id' => $business->id,
            'is_active' => true, 'title' => 'عنوان', 'body' => 'نص',
            'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
            'subject_id' => $item->id,
        ]);

        $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/posts/' . $post->id, ['subject_type' => null])
            ->assertOk();

        $this->assertNull($post->fresh()->subject_type);
    }

    /** The picker offers only what the caller owns. */
    public function test_subject_options_list_the_callers_own_menu(): void
    {
        $business = $this->business();
        $item = $this->menuItem($business);

        $options = $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/posts/subject-options')
            ->assertOk()
            ->json('data.options');

        $menu = collect($options)->firstWhere('type', PostSubjectService::TYPE_MENU_ITEM);

        $this->assertNotNull($menu, 'a business with a menu must be offered it');
        $this->assertContains(
            $item->id,
            collect($menu['groups'])->flatMap(fn ($g) => collect($g['items'])->pluck('id'))->all()
        );
    }

    /** Empty is the normal answer today — nothing to advertise is not an error. */
    public function test_subject_options_are_empty_for_a_business_with_nothing_to_sell(): void
    {
        $business = User::query()->where('type', 'business')
            ->whereNotIn('id', MenuItem::query()->select('business_id'))
            ->whereNotIn('id', DB::table('bookable_items')->select('business_id'))
            ->orderBy('id')
            ->first();

        if (! $business) {
            $this->markTestSkipped('Needs a business that owns nothing.');
        }

        $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/posts/subject-options')
            ->assertOk()
            ->assertJsonPath('data.options', []);
    }

    /** The feed must not pay a query per linked post. */
    public function test_the_feed_resolves_subjects_without_an_n_plus_one(): void
    {
        $business = $this->business();

        // THREE DISTINCT items, one per post. Pointing them all at the same
        // item would make this pass with or without the preload, because the
        // per-request cache alone would collapse it to one query — a test that
        // proves nothing.
        foreach (range(1, 3) as $i) {
            $item = $this->menuItem($business, 'صنف ' . $i, 'Item ' . $i);

            FeedPost::create([
                'user_id' => $business->id,
                'is_active' => true, 'body' => 'نص ' . $i,
                'subject_type' => PostSubjectService::TYPE_MENU_ITEM,
                'subject_id' => $item->id,
            ]);
        }

        // A fresh instance so the preload cache starts empty, as in a real request.
        app()->forgetInstance(PostSubjectService::class);

        DB::enableQueryLog();
        $this->actingAs($business, 'sanctum')->getJson('/api/v2/posts/mine')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $menuLookups = $queries->filter(fn ($q) => str_contains($q, 'menu_items'))->count();

        $this->assertLessThanOrEqual(
            1,
            $menuLookups,
            'the page of posts must resolve its subjects in one query, not one per post'
        );
    }
}
