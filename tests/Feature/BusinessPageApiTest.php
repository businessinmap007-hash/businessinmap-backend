<?php

namespace Tests\Feature;

use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
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

    public function test_the_business_page_is_404_for_a_non_business(): void
    {
        $client = User::query()->where('type', '!=', User::TYPE_BUSINESS)->firstOrFail();

        $this->getJson("/api/v2/businesses/{$client->id}")->assertNotFound();
        $this->getJson('/api/v2/businesses/99999999')->assertNotFound();
    }

    public function test_the_posts_wall_returns_only_the_businesss_live_posts(): void
    {
        $liveA = $this->makePost($this->biz, 'live A');
        $liveB = $this->makePost($this->biz, 'live B');
        $inactive = $this->makePost($this->biz, 'hidden', ['is_active' => false]);
        $expired = $this->makePost($this->biz, 'gone', ['expire_at' => Carbon::now()->subDay()]);
        $foreign = $this->makePost($this->otherBiz, 'someone else');

        $ids = collect($this->getJson("/api/v2/businesses/{$this->biz->id}/posts")->assertOk()->json('data'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($liveA->id, $ids);
        $this->assertContains($liveB->id, $ids);
        $this->assertNotContains($inactive->id, $ids, 'an inactive post is hidden');
        $this->assertNotContains($expired->id, $ids, 'an expired post is hidden');
        $this->assertNotContains($foreign->id, $ids, "another business's post never leaks in");

        // Newest first.
        $this->assertSame($liveB->id, $ids[0]);
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
