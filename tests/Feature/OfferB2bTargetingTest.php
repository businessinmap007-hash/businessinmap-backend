<?php

namespace Tests\Feature;

use App\Models\CommercialOffer;
use App\Models\MenuItem;
use App\Models\CommercialOfferTarget;
use App\Models\OfferFollow;
use App\Models\OfferFollowNotification;
use App\Models\User;
use App\Services\Commercial\OfferAudience;
use App\Services\Commercial\OfferFollowMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «توجيه العروض B2B» — `commercial_offer_targets` has been written from the
 * admin since July and read by nothing, so an offer addressed to «شركات مواد
 * البناء» was shown to every passer-by.
 *
 * The rules these hold down:
 *   • an offer that names nobody is open (every existing row);
 *   • an offer that names somebody is invisible to everybody else — in the
 *     wall, in search, in the price comparison, in tracking, in notifications;
 *   • a keyword is not an audience;
 *   • `?audience_type=` narrows what you may see, it never widens it.
 *
 * Rolls back.
 */
class OfferB2bTargetingTest extends TestCase
{
    use DatabaseTransactions;

    private User $seller;
    private User $named;
    private User $stranger;
    private User $client;
    private int $offerableId;

    protected function setUp(): void
    {
        parent::setUp();

        // Three businesses in three different trades: the one selling, the one
        // the offer names, and one that is simply somebody else.
        $businesses = User::query()
            ->where('type', 'business')
            ->where('category_id', '>', 0)
            ->where('category_child_id', '>', 0)
            ->orderBy('id')
            ->limit(400)
            ->get(['id', 'name', 'type', 'category_id', 'category_child_id']);

        $distinct = $businesses->unique('category_child_id')->values();

        if ($distinct->count() < 3) {
            $this->markTestSkipped('Needs three businesses in three different trades.');
        }

        $this->seller = $distinct[0];
        $this->named = $distinct[1];
        $this->stranger = $distinct[2];

        $this->client = User::query()->where('type', 'client')->orderBy('id')->firstOrFail();

        $this->offerableId = 910000 + random_int(1000, 9999);
    }

    private function makeOffer(array $attributes = []): CommercialOffer
    {
        return CommercialOffer::create(array_merge([
            'offerable_type' => CommercialOffer::OFFERABLE_PRODUCT,
            'offerable_id' => $this->offerableId,
            'owner_business_id' => $this->seller->id,
            'seller_business_id' => $this->seller->id,
            'source_type' => CommercialOffer::SOURCE_DIRECT,
            'audience_type' => CommercialOffer::AUDIENCE_B2B,
            'title_ar' => 'عرض موجه',
            'title_en' => 'Directed offer',
            'base_price' => 100,
            'final_price' => 80,
            'currency' => 'EGP',
            'availability_mode' => CommercialOffer::AVAILABILITY_INSTANT,
            'status' => CommercialOffer::STATUS_ACTIVE,
        ], $attributes));
    }

    private function target(CommercialOffer $offer, string $type, ?int $id = null, ?string $keyword = null): void
    {
        CommercialOfferTarget::query()->create([
            'offer_id' => $offer->id,
            'target_type' => $type,
            'target_id' => $id,
            'keyword' => $keyword,
        ]);
    }

