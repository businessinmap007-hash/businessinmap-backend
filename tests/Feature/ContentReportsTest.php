<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\OwnerListing;
use App\Models\Post;
use App\Models\User;
use App\Services\OwnerListings\OwnerListingCategories;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «الإبلاغ»: anyone reports a post or an owner listing, once; a moderator (content ability) works one row per reported item.
 */
class ContentReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function user(string $tag, string $type = User::TYPE_CLIENT): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function moderator(): User
    {
        $m = $this->user('mod');
        Bouncer::allow($m)->to(AdminAbility::CONTENT);
        Bouncer::refreshFor($m);

        return $m;
    }

    private function makePost(User $author, bool $active = true): Post
    {
        return Post::query()->create(['type' => 'post', 'user_id' => $author->id, 'is_active' => $active, 'title' => 'Big sale', 'body' => 'Everything must go']);
    }

    private function listing(User $owner): OwnerListing
    {
        $l = OwnerListing::query()->create([
            'user_id' => $owner->id, 'category' => OwnerListingCategories::keys()[0], 'deal' => 'sale', 'title' => 'Used thing',
            'price' => 100, 'governorate_id' => 1, 'attributes' => [], 'status' => OwnerListing::STATUS_ACTIVE, 'expires_at' => now()->addDays(30),
        ]);

        return $l;
    }

    public function test_anyone_reports_a_post_once_and_never_their_own(): void
    {
        $author = $this->user('author', User::TYPE_BUSINESS);
        $post = $this->makePost($author);
        $reporter = $this->user('rep');

        Sanctum::actingAs($reporter);
        $this->getJson('/api/v2/reports/reasons')->assertOk()->assertJsonStructure(['data' => ['reasons' => [['key', 'name']]]]);
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam', 'details' => 'again and again'])->assertOk();
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'fraud'])->assertOk();

        $this->assertSame(1, ContentReport::where('reportable_type', 'post')->where('reportable_id', $post->id)->count(), 'one person counts once');
        $this->assertSame('spam', ContentReport::where('reportable_id', $post->id)->first()->reason, 'the first report stands');

        Sanctum::actingAs($author);
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertStatus(422);

        Sanctum::actingAs($reporter);
        $this->postJson('/api/v2/reports', ['type' => 'nonsense', 'id' => 1, 'reason' => 'spam'])->assertStatus(422);
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $this->makePost($author, false)->id, 'reason' => 'spam'])->assertStatus(422);
    }

    public function test_only_a_moderator_sees_the_queue_and_the_app_is_told_who_is(): void
    {
        $plain = $this->user('plain');
        Sanctum::actingAs($plain);
        $this->getJson('/api/v2/moderation/me')->assertOk()->assertJsonPath('data.is_moderator', false);
        $this->getJson('/api/v2/moderation/reports')->assertStatus(404);
        $this->postJson('/api/v2/moderation/reports/post/1/remove')->assertStatus(404);

        Sanctum::actingAs($this->moderator());
        $this->getJson('/api/v2/moderation/me')->assertOk()->assertJsonPath('data.is_moderator', true);
        $this->getJson('/api/v2/moderation/reports')->assertOk();
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.is_moderator', true);
    }

    public function test_the_queue_has_one_row_per_item_with_the_reasons_and_a_post_is_hidden_then_restored(): void
    {
        $author = $this->user('author', User::TYPE_BUSINESS);
        $post = $this->makePost($author);

        foreach (['spam', 'spam', 'fraud'] as $i => $reason) {
            Sanctum::actingAs($this->user('rep' . $i));
            $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => $reason])->assertOk();
        }

        Sanctum::actingAs($this->moderator());
        $row = collect($this->getJson('/api/v2/moderation/reports?type=post&per_page=50')->assertOk()->json('data.items'))->firstWhere('id', $post->id);
        $this->assertSame(3, $row['reports']);
        $this->assertSame('live', $row['state']);
        $this->assertSame('Big sale', $row['title']);
        $this->assertSame(['spam' => 2, 'fraud' => 1], collect($row['reasons'])->pluck('count', 'key')->all());

        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/remove", ['violation' => 'fraud'])->assertOk();
        $this->assertFalse((bool) $post->refresh()->is_active, 'hidden, not deleted');
        $handled = collect($this->getJson('/api/v2/moderation/reports?scope=handled&per_page=50')->json('data.items'))->firstWhere('id', $post->id);
        $this->assertSame('hidden', $handled['state']);
        $this->assertNull(collect($this->getJson('/api/v2/moderation/reports?per_page=50')->json('data.items'))->firstWhere('id', $post->id), 'it left the open queue');

        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/restore")->assertOk();
        $this->assertTrue((bool) $post->refresh()->is_active);
    }

    public function test_dismissing_closes_the_reports_and_brings_a_flagged_listing_back(): void
    {
        $owner = $this->user('own');
        $listing = $this->listing($owner);

        foreach (range(1, 3) as $i) {
            Sanctum::actingAs($this->user('rep' . $i));
            $this->postJson('/api/v2/reports', ['type' => 'owner_listing', 'id' => $listing->id, 'reason' => 'prohibited'])->assertOk();
        }

        $this->assertSame(OwnerListing::STATUS_FLAGGED, $listing->refresh()->status, 'three different people hide it until somebody looks');

        Sanctum::actingAs($this->moderator());
        $this->postJson("/api/v2/moderation/reports/owner_listing/{$listing->id}/dismiss")->assertOk();

        $this->assertSame(OwnerListing::STATUS_ACTIVE, $listing->refresh()->status);
        $this->assertSame(0, ContentReport::where('reportable_type', 'owner_listing')->where('reportable_id', $listing->id)->where('status', 'open')->count());
    }

    public function test_removing_a_listing_deletes_it(): void
    {
        $listing = $this->listing($this->user('own'));
        Sanctum::actingAs($this->user('rep'));
        $this->postJson('/api/v2/reports', ['type' => 'owner_listing', 'id' => $listing->id, 'reason' => 'fraud'])->assertOk();

        Sanctum::actingAs($this->moderator());
        $this->postJson("/api/v2/moderation/reports/owner_listing/{$listing->id}/remove", ['violation' => 'fraud'])->assertOk();

        $this->assertNull(OwnerListing::find($listing->id));
        $row = collect($this->getJson('/api/v2/moderation/reports?scope=handled&per_page=50')->json('data.items'))->firstWhere('id', $listing->id);
        $this->assertSame('gone', $row['state']);
    }

    public function test_the_moderator_who_opens_a_report_is_recorded_and_nobody_else_may_act_until_he_closes_it(): void
    {
        $post = $this->makePost($this->user('author', User::TYPE_BUSINESS));
        Sanctum::actingAs($this->user('rep'));
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertOk();

        $fahim = $this->moderator();
        $other = $this->moderator();

        Sanctum::actingAs($fahim);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/claim")->assertOk();
        $row = collect($this->getJson('/api/v2/moderation/reports?type=post&per_page=50')->json('data.items'))->firstWhere('id', $post->id);
        $this->assertTrue($row['claimed_by_me']);
        $this->assertFalse($row['locked']);
        $this->assertSame($fahim->displayName(), $row['handler']['name']);

        // another moderator sees who has it, and cannot claim, dismiss or remove it
        Sanctum::actingAs($other);
        $row = collect($this->getJson('/api/v2/moderation/reports?type=post&per_page=50')->json('data.items'))->firstWhere('id', $post->id);
        $this->assertTrue($row['locked']);
        $this->assertFalse($row['claimed_by_me']);
        $this->assertSame($fahim->displayName(), $row['handler']['name']);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/claim")->assertStatus(422)->assertJsonValidationErrors('claim');
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertStatus(422);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/remove")->assertStatus(422);
        $this->assertTrue((bool) $post->refresh()->is_active);

        // he lets it go: free again
        Sanctum::actingAs($fahim);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/release")->assertOk();
        Sanctum::actingAs($other);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/claim")->assertOk();

        // `other` holds it now. 29 quiet minutes is still his; touching it (opening it again) restarts the 30
        Sanctum::actingAs($fahim);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertStatus(422);
        $this->travel(29)->minutes();
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertStatus(422);
        Sanctum::actingAs($other);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/claim")->assertOk();
        $this->travel(15)->minutes();
        Sanctum::actingAs($fahim);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertStatus(422);

        // 30 minutes without a touch: the claim lapses and another moderator may review it
        $this->travel(16)->minutes();
        $row = collect($this->getJson('/api/v2/moderation/reports?type=post&per_page=50')->json('data.items'))->firstWhere('id', $post->id);
        $this->assertFalse($row['locked']);
        $this->assertNull($row['handler']);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertOk();

        $done = collect($this->getJson('/api/v2/moderation/reports?scope=handled&per_page=50')->json('data.items'))->firstWhere('id', $post->id);
        $this->assertSame($fahim->displayName(), $done['handled_by'], 'the handled list says who closed it');
    }

    public function test_acting_without_opening_first_claims_it_and_a_report_with_nothing_open_is_refused(): void
    {
        $post = $this->makePost($this->user('author', User::TYPE_BUSINESS));
        Sanctum::actingAs($this->user('rep'));
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertOk();

        $mod = $this->moderator();
        Sanctum::actingAs($mod);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertOk();
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/dismiss")->assertStatus(422);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/claim")->assertStatus(422);
    }

    public function test_the_author_is_told_when_a_moderator_takes_his_content_down(): void
    {
        $author = $this->user('author', User::TYPE_BUSINESS);
        $post = $this->makePost($author);
        $listing = $this->listing($author);

        foreach ([['post', $post->id], ['owner_listing', $listing->id]] as $i => [$type, $id]) {
            Sanctum::actingAs($this->user('rep' . $i));
            $this->postJson('/api/v2/reports', ['type' => $type, 'id' => $id, 'reason' => 'fraud'])->assertOk();
        }

        Sanctum::actingAs($this->moderator());
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/remove", ['violation' => 'fraud'])->assertOk();
        $this->postJson("/api/v2/moderation/reports/owner_listing/{$listing->id}/remove", ['violation' => 'fraud'])->assertOk();

        $told = AppNotification::query()->where('user_id', $author->id)->where('source_type', 'moderation_content_removed')->get();
        $this->assertCount(2, $told);
        $this->assertTrue($told->contains(fn ($n) => str_contains((string) $n->body_ar, 'منشورك')));
        $this->assertTrue($told->contains(fn ($n) => str_contains((string) $n->body_ar, 'إعلانك')));

        // dismissing tells nobody
        $other = $this->makePost($author);
        Sanctum::actingAs($this->user('rep9'));
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $other->id, 'reason' => 'spam'])->assertOk();
        Sanctum::actingAs($this->moderator());
        $this->postJson("/api/v2/moderation/reports/post/{$other->id}/dismiss")->assertOk();
        $this->assertSame(2, AppNotification::query()->where('user_id', $author->id)->where('source_type', 'moderation_content_removed')->count());
    }

    public function test_a_public_comment_can_be_reported_and_a_removed_one_shows_to_nobody(): void
    {
        $author = $this->user('author', User::TYPE_BUSINESS);
        $post = $this->makePost($author);
        $writer = $this->user('writer');
        $comment = Comment::query()->create(['post_id' => $post->id, 'user_id' => $writer->id, 'comment' => 'buy cheap pills', 'status' => 'public']);
        $private = Comment::query()->create(['post_id' => $post->id, 'user_id' => $writer->id, 'comment' => 'just between us', 'status' => 'private']);

        Sanctum::actingAs($this->user('rep'));
        $this->postJson('/api/v2/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam'])->assertOk();
        $this->postJson('/api/v2/reports', ['type' => 'comment', 'id' => $private->id, 'reason' => 'spam'])->assertStatus(422);

        Sanctum::actingAs($writer);
        $this->postJson('/api/v2/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam'])->assertStatus(422);

        Sanctum::actingAs($this->moderator());
        $row = collect($this->getJson('/api/v2/moderation/reports?type=comment&per_page=50')->json('data.items'))->firstWhere('id', $comment->id);
        $this->assertSame('buy cheap pills', $row['excerpt']);
        $this->postJson("/api/v2/moderation/reports/comment/{$comment->id}/remove", ['violation' => 'fraud'])->assertOk();
        $this->assertSame('removed', $comment->refresh()->status);

        // gone for the public, for the commenter and for the post's author alike
        foreach ([null, $writer, $author] as $viewer) {
            $viewer ? Sanctum::actingAs($viewer) : $this->app['auth']->forgetGuards();
            $texts = collect($this->getJson("/api/v2/posts/{$post->id}/comments")->json('data'))->pluck('comment');
            $this->assertFalse($texts->contains('buy cheap pills'));
        }

        // a comment is never restored: what it was before (public or private) is not kept
        Sanctum::actingAs($this->moderator());
        $this->postJson("/api/v2/moderation/reports/comment/{$comment->id}/restore")->assertStatus(422);
    }

    public function test_the_reporter_hears_the_outcome_but_not_who_decided(): void
    {
        $post = $this->makePost($this->user('author', User::TYPE_BUSINESS));
        $kept = $this->makePost($this->user('author2', User::TYPE_BUSINESS));
        $rep = $this->user('rep');

        Sanctum::actingAs($rep);
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'fraud'])->assertOk();
        $this->postJson('/api/v2/reports', ['type' => 'post', 'id' => $kept->id, 'reason' => 'spam'])->assertOk();

        $mod = $this->moderator();
        Sanctum::actingAs($mod);
        $this->postJson("/api/v2/moderation/reports/post/{$post->id}/remove", ['violation' => 'fraud'])->assertOk();
        $this->postJson("/api/v2/moderation/reports/post/{$kept->id}/dismiss")->assertOk();

        $told = AppNotification::query()->where('user_id', $rep->id)->where('source_type', 'moderation_report_outcome')->orderBy('source_id')->get();
        $this->assertCount(2, $told);
        $this->assertStringContainsString('أزال', $told->firstWhere('source_id', $post->id)->body_ar);
        $this->assertStringContainsString('لا يخالف', $told->firstWhere('source_id', $kept->id)->body_ar);
        $this->assertStringNotContainsString($mod->name, $told->pluck('body_ar')->implode(' '));
    }
}
