<?php

namespace Tests\Feature;

use App\Models\FeedPost;
use App\Models\FollowUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * `follow_user` powers PostAudienceService's feed audience but had no
 * endpoint able to write it until Api\V2\FollowController. Covers the
 * write path and the fact that following someone actually changes the feed.
 *
 * Real fixtures (UserFactory doesn't match the live `users` schema — see
 * AlbumApiTest/BusinessServicePriceApiTest for the same pattern). Cleans up
 * any pre-existing row between this exact pair so "not following yet" holds
 * regardless of real data already in the shared dev database.
 */
class FollowApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::query()->where('type', 'client')->findOrFail(187);
        $this->business = User::query()->where('type', 'business')->findOrFail(179);

        FollowUser::query()
            ->where('user_id', $this->client->id)
            ->where('follow_id', $this->business->id)
            ->delete();
    }

    public function test_following_an_account_surfaces_it_in_the_index(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/follows', ['follow_id' => $this->business->id])
            ->assertCreated();

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/follows')
            ->assertOk();

        $this->assertContains($this->business->id, $response->json('data.*.id'));
    }

    public function test_following_twice_does_not_duplicate(): void
    {
        $this->actingAs($this->client, 'sanctum')->postJson('/api/v2/follows', ['follow_id' => $this->business->id])->assertCreated();
        $this->actingAs($this->client, 'sanctum')->postJson('/api/v2/follows', ['follow_id' => $this->business->id])->assertCreated();

        $this->assertSame(
            1,
            FollowUser::query()->where('user_id', $this->client->id)->where('follow_id', $this->business->id)->count()
        );
    }

    public function test_following_self_is_rejected(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/follows', ['follow_id' => $this->client->id])
            ->assertStatus(422);
    }

    public function test_unfollow_removes_it_and_the_feed_stops_showing_that_author(): void
    {
        FollowUser::query()->create(['user_id' => $this->client->id, 'follow_id' => $this->business->id]);
        $post = FeedPost::create([
            'user_id' => $this->business->id,
            'is_active' => true,
            'share_count' => 0,
            'title' => 'follow-test-post',
            'body' => 'follow-test-post',
        ]);

        try {
            $feed = $this->actingAs($this->client, 'sanctum')->getJson('/api/v2/posts')->assertOk();
            $this->assertContains($post->id, $feed->json('data.*.id'));

            $this->actingAs($this->client, 'sanctum')
                ->deleteJson("/api/v2/follows/{$this->business->id}")
                ->assertOk();

            $feed = $this->actingAs($this->client, 'sanctum')->getJson('/api/v2/posts')->assertOk();
            $this->assertNotContains($post->id, $feed->json('data.*.id'));
        } finally {
            $post->delete();
        }
    }

    public function test_business_page_reports_follow_state_and_followers_count(): void
    {
        $before = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/businesses/{$this->business->id}")
            ->assertOk();
        $this->assertFalse($before->json('data.is_following'));
        $baseline = (int) $before->json('data.counts.followers');

        FollowUser::query()->create(['user_id' => $this->client->id, 'follow_id' => $this->business->id]);

        $after = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/businesses/{$this->business->id}")
            ->assertOk();
        $this->assertTrue($after->json('data.is_following'));
        $this->assertSame($baseline + 1, $after->json('data.counts.followers'));
    }
}