    /** @return int[] */
    private function wallIds(?User $viewer, string $query = '', ?int $offerableId = null): array
    {
        $viewer ? Sanctum::actingAs($viewer) : null;

        return collect(
            $this->getJson('/api/v2/offers?offerable_id=' . ($offerableId ?: $this->offerableId) . '&per_page=50' . $query)
                ->assertOk()
                ->json('data.offers.data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ── the default: nothing changes for an offer that names nobody ─────────

    public function test_an_offer_that_names_nobody_is_open(): void
    {
        $offer = $this->makeOffer();

        $this->assertContains($offer->id, $this->wallIds($this->stranger));
        $this->assertContains($offer->id, $this->wallIds($this->named));
    }

    // ── the direction itself ────────────────────────────────────────────────

    public function test_a_trade_target_hides_the_offer_from_every_other_trade(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_CATEGORY_CHILD, (int) $this->named->category_child_id);

        $this->assertContains($offer->id, $this->wallIds($this->named), 'The named trade must see it.');
        $this->assertNotContains($offer->id, $this->wallIds($this->stranger), 'Another trade must not.');
    }

    public function test_a_root_category_target_reaches_every_trade_inside_it(): void
    {
        $inside = User::query()
            ->where('type', 'business')
            ->where('category_id', (int) $this->named->category_id)
            ->where('id', '!=', $this->named->id)
            ->where('id', '!=', $this->seller->id)
            ->first();

        if (! $inside) {
            $this->markTestSkipped('Needs a second business under the same root category.');
        }

        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_CATEGORY, (int) $this->named->category_id);

        $this->assertContains($offer->id, $this->wallIds($this->named));
        $this->assertContains($offer->id, $this->wallIds($inside), 'A root target reaches the whole root.');
    }

    public function test_a_named_business_sees_it_and_nobody_else_does(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        $this->assertContains($offer->id, $this->wallIds($this->named));
        $this->assertNotContains($offer->id, $this->wallIds($this->stranger));
    }

    public function test_a_guest_never_sees_a_directed_offer(): void
    {
        $offer = $this->makeOffer(['audience_type' => CommercialOffer::AUDIENCE_BOTH]);
        $this->target($offer, CommercialOfferTarget::TARGET_CATEGORY_CHILD, (int) $this->named->category_child_id);

        $this->assertNotContains($offer->id, $this->wallIds(null), 'A guest is named by nothing.');
    }

    public function test_the_seller_keeps_sight_of_what_he_addressed_to_others(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        $this->assertContains($offer->id, $this->wallIds($this->seller));
    }

    public function test_a_keyword_is_not_an_audience(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_KEYWORD, null, 'خصم');

        $this->assertContains(
            $offer->id,
            $this->wallIds($this->stranger),
            'A keyword says what an offer is about, not who it is for.'
        );
    }

    public function test_a_user_type_target_names_a_kind_of_account(): void
    {
        $offer = $this->makeOffer(['audience_type' => CommercialOffer::AUDIENCE_BOTH]);
        $this->target($offer, CommercialOfferTarget::TARGET_USER_TYPE, null, 'business');

        $this->assertContains($offer->id, $this->wallIds($this->stranger));
        $this->assertNotContains($offer->id, $this->wallIds($this->client));
    }

    // ── «خاص»: named-only, never open ───────────────────────────────────────

    public function test_private_reaches_the_named_and_nobody_else(): void
    {
        $offer = $this->makeOffer(['audience_type' => CommercialOffer::AUDIENCE_PRIVATE]);
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        $this->assertContains($offer->id, $this->wallIds($this->named));
        $this->assertNotContains($offer->id, $this->wallIds($this->stranger));
        $this->assertNotContains($offer->id, $this->wallIds(null));
    }

    public function test_private_with_no_targets_is_addressed_to_nobody(): void
    {
        $offer = $this->makeOffer(['audience_type' => CommercialOffer::AUDIENCE_PRIVATE]);

        $this->assertNotContains($offer->id, $this->wallIds($this->named));
        $this->assertNotContains($offer->id, $this->wallIds($this->stranger));
        $this->assertNotContains($offer->id, $this->wallIds($this->client));
    }

    // ── the filter narrows, it never widens ─────────────────────────────────

    public function test_a_client_asking_for_the_wholesale_side_is_given_nothing(): void
    {
        $b2b = $this->makeOffer();

        $ids = $this->wallIds($this->client, '&audience_type=b2b');

        $this->assertNotContains($b2b->id, $ids, 'Asking for what is not yours returns nothing, not everything.');
    }

