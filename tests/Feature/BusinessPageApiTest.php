<?php

namespace Tests\Feature;

use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The public business page (the aggregate a search result opens) and the
 * per-business posts wall. A business's own page shows its live posts to anyone,
 * unlike the audience-scoped personal feed.
 */
class BusinessPageApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $biz;
    private User $otherBiz;

    protected function setUp(): void
    {
        parent::setUp();
        // Fresh businesses so post counts are deterministic (no pre-existing rows).
        $this->biz = $this->makeBusiness();
        $this->otherBiz = $this->makeBusiness();
    }

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Biz ' . Str::random(5);
        $u->email = 'biz-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function makePost(User $biz, string $title, array $attrs = []): FeedPost
    {
        return FeedPost::create(array_merge([
            'user_id' => $biz->id,
            'title' => $title,
            'body' => 'body of ' . $title,
            'is_active' => true,
            'share_count' => 0,
        ], $attrs));
    }

    public function test_the_business_page_returns_the_profile_aggregate(): void
    {
        $this->makePost($this->biz, 'live one');
        $this->makePost($this->biz, 'live two');

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.id', $this->biz->id)
            ->assertJsonPath('data.name', $this->biz->name)
            ->assertJsonPath('data.rating.role', 'business')
            ->assertJsonPath('data.counts.posts', 2)
            ->assertJsonPath('data.sections.posts', true);

        $this->assertIsBool($res->json('data.open_now'));
        $this->assertArrayHasKey('phone', $res->json('data'));
    }

    /**
     * The info-screen fields (2026-09-04): governorate/city names, not just
     * ids, so the app doesn't need a separate picker fetch to show "which
     * governorate/city" on a business's own info page.
     */
    public function test_the_business_page_includes_governorate_and_city_names(): void
    {
        $city = \App\Models\City::query()->whereNotNull('governorate_id')->firstOrFail();
        $this->biz->forceFill(['governorate_id' => $city->governorate_id, 'city_id' => $city->id])->save();

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.location.city.id', $city->id)
            ->assertJsonPath('data.location.governorate.id', $city->governorate_id);
    }

    public function test_the_business_page_governorate_and_city_are_null_when_unset(): void
    {
        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.location.governorate', null)
            ->assertJsonPath('data.location.city', null);
    }

    /**
     * The info-screen fields (2026-09-07): country_id is rarely set at
     * signup, but the display must not go blank for almost every business —
     * it defaults to Egypt rather than leaving the field empty.
     */
    public function test_the_business_page_defaults_country_to_egypt_when_unset(): void
    {
        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $egypt = \App\Models\Country::query()->where('iso2', 'EG')->firstOrFail();
        $res->assertJsonPath('data.location.country.id', $egypt->id);
    }

    public function test_the_business_page_uses_the_set_country_when_present(): void
    {
        $other = \App\Models\Country::query()->where('iso2', '!=', 'EG')->firstOrFail();
        $this->biz->forceFill(['country_id' => $other->id])->save();

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.location.country.id', $other->id);
    }

    public function test_the_business_page_includes_its_primary_address(): void
    {
        $this->biz->addresses()->create([
            'address_line' => 'شارع الاختبار',
            'is_primary' => true,
        ]);
        $this->biz->addresses()->create([
            'address_line' => 'not primary',
            'is_primary' => false,
        ]);

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.address', 'شارع الاختبار');
    }

    public function test_the_business_page_address_is_null_when_none_is_primary(): void
    {
        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.address', null);
    }

    public function test_the_business_page_includes_category_and_child_names(): void
    {
        $root = \App\Models\Category::query()->firstOrFail();
        $child = \App\Models\CategoryChild::query()->firstOrFail();
        $this->biz->forceFill(['category_id' => $root->id, 'category_child_id' => $child->id])->save();

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.category.id', $root->id)
            ->assertJsonPath('data.category.child_id', $child->id)
            ->assertJsonPath('data.category.child_name.id', $child->id)
            ->assertJsonPath('data.category.name.id', $root->id);
    }

    public function test_the_business_page_includes_its_options(): void
    {
        $option = \App\Models\Option::query()->firstOrFail();
        DB::table('option_user')->insert(['user_id' => $this->biz->id, 'option_id' => $option->id]);

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $ids = collect($res->json('data.options'))->pluck('id')->all();
        $this->assertContains($option->id, $ids);
    }

    public function test_the_business_page_includes_its_social_links(): void
    {
        $this->biz->social()->create(['facebook' => 'fb.com/bim', 'youtube' => '']);

        $this->getJson("/api/v2/businesses/{$this->biz->id}")
            ->assertOk()
            ->assertJsonPath('data.social.facebook', 'fb.com/bim')
            ->assertJsonMissingPath('data.social.youtube');
    }

    public function test_the_business_page_social_is_null_when_never_set(): void
    {
        $this->getJson("/api/v2/businesses/{$this->biz->id}")
            ->assertOk()
            ->assertJsonPath('data.social', null);
    }

    /**
     * The exact bug reported live: a business whose posts all carried an old
     * expire_at got sections.posts=false, so the app rendered no Posts tab
     * at all — not just a hidden post inside one.
     */
    public function test_sections_posts_stays_true_when_every_post_has_an_old_expire_at(): void
    {
        $this->makePost($this->biz, 'old one', ['expire_at' => Carbon::now()->subYear()]);

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.counts.posts', 1)
            ->assertJsonPath('data.sections.posts', true);
    }

    /**
     * The unified fulfillment-method entry point (bim_app, above the menu):
     * no BusinessMenuSetting row means the business never opted out of
     * either — both stay available, matching checkout's own historical
     * unconditional acceptance of delivery/pickup. Dine-in stays false with
     * no active table.
     */
    public function test_the_business_page_fulfillment_defaults_to_delivery_and_pickup_with_no_settings_row(): void
    {
        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.fulfillment.delivery', true)
            ->assertJsonPath('data.fulfillment.pickup', true)
            ->assertJsonPath('data.fulfillment.dine_in', false);
    }

    public function test_the_business_page_fulfillment_reflects_an_opt_out(): void
    {
        \App\Models\BusinessMenuSetting::create([
            'business_id' => $this->biz->id,
            'supports_delivery' => false,
            'supports_pickup' => true,
        ]);

        $res = $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();

        $res->assertJsonPath('data.fulfillment.delivery', false)
            ->assertJsonPath('data.fulfillment.pickup', true);
    }

    public function test_the_business_page_fulfillment_dine_in_is_true_only_with_an_active_table(): void
    {
        DB::table('business_tables')->insert([
            'business_id' => $this->biz->id,
            'label' => 'Table 1',
            'token' => \Illuminate\Support\Str::random(40),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v2/businesses/{$this->biz->id}")
            ->assertOk()
            ->assertJsonPath('data.fulfillment.dine_in', false);

        DB::table('business_tables')->where('business_id', $this->biz->id)->update(['is_active' => true]);

        $this->getJson("/api/v2/businesses/{$this->biz->id}")
            ->assertOk()
            ->assertJsonPath('data.fulfillment.dine_in', true);
    }

    public function test_the_business_page_is_404_for_a_non_business(): void
    {
        $client = User::query()->where('type', '!=', User::TYPE_BUSINESS)->firstOrFail();

        $this->getJson("/api/v2/businesses/{$client->id}")->assertNotFound();
        $this->getJson('/api/v2/businesses/99999999')->assertNotFound();
    }

    public function test_the_posts_wall_returns_only_the_businesss_active_posts(): void
    {
        $liveA = $this->makePost($this->biz, 'live A');
        $liveB = $this->makePost($this->biz, 'live B');
        $inactive = $this->makePost($this->biz, 'hidden', ['is_active' => false]);
        // expire_at isn't checked here (dropped 2026-09-03) — index()/mine()/
        // show() never checked it either, so a post a follower already sees
        // in their feed no longer vanishes the moment they open this wall.
        $oldExpireAt = $this->makePost($this->biz, 'still shown', ['expire_at' => Carbon::now()->subDay()]);
        $foreign = $this->makePost($this->otherBiz, 'someone else');

        $ids = collect($this->getJson("/api/v2/businesses/{$this->biz->id}/posts")->assertOk()->json('data'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($liveA->id, $ids);
        $this->assertContains($liveB->id, $ids);
        $this->assertContains($oldExpireAt->id, $ids, 'a past expire_at no longer hides a post here');
        $this->assertNotContains($inactive->id, $ids, 'an inactive post is hidden');
        $this->assertNotContains($foreign->id, $ids, "another business's post never leaks in");

        // Newest first.
        $this->assertSame($oldExpireAt->id, $ids[0]);
    }

    public function test_the_posts_wall_is_404_for_a_non_business(): void
    {
        $client = User::query()->where('type', '!=', User::TYPE_BUSINESS)->firstOrFail();

        $this->getJson("/api/v2/businesses/{$client->id}/posts")->assertNotFound();
    }

    public function test_both_endpoints_are_public(): void
    {
        // No Authorization header at all.
        $this->getJson("/api/v2/businesses/{$this->biz->id}")->assertOk();
        $this->getJson("/api/v2/businesses/{$this->biz->id}/posts")->assertOk();
    }
}