    public function test_the_empty_intersection_does_not_open_the_wall(): void
    {
        // The failure this guards: an «impossible» filter compiling to no SQL
        // at all, which shows every row instead of none.
        $b2c = $this->makeOffer(['audience_type' => CommercialOffer::AUDIENCE_B2C]);
        $b2b = $this->makeOffer();

        $ids = $this->wallIds($this->client, '&audience_type=b2b');

        $this->assertNotContains($b2b->id, $ids);
        $this->assertNotContains($b2c->id, $ids);
    }

    // ── the same rule on every other door ───────────────────────────────────

    public function test_search_enforces_the_same_direction(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        $read = function (User $viewer) {
            Sanctum::actingAs($viewer);

            return collect(
                $this->getJson('/api/v2/search/offers?business_id=' . $this->seller->id . '&per_page=50')
                    ->assertOk()
                    ->json('data.offers.data')
            )->pluck('id')->map(fn ($id) => (int) $id)->all();
        };

        $this->assertContains($offer->id, $read($this->named));
        $this->assertNotContains($offer->id, $read($this->stranger));
    }

    public function test_the_lowest_price_is_not_quoted_off_a_directed_offer(): void
    {
        $open = $this->makeOffer(['final_price' => 90]);
        $directed = $this->makeOffer(['final_price' => 40]);
        $this->target($directed, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        $lowest = function (User $viewer) {
            Sanctum::actingAs($viewer);

            return $this->getJson('/api/v2/offers/lowest?offerable_type=product&offerable_id=' . $this->offerableId)
                ->assertOk()
                ->json('data.lowest_price.id');
        };

        $this->assertSame($directed->id, (int) $lowest($this->named));
        $this->assertSame($open->id, (int) $lowest($this->stranger), 'A stranger is quoted the price he may have.');
    }

    public function test_tracking_an_offer_you_were_never_shown_is_a_404(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        Sanctum::actingAs($this->stranger);
        $this->postJson("/api/v2/offers/{$offer->id}/track", ['event_type' => 'view'])
            ->assertNotFound();

        Sanctum::actingAs($this->named);
        $this->postJson("/api/v2/offers/{$offer->id}/track", ['event_type' => 'view'])
            ->assertCreated();
    }

    public function test_show_hides_a_directed_offer_from_a_stranger(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_CATEGORY_CHILD, (int) $this->named->category_child_id);

        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/v2/offers/{$offer->id}")->assertNotFound();

        Sanctum::actingAs($this->named);
        $this->getJson("/api/v2/offers/{$offer->id}")->assertOk();
    }

    public function test_a_follower_the_offer_does_not_name_is_not_notified(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        // Both follow the same shop; only one of them is addressed.
        foreach ([$this->named, $this->stranger] as $follower) {
            OfferFollow::query()->create([
                'user_id' => $follower->id,
                'followable_type' => OfferFollow::FOLLOW_BUSINESS,
                'followable_id' => $this->seller->id,
                'is_active' => 1,
            ]);
        }

        app(OfferFollowMatchingService::class)->matchOffer($offer->fresh());

        $notified = OfferFollowNotification::query()
            ->where('offer_id', $offer->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $this->named->id, $notified);
        $this->assertNotContains(
            (int) $this->stranger->id,
            $notified,
            'A push telling you an offer exists has already shown it to you.'
        );
    }

    // ── the merchant's own door onto the direction ──────────────────────────

    /**
     * `business_offers` is switched off platform-wide, deliberately, and the
     * merchant must be subscribed to it. Both are true of the live database
     * and neither is what this file is about — a test that skipped on them
     * would report «the direction is saved» without having saved one. Flipped
     * inside the transaction, rolled back with everything else.
     */
    private function openTheOffersDoor(int $businessId): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'business_offers')->value('id');

        if ($serviceId <= 0) {
            $this->markTestSkipped('The business_offers service does not exist.');
        }

        DB::table('platform_services')->where('id', $serviceId)->update(['is_active' => 1]);

        DB::table('user_platform_service')->updateOrInsert(
            ['user_id' => $businessId, 'platform_service_id' => $serviceId],
            ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function sellersRow(float $price = 200): MenuItem
    {
        return MenuItem::create([
            'business_id' => $this->seller->id,
            'menu_section_id' => null,
            'item_type' => 'menu_food',
            'name_ar' => 'صنف موجه',
            'base_price' => $price,
            'is_active' => 1,
        ]);
    }

    public function test_a_merchant_addresses_his_own_offer_and_the_wall_obeys(): void
    {
        $this->openTheOffersDoor((int) $this->seller->id);
        Sanctum::actingAs($this->seller);

        $row = $this->sellersRow();

        $res = $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
            'final_price' => 150,
            'title_ar' => 'خصم موجه',
            'ends_at' => now()->addWeek()->toDateTimeString(),
            'target_businesses' => [(int) $this->named->id],
        ]);

        if (in_array($res->status(), [403, 429], true)) {
            $this->markTestSkipped('The offers door refused for a reason of its own.');
        }

        $res->assertCreated()->assertJsonPath('data.targets.businesses', [(int) $this->named->id]);

        $offerId = (int) $res->json('data.offer.id');

        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/v2/offers/{$offerId}")->assertNotFound();

        Sanctum::actingAs($this->named);
        $this->getJson("/api/v2/offers/{$offerId}")->assertOk();
    }

    public function test_an_emptied_audience_reopens_the_offer(): void
    {
        $this->openTheOffersDoor((int) $this->seller->id);

        $row = $this->sellersRow();

        $offer = $this->makeOffer([
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
        ]);
        $this->target($offer, CommercialOfferTarget::TARGET_BUSINESS, (int) $this->named->id);

        $this->assertNotContains($offer->id, $this->wallIds($this->stranger, '', (int) $row->id));

        Sanctum::actingAs($this->seller);
        // `update` re-states the whole offer — it is a PUT wearing PATCH's
        // name — so the row and the price travel with the emptied audience.
        $res = $this->patchJson("/api/v2/business/offers/{$offer->id}", [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
            'final_price' => 150,
            // Every offer states an end — the honesty rule, still standing.
            'ends_at' => now()->addWeek()->toDateTimeString(),
            'target_businesses' => [],
        ]);

        if (in_array($res->status(), [403, 429], true)) {
            $this->markTestSkipped('The offers door refused for a reason of its own.');
        }

        $res->assertOk();

        $this->assertContains(
            $offer->id,
            $this->wallIds($this->stranger, '', (int) $row->id),
            'Naming nobody is «للجميع» — a merchant must be able to take the direction back off.'
        );
    }

    public function test_an_offer_cannot_be_addressed_to_an_account_that_does_not_exist(): void
    {
        $this->openTheOffersDoor((int) $this->seller->id);
        Sanctum::actingAs($this->seller);

        $row = $this->sellersRow();

        $res = $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
            'final_price' => 150,
            'title_ar' => 'خصم موجه',
            'ends_at' => now()->addWeek()->toDateTimeString(),
            'target_businesses' => [99999999],
        ]);

        if (in_array($res->status(), [403, 429], true)) {
            $this->markTestSkipped('The offers door refused for a reason of its own.');
        }

        $res->assertStatus(422)->assertJsonValidationErrors('target_businesses.0');
    }

    // ── the writer ──────────────────────────────────────────────────────────

    public function test_the_service_and_the_wall_agree_on_one_offer(): void
    {
        $offer = $this->makeOffer();
        $this->target($offer, CommercialOfferTarget::TARGET_CATEGORY_CHILD, (int) $this->named->category_child_id);

        $audience = app(OfferAudience::class);

        $this->assertTrue($audience->canSee($offer, $this->named));
        $this->assertFalse($audience->canSee($offer, $this->stranger));
        $this->assertFalse($audience->canSee($offer, null));
        $this->assertTrue($audience->canSee($offer, $this->seller), 'His own offer.');
    }
}
