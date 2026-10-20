<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\OwnerListing;
use App\Models\OwnerListingInquiry;
use App\Models\PlatformEventFee;
use App\Models\Thread;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\OwnerListings\OwnerListingService;
use App\Services\OwnerListings\PhoneNumberGuard;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «إعلان من المالك» (owner, 2026-10-26): any account lists ONE item per category — a car, a flat, any used thing — for
 * sale, rent or swap, with no business account behind it. It disappears three days after it is sold or rented. The seller's
 * number is never shown; buyer and seller talk in the app, the chat refuses a phone number, and when they agree either asks to
 * swap numbers and the platform's fee is taken. Rolls back.
 */
class OwnerListingsTest extends TestCase
{
    use DatabaseTransactions;

    private int $gov;
    private int $city;

    protected function setUp(): void
    {
        parent::setUp();

        $c = DB::table('cities')->orderBy('id')->first();
        $this->gov = (int) $c->governorate_id;
        $this->city = (int) $c->id;
    }

    private function user(string $tag, string $type = User::TYPE_CLIENT, array $extra = []): User
    {
        $u = new User();
        $u->forceFill($extra + [
            'name' => $tag . ' Name ' . Str::random(4),
            'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => 'secret-password',
            'type' => $type,
            'api_token' => Str::random(80),
        ])->save();

        return $u;
    }

    private function carBody(array $extra = []): array
    {
        return $extra + [
            'category' => 'car',
            'deal' => 'sale',
            'title' => 'Kia Cerato 2021',
            'price' => 640000,
            'governorate_id' => $this->gov,
            'city_id' => $this->city,
            'attributes' => [
                'vehicle_type' => (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'نوع المركبة')->value('o.id'),
                'brand' => (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'ماركات السيارات')->value('o.id'),
                'year' => 2021,
                'mileage_km' => 48000,
                'transmission' => 'automatic',
            ],
        ];
    }

    private function withPhoto(OwnerListing $l): OwnerListing
    {
        $l->images()->create(['image' => 'files/uploads/zz-test-' . uniqid() . '.jpg']);

        return $l->refresh();
    }

    private function listed(User $u, ?array $body = null): OwnerListing
    {
        Sanctum::actingAs($u);
        $id = $this->postJson('/api/v2/owner-listings', $body ?? $this->carBody())->assertCreated()->json('data.listing.id');

        return OwnerListing::findOrFail($id);
    }

    /** Price the swap in the control panel: a percent of the listing price per side (null = that side pays nothing). */
    private function priceSwap(?float $buyerPercent, ?float $sellerPercent, float $min = 20, float $max = 500): void
    {
        foreach (['buyer' => $buyerPercent, 'seller' => $sellerPercent] as $role => $percent) {
            PlatformEventFee::query()->updateOrCreate(
                ['event_key' => PlatformEventFeeService::OWNER_LISTING_CONTACT, 'role' => $role],
                ['enabled' => $percent !== null, 'calc_type' => 'percent', 'amount' => $percent ?? 0, 'min_amount' => $percent === null ? null : $min, 'max_amount' => $percent === null ? null : $max]
            );
        }
    }

    private function fund(User $u, float $amount): void
    {
        app(WalletService::class)->deposit($u->id, $amount, 'test');
    }

    // ── posting ──────────────────────────────────────────────────────────────

    public function test_the_categories_come_with_their_fields_and_the_car_lists_its_makes(): void
    {
        Sanctum::actingAs($this->user('cust'));
        $cats = collect($this->getJson('/api/v2/owner-listings/categories')->assertOk()->json('data.categories'));

        $this->assertEqualsCanonicalizing(['car', 'property', 'phones', 'computers', 'appliances', 'furniture', 'clothes', 'kids', 'sports', 'tools', 'other'], $cats->pluck('key')->all());
        $car = $cats->firstWhere('key', 'car');
        $this->assertSame(['sale', 'rent', 'swap'], $car['deals']);
        $brand = collect($car['fields'])->firstWhere('key', 'brand');
        $this->assertGreaterThan(10, count($brand['options']));
        $this->assertSame(['sale', 'swap'], $cats->firstWhere('key', 'phones')['deals'], 'a phone is sold or swapped, not let');
    }

    public function test_any_account_posts_one_item_per_category_and_a_doctor_may_sell_his_car(): void
    {
        $doctor = $this->user('doc', User::TYPE_BUSINESS, ['category_id' => 20, 'category_child_id' => (int) DB::table('category_children_master')->value('id')]);

        $car = $this->listed($doctor);
        $this->assertSame('active', $car->status);
        $this->assertSame($doctor->id, (int) $car->user_id);

        // a second CAR is refused; a flat is another category
        $this->postJson('/api/v2/owner-listings', $this->carBody(['title' => 'Another car']))->assertStatus(422)->assertJsonValidationErrors('category');
        $this->postJson('/api/v2/owner-listings', [
            'category' => 'property', 'deal' => 'rent', 'title' => 'Flat in Nasr City', 'price' => 6000, 'price_unit' => 'month',
            'governorate_id' => $this->gov, 'attributes' => ['property_type' => 'apartment', 'area_m2' => 120, 'rooms' => 3, 'furnished' => 'yes'],
        ])->assertCreated();
        $this->postJson('/api/v2/owner-listings', [
            'category' => 'phones', 'deal' => 'sale', 'title' => 'iPhone 13', 'price' => 18000, 'governorate_id' => $this->gov, 'attributes' => ['condition' => 'used'],
        ])->assertCreated();
    }

    public function test_the_deal_the_price_and_the_attributes_are_checked(): void
    {
        Sanctum::actingAs($this->user('cust'));

        // a phone cannot be let; a rent needs its period; a sale needs a price; a swap does not
        $this->postJson('/api/v2/owner-listings', ['category' => 'phones', 'deal' => 'rent', 'title' => 'iPhone', 'price' => 100, 'price_unit' => 'day', 'governorate_id' => $this->gov, 'attributes' => ['condition' => 'used']])->assertStatus(422)->assertJsonValidationErrors('deal');
        $this->postJson('/api/v2/owner-listings', $this->carBody(['deal' => 'rent']))->assertStatus(422)->assertJsonValidationErrors('price_unit');
        $this->postJson('/api/v2/owner-listings', $this->carBody(['price' => null]))->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson('/api/v2/owner-listings', $this->carBody(['deal' => 'swap', 'price' => null]))->assertCreated();

        // an unknown make and a year out of range are refused; an unknown key is dropped
        Sanctum::actingAs($this->user('cust2'));
        $bad = $this->carBody();
        $bad['attributes']['brand'] = 999999999;
        $this->postJson('/api/v2/owner-listings', $bad)->assertStatus(422)->assertJsonValidationErrors('attributes.brand');
        $bad = $this->carBody();
        $bad['attributes']['year'] = 1800;
        $this->postJson('/api/v2/owner-listings', $bad)->assertStatus(422)->assertJsonValidationErrors('attributes.year');
        $ok = $this->carBody();
        $ok['attributes']['secret_field'] = 'x';
        $id = $this->postJson('/api/v2/owner-listings', $ok)->assertCreated()->json('data.listing.id');
        $this->assertArrayNotHasKey('secret_field', (array) OwnerListing::find($id)->attributes);
    }

    // ── what people see ──────────────────────────────────────────────────────

    public function test_a_listing_shows_only_with_a_photo_and_never_with_the_owners_phone(): void
    {
        $owner = $this->user('own');
        $listing = $this->listed($owner);

        Sanctum::actingAs($this->user('buyer'));
        $this->assertNull(collect($this->getJson('/api/v2/owner-listings')->json('data.data'))->firstWhere('id', $listing->id), 'no photo, not shown');
        $this->getJson("/api/v2/owner-listings/{$listing->id}")->assertStatus(404);

        $this->withPhoto($listing);
        $row = collect($this->getJson('/api/v2/owner-listings?category=car')->json('data.data'))->firstWhere('id', $listing->id);
        $this->assertNotNull($row);
        $detail = $this->getJson("/api/v2/owner-listings/{$listing->id}")->assertOk();
        $this->assertStringNotContainsString($owner->phone, json_encode($detail->json()), 'the number is nowhere in the listing');
        $this->assertSame($owner->id, $detail->json('data.listing.owner.id'));
        $this->assertNotEmpty($detail->json('data.listing.attributes'));

        // the owner finds his own in «mine» whatever its state
        Sanctum::actingAs($owner);
        $this->assertSame($listing->id, $this->getJson('/api/v2/owner-listings/mine')->json('data.results.0.id'));
    }

    // ── the end of a listing ─────────────────────────────────────────────────

    public function test_sold_is_hidden_at_once_and_deleted_three_days_later_with_its_photos_and_chats(): void
    {
        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));
        $buyer = $this->user('buyer');
        Sanctum::actingAs($buyer);
        $inquiry = $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertCreated()->json('data.inquiry.id');
        $this->getJson("/api/v2/operation-chats/listing/{$inquiry}")->assertOk();
        $this->assertSame(1, Thread::where('subject_type', (new OwnerListingInquiry())->getMorphClass())->where('subject_id', $inquiry)->count());

        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/finish", ['reason' => 'rented'])->assertStatus(422);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/finish", ['reason' => 'sold'])->assertOk();

        $listing->refresh();
        $this->assertSame('done', $listing->status);
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $listing->purge_at->timestamp, 5);

        Sanctum::actingAs($buyer);
        $this->getJson("/api/v2/owner-listings/{$listing->id}")->assertStatus(404);
        $this->assertNull(collect($this->getJson('/api/v2/owner-listings')->json('data.data'))->firstWhere('id', $listing->id));

        // two days later: still there (the deal may need finishing); after three: gone, photos and chat too
        app(OwnerListingService::class)->sweep();
        $this->assertNotNull(OwnerListing::find($listing->id));
        OwnerListing::whereKey($listing->id)->update(['purge_at' => now()->subMinute()]);
        $this->assertSame(1, app(OwnerListingService::class)->sweep()['purged']);

        $this->assertNull(OwnerListing::find($listing->id));
        $this->assertSame(0, DB::table('images')->where('imageable_type', $listing->getMorphClass())->where('imageable_id', $listing->id)->count());
        $this->assertSame(0, OwnerListingInquiry::where('owner_listing_id', $listing->id)->count());
        $this->assertSame(0, Thread::where('subject_type', (new OwnerListingInquiry())->getMorphClass())->where('subject_id', $inquiry)->count());
    }

    public function test_an_old_listing_expires_and_can_be_renewed_and_a_new_one_may_follow_a_finished_one(): void
    {
        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));
        OwnerListing::whereKey($listing->id)->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(OwnerListingService::class)->sweep()['expired']);
        $this->assertSame('expired', $listing->refresh()->status);
        $this->assertNotNull($listing->purge_at);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/renew")->assertOk();
        $listing->refresh();
        $this->assertSame('active', $listing->status);
        $this->assertNull($listing->purge_at);
        $this->assertTrue($listing->expires_at->isFuture());

        // finished: the place in the category is free again at once
        $this->postJson("/api/v2/owner-listings/{$listing->id}/finish", ['reason' => 'withdrawn'])->assertOk();
        $this->postJson('/api/v2/owner-listings', $this->carBody(['title' => 'A better car']))->assertCreated();
    }

    public function test_three_different_reports_hide_a_listing_and_one_person_counts_once(): void
    {
        $listing = $this->withPhoto($this->listed($this->user('own')));

        $reporter = $this->user('rep1');
        Sanctum::actingAs($reporter);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/report", ['reason' => 'fake'])->assertOk();
        $this->postJson("/api/v2/owner-listings/{$listing->id}/report")->assertOk();
        $this->assertSame(1, $listing->refresh()->reports_count);

        foreach (['rep2', 'rep3'] as $tag) {
            Sanctum::actingAs($this->user($tag));
            $this->postJson("/api/v2/owner-listings/{$listing->id}/report")->assertOk();
        }

        $this->assertSame('flagged', $listing->refresh()->status);
        Sanctum::actingAs($this->user('buyer'));
        $this->getJson("/api/v2/owner-listings/{$listing->id}")->assertStatus(404);
    }

    // ── the chat and the swap of numbers ─────────────────────────────────────

    public function test_the_phone_guard_catches_the_usual_disguises_and_leaves_prices_alone(): void
    {
        foreach ([
            '01012345678', '٠١٠١٢٣٤٥٦٧٨', '010 1234 5678', '010-1234-5678', '+201012345678', '0020 101 234 5678', '0 1 0 1 2 3 4 5 6 7 8',
            'كلمني على 01012345678 ضروري', 'صفر واحد صفر واحد اتنين تلاتة اربعة خمسة ستة سبعة تمانية', '01O12345678', '0222345678',
        ] as $text) {
            $this->assertTrue(PhoneNumberGuard::contains($text), $text);
        }

        foreach (['السعر 1500000 جنيه', 'موديل 2021 ومشى 48000 كم', 'عايز 640,000 نهائي', 'الدور الخامس وفيها 3 غرف', 'هاتيها بـ 25000'] as $text) {
            $this->assertFalse(PhoneNumberGuard::contains($text), $text);
        }
    }

    public function test_the_chat_refuses_a_phone_number_until_the_numbers_are_swapped(): void
    {
        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));
        $buyer = $this->user('buyer');
        Sanctum::actingAs($buyer);
        $inquiry = $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertCreated()->json('data.inquiry.id');

        $this->postJson("/api/v2/operation-chats/listing/{$inquiry}/messages", ['body' => 'Is it still available? I can pay 600000'])->assertCreated();
        $this->postJson("/api/v2/operation-chats/listing/{$inquiry}/messages", ['body' => 'call me 010 1234 5678'])->assertStatus(422)->assertJsonValidationErrors('body');

        $this->fund($buyer, 10000);
        $this->fund($owner, 10000);
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/request")->assertOk();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/accept")->assertOk()->assertJsonPath('data.inquiry.status', 'revealed');

        // numbers swapped: the chat no longer needs to hide them
        $this->postJson("/api/v2/operation-chats/listing/{$inquiry}/messages", ['body' => 'my other number 010 1234 5678'])->assertCreated();
    }

    public function test_the_chat_is_closed_to_strangers(): void
    {
        $listing = $this->withPhoto($this->listed($this->user('own')));
        Sanctum::actingAs($this->user('buyer'));
        $inquiry = $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertCreated()->json('data.inquiry.id');

        Sanctum::actingAs($this->user('stranger'));
        $this->getJson("/api/v2/operation-chats/listing/{$inquiry}")->assertStatus(404);
        $this->getJson("/api/v2/owner-listing-inquiries/{$inquiry}")->assertStatus(404);
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/request")->assertStatus(404);
    }

    public function test_the_fee_is_split_between_the_two_taken_only_when_both_said_yes_and_the_numbers_appear_only_then(): void
    {
        $this->priceSwap(0.5, 0.5, 20, 250);

        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));            // 640,000 → 0.5% = 3,200 → capped at 250 each side
        $buyer = $this->user('buyer');
        $this->fund($owner, 1000);
        $this->fund($buyer, 1000);
        $wallets = app(WalletService::class);

        Sanctum::actingAs($buyer);
        $inquiry = $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertCreated()->json('data.inquiry.id');
        $quote = $this->getJson("/api/v2/owner-listing-inquiries/{$inquiry}")->assertOk()->json('data.inquiry');
        $this->assertEquals(500, $quote['reveal']['fee_total']);
        $this->assertEquals(250, $quote['reveal']['my_share']);
        $this->assertNull($quote['other_phone']);

        // asking costs nothing and shows nothing
        $asked = $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/request")->assertOk();
        $this->assertTrue($asked->json('data.inquiry.reveal.requested_by_me'));
        $this->assertNull($asked->json('data.inquiry.other_phone'));
        $this->assertEquals(1000, (float) $wallets->getOrCreateWallet($buyer->id)->balance);
        // the asker cannot accept his own request
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/accept")->assertStatus(422);

        Sanctum::actingAs($owner);
        $seen = $this->getJson("/api/v2/owner-listing-inquiries/{$inquiry}")->assertOk()->json('data.inquiry');
        $this->assertTrue($seen['reveal']['requested_by_other']);

        $done = $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/accept")->assertOk();
        $this->assertSame('revealed', $done->json('data.inquiry.status'));
        $this->assertSame($buyer->phone, $done->json('data.inquiry.other_phone'));
        $this->assertEquals(750, (float) $wallets->getOrCreateWallet($owner->id)->balance);
        $this->assertEquals(750, (float) $wallets->getOrCreateWallet($buyer->id)->balance);

        Sanctum::actingAs($buyer);
        $this->assertSame($owner->phone, $this->getJson("/api/v2/owner-listing-inquiries/{$inquiry}")->json('data.inquiry.other_phone'));

        // accepting again takes nothing more
        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/accept")->assertOk();
        $this->assertEquals(750, (float) $wallets->getOrCreateWallet($owner->id)->balance);
    }

    public function test_a_party_who_cannot_pay_stops_the_swap_and_a_decline_puts_it_back(): void
    {
        $this->priceSwap(0.5, 0.5, 20, 250);

        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));
        $buyer = $this->user('buyer');
        $this->fund($buyer, 1000);          // the owner's wallet is empty

        Sanctum::actingAs($buyer);
        $inquiry = $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertCreated()->json('data.inquiry.id');
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/request")->assertOk();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/accept")->assertStatus(422)->assertJsonValidationErrors('balance');
        $this->assertSame('reveal_requested', OwnerListingInquiry::find($inquiry)->status);
        $this->assertEquals(1000, (float) app(WalletService::class)->getOrCreateWallet($buyer->id)->balance, 'nothing was taken from the buyer');

        // not yet: the request goes away and they may keep talking
        $this->postJson("/api/v2/owner-listing-inquiries/{$inquiry}/reveal/cancel")->assertOk()->assertJsonPath('data.inquiry.status', 'open');
    }

    public function test_the_fee_follows_the_price_between_its_floor_and_its_ceiling_and_the_payer_setting(): void
    {
        $owner = $this->user('own');
        $cheap = $this->withPhoto($this->listed($owner, [
            'category' => 'phones', 'deal' => 'sale', 'title' => 'Old phone', 'price' => 1000, 'governorate_id' => $this->gov, 'attributes' => ['condition' => 'used'],
        ]));
        $mid = $this->withPhoto($this->listed($owner, [
            'category' => 'furniture', 'deal' => 'sale', 'title' => 'Bedroom', 'price' => 30000, 'governorate_id' => $this->gov, 'attributes' => ['condition' => 'used'],
        ]));
        $buyer = $this->user('buyer');

        $this->priceSwap(null, 1.0);
        Sanctum::actingAs($buyer);
        $a = $this->postJson("/api/v2/owner-listings/{$cheap->id}/inquiries")->json('data.inquiry.id');
        $b = $this->postJson("/api/v2/owner-listings/{$mid->id}/inquiries")->json('data.inquiry.id');

        $this->assertEquals(20, $this->getJson("/api/v2/owner-listing-inquiries/{$a}")->json('data.inquiry.reveal.fee_total'), '1% of 1000 is 10: the floor is 20');
        $this->assertEquals(300, $this->getJson("/api/v2/owner-listing-inquiries/{$b}")->json('data.inquiry.reveal.fee_total'));
        $this->assertEquals(0, $this->getJson("/api/v2/owner-listing-inquiries/{$b}")->json('data.inquiry.reveal.my_share'), 'the seller pays: the buyer owes nothing');
        Sanctum::actingAs($owner);
        $this->assertEquals(300, $this->getJson("/api/v2/owner-listing-inquiries/{$b}")->json('data.inquiry.reveal.my_share'));
    }

    public function test_only_the_owner_changes_a_listing_and_a_buyer_cannot_inquire_about_his_own_or_a_finished_one(): void
    {
        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));

        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertStatus(422);
        $this->putJson("/api/v2/owner-listings/{$listing->id}", ['title' => 'Kia Cerato 2021 — price dropped', 'price' => 600000])->assertOk()->assertJsonPath('data.listing.price', 600000);

        Sanctum::actingAs($this->user('other'));
        $this->putJson("/api/v2/owner-listings/{$listing->id}", ['title' => 'Hacked'])->assertStatus(404);
        $this->deleteJson("/api/v2/owner-listings/{$listing->id}")->assertStatus(404);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/finish", ['reason' => 'sold'])->assertStatus(404);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/finish", ['reason' => 'sold'])->assertOk();
        Sanctum::actingAs($this->user('late'));
        $this->postJson("/api/v2/owner-listings/{$listing->id}/inquiries")->assertStatus(422);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v2/owner-listings/{$listing->id}")->assertOk();
        $this->assertNull(OwnerListing::find($listing->id));
    }

    public function test_the_owner_is_told_once_before_the_listing_ends_and_again_when_it_has(): void
    {
        $owner = $this->user('own');
        $listing = $this->withPhoto($this->listed($owner));
        $count = fn (string $event) => AppNotification::where('user_id', $owner->id)->where('source_type', $event)->where('source_id', $listing->id)->count();

        $this->artisan('owner-listings:sweep')->assertSuccessful();
        $this->assertSame(0, $count('owner_listing_expiring'), 'two months to go');

        $listing->update(['expires_at' => now()->addDays(2)]);
        $this->artisan('owner-listings:sweep')->assertSuccessful();
        $this->artisan('owner-listings:sweep')->assertSuccessful();
        $this->assertSame(1, $count('owner_listing_expiring'), 'told once, not on every hourly run');

        // renewing starts a new run of days: it is announced again when that one nears its end
        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/owner-listings/{$listing->id}/renew")->assertOk();
        $listing->refresh()->update(['expires_at' => now()->addDays(1)]);
        $this->artisan('owner-listings:sweep')->assertSuccessful();
        $this->assertSame(2, $count('owner_listing_expiring'));

        $listing->update(['expires_at' => now()->subMinute()]);
        $this->artisan('owner-listings:sweep')->assertSuccessful();
        $this->assertSame(1, $count('owner_listing_expired'));
        $this->assertSame(OwnerListing::STATUS_EXPIRED, $listing->refresh()->status);
    }

    /** The seriousness deposit as the control panel sets it (null = switched off). */
    private function priceDeposit(?float $amount): void
    {
        PlatformEventFee::query()->updateOrCreate(
            ['event_key' => PlatformEventFeeService::OWNER_LISTING_DEPOSIT, 'role' => 'owner'],
            ['enabled' => $amount !== null, 'calc_type' => 'fixed', 'amount' => $amount ?? 0, 'min_amount' => null, 'max_amount' => null]
        );
    }

    private function giveBronze(User $u): void
    {
        $level = GuaranteeLevel::query()->where('code', 'bronze')->where('target_type', GuaranteeLevel::TARGET_CLIENT)->firstOrFail();

        UserGuarantee::create([
            'user_id' => $u->id, 'target_type' => GuaranteeLevel::TARGET_CLIENT, 'purchased_level_id' => $level->id,
            'effective_level_id' => $level->id, 'status' => UserGuarantee::STATUS_ACTIVE, 'current_coverage_amount' => 1000, 'used_coverage_amount' => 0,
        ]);
    }

    public function test_with_the_condition_off_anyone_publishes_and_the_app_is_told_nothing_is_needed(): void
    {
        $this->priceDeposit(null);
        $u = $this->user('own');
        Sanctum::actingAs($u);

        $this->getJson('/api/v2/owner-listings/requirements')->assertOk()->assertJsonPath('data.requirements.required', false)->assertJsonPath('data.requirements.needs_deposit', false);
        $this->postJson('/api/v2/owner-listings', $this->carBody())->assertCreated();
    }

    public function test_without_a_bronze_guarantee_the_listing_needs_a_deposit_that_is_held_then_handed_back_on_finishing(): void
    {
        $this->priceDeposit(100);
        $u = $this->user('own');
        $wallets = app(WalletService::class);
        Sanctum::actingAs($u);

        $req = $this->getJson('/api/v2/owner-listings/requirements')->assertOk()->json('data.requirements');
        $this->assertTrue($req['needs_deposit']);
        $this->assertEquals(100, $req['deposit_amount']);
        $this->assertFalse($req['guarantee_ok']);

        // no consent -> refused with what to do; consent but an empty wallet -> refused; nothing is created either way
        $this->postJson('/api/v2/owner-listings', $this->carBody())->assertStatus(422)->assertJsonValidationErrors('seriousness');
        $this->postJson('/api/v2/owner-listings', $this->carBody() + ['hold_deposit' => true])->assertStatus(422)->assertJsonValidationErrors('balance');
        $this->assertSame(0, OwnerListing::where('user_id', $u->id)->count());

        $this->fund($u, 300);
        $id = $this->postJson('/api/v2/owner-listings', $this->carBody() + ['hold_deposit' => true])->assertCreated()
            ->assertJsonPath('data.listing.deposit.state', 'held')->json('data.listing.id');
        $wallet = $wallets->getOrCreateWallet($u->id)->refresh();
        $this->assertEquals(200, (float) $wallet->balance);
        $this->assertEquals(100, (float) $wallet->locked_balance);

        $this->postJson("/api/v2/owner-listings/{$id}/finish", ['reason' => 'sold'])->assertOk();
        $wallet = $wallets->getOrCreateWallet($u->id)->refresh();
        $this->assertEquals(300, (float) $wallet->balance, 'handed back on the sale');
        $this->assertEquals(0, (float) $wallet->locked_balance);
        $this->assertSame('released', OwnerListing::find($id)->deposit_state);

        // finishing again hands back nothing more
        $this->postJson("/api/v2/owner-listings/{$id}/finish", ['reason' => 'sold'])->assertOk();
        $this->assertEquals(300, (float) $wallets->getOrCreateWallet($u->id)->refresh()->balance);
    }

    public function test_a_bronze_guarantee_stands_behind_the_listing_so_no_cash_deposit_is_taken(): void
    {
        $this->priceDeposit(100);
        $u = $this->user('own');
        $this->giveBronze($u);
        Sanctum::actingAs($u);

        $req = $this->getJson('/api/v2/owner-listings/requirements')->json('data.requirements');
        $this->assertTrue($req['guarantee_ok']);
        $this->assertFalse($req['needs_deposit']);

        // the stake is the frozen guarantee coverage: nothing is locked in the wallet
        $this->postJson('/api/v2/owner-listings', $this->carBody())->assertCreated()->assertJsonPath('data.listing.deposit.state', 'held');
        $this->assertEquals(0, (float) app(WalletService::class)->getOrCreateWallet($u->id)->locked_balance);
    }

    public function test_the_deposit_also_goes_back_when_the_owner_deletes_the_listing_when_it_expires_and_when_a_moderator_removes_it(): void
    {
        $this->priceDeposit(50);
        $wallets = app(WalletService::class);
        $balance = fn (User $u) => (float) $wallets->getOrCreateWallet($u->id)->refresh()->balance;
        $locked = fn (User $u) => (float) $wallets->getOrCreateWallet($u->id)->refresh()->locked_balance;

        // deleted by the owner
        $a = $this->user('a');
        $this->fund($a, 100);
        Sanctum::actingAs($a);
        $idA = $this->postJson('/api/v2/owner-listings', $this->carBody() + ['hold_deposit' => true])->assertCreated()->json('data.listing.id');
        $this->deleteJson("/api/v2/owner-listings/{$idA}")->assertOk();
        $this->assertEquals([100, 0], [$balance($a), $locked($a)]);

        // expired: back at once; renewing it asks for the condition again
        $b = $this->user('b');
        $this->fund($b, 100);
        Sanctum::actingAs($b);
        $idB = $this->postJson('/api/v2/owner-listings', $this->carBody() + ['hold_deposit' => true])->assertCreated()->json('data.listing.id');
        OwnerListing::whereKey($idB)->update(['expires_at' => now()->subMinute()]);
        $this->artisan('owner-listings:sweep')->assertSuccessful();
        $this->assertEquals([100, 0], [$balance($b), $locked($b)]);
        $this->postJson("/api/v2/owner-listings/{$idB}/renew")->assertOk();
        $this->assertEquals([50, 50], [$balance($b), $locked($b)], 'held again for the new run of days');

        // removed by a moderator: handed back, not confiscated
        $c = $this->user('c');
        $this->fund($c, 100);
        Sanctum::actingAs($c);
        $idC = $this->postJson('/api/v2/owner-listings', $this->carBody() + ['hold_deposit' => true])->assertCreated()->json('data.listing.id');
        $reports = app(\App\Services\Moderation\ContentReportService::class);
        $reports->report($this->user('rep'), 'owner_listing', $idC, 'fraud');
        // a moderator who removes it WITHOUT confiscating hands it back
        $reports->remove('owner_listing', $idC, $this->user('mod'), 'wrong_info', false);
        $this->assertEquals([100, 0], [$balance($c), $locked($c)]);
    }

    public function test_a_listing_can_be_closed_on_its_city_or_governorate_and_the_filters_narrow_the_search(): void
    {
        $this->priceDeposit(null);
        $other = DB::table('cities')->where('governorate_id', '!=', $this->gov)->orderBy('id')->first();
        $sibling = DB::table('cities')->where('governorate_id', $this->gov)->where('id', '!=', $this->city)->orderBy('id')->first();
        $this->assertNotNull($other);

        $owner = $this->user('own', User::TYPE_CLIENT, ['governorate_id' => $this->gov, 'city_id' => $this->city]);
        $local = $this->user('local', User::TYPE_CLIENT, ['governorate_id' => $this->gov, 'city_id' => $this->city]);
        $neighbour = $this->user('neighbour', User::TYPE_CLIENT, ['governorate_id' => $this->gov, 'city_id' => $sibling?->id ?? $this->city]);
        $far = $this->user('far', User::TYPE_CLIENT, ['governorate_id' => $other->governorate_id, 'city_id' => $other->id]);
        $nowhere = $this->user('nowhere');

        $everyone = $this->withPhoto($this->listed($owner, $this->carBody(['title' => 'Open to all'])));
        $cityOnly = $this->withPhoto($this->listed($owner, [
            'category' => 'phones', 'deal' => 'sale', 'title' => 'City only phone', 'price' => 1000, 'governorate_id' => $this->gov, 'city_id' => $this->city,
            'visibility' => 'city', 'attributes' => ['condition' => 'used'],
        ]));
        $govOnly = $this->withPhoto($this->listed($owner, [
            'category' => 'furniture', 'deal' => 'sale', 'title' => 'Governorate only sofa', 'price' => 3000, 'governorate_id' => $this->gov, 'city_id' => $this->city,
            'visibility' => 'governorate', 'attributes' => ['condition' => 'used'],
        ]));
        $this->assertSame('city', $cityOnly->visibility);

        $titles = function (User $viewer, string $query = '') {
            Sanctum::actingAs($viewer);

            return collect($this->getJson('/api/v2/owner-listings?per_page=50' . $query)->assertOk()->json('data.data'))->pluck('title')->all();
        };

        $this->assertEqualsCanonicalizing(['Open to all', 'City only phone', 'Governorate only sofa'], array_intersect($titles($local), ['Open to all', 'City only phone', 'Governorate only sofa']));
        $this->assertContains('Governorate only sofa', $titles($neighbour), 'same governorate, another city');
        $this->assertNotContains('City only phone', $titles($neighbour));
        if ($sibling) {
            $this->assertNotContains('City only phone', $titles($neighbour));
        }
        $this->assertSame(['Open to all'], array_values(array_intersect($titles($far), ['Open to all', 'City only phone', 'Governorate only sofa'])));
        $this->assertSame(['Open to all'], array_values(array_intersect($titles($nowhere), ['Open to all', 'City only phone', 'Governorate only sofa'])), 'no registered place: unrestricted only');

        // direct links and conversations follow the same rule; the owner always sees his own
        Sanctum::actingAs($far);
        $this->getJson("/api/v2/owner-listings/{$cityOnly->id}")->assertStatus(404);
        $this->postJson("/api/v2/owner-listings/{$cityOnly->id}/inquiries")->assertStatus(422);
        Sanctum::actingAs($local);
        $this->getJson("/api/v2/owner-listings/{$cityOnly->id}")->assertOk()->assertJsonPath('data.listing.visibility', 'city');
        Sanctum::actingAs($owner);
        $this->getJson("/api/v2/owner-listings/{$cityOnly->id}")->assertOk();

        // the search filters: by governorate, then by city
        $this->assertContains('Open to all', $titles($local, '&governorate_id=' . $this->gov));
        $this->assertNotContains('Open to all', $titles($local, '&governorate_id=' . $other->governorate_id));
        $this->assertContains('City only phone', $titles($local, '&city_id=' . $this->city));

        // «لمدينتي فقط» needs a city; the owner can change the scope later
        Sanctum::actingAs($owner);
        $noCity = $this->carBody(['category' => 'tools', 'visibility' => 'city']);
        unset($noCity['city_id']);
        $this->postJson('/api/v2/owner-listings', ['category' => 'other', 'deal' => 'sale', 'title' => 'Nothing special', 'price' => 10, 'governorate_id' => $this->gov, 'visibility' => 'city'])
            ->assertStatus(422)->assertJsonValidationErrors('visibility');
        $this->putJson("/api/v2/owner-listings/{$cityOnly->id}", ['visibility' => 'all'])->assertOk()->assertJsonPath('data.listing.visibility', 'all');
        $this->assertContains('City only phone', $titles($far));
    }

    public function test_a_moderators_removal_for_a_violation_confiscates_the_deposit_and_tells_the_owner_why(): void
    {
        $this->priceDeposit(50);
        $owner = $this->user('own');
        $this->fund($owner, 100);
        $wallets = app(WalletService::class);
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v2/owner-listings', $this->carBody() + ['hold_deposit' => true])->assertCreated()->json('data.listing.id');

        // no rule named -> refused; the listing and the deposit stay
        $reports = app(\App\Services\Moderation\ContentReportService::class);
        $reports->report($this->user('rep'), 'owner_listing', $id, 'fraud');
        $mod = $this->user('mod');
        try {
            $reports->remove('owner_listing', $id, $mod);
            $this->fail('a rule must be named');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('violation', $e->errors());
        }
        $this->assertNotNull(OwnerListing::find($id));

        $taken = $reports->remove('owner_listing', $id, $mod, 'fraud');
        $this->assertEquals(50, $taken);
        $wallet = $wallets->getOrCreateWallet($owner->id)->refresh();
        $this->assertEquals([50, 0], [(float) $wallet->balance, (float) $wallet->locked_balance], 'the 50 is gone, not handed back');
        $this->assertNull(OwnerListing::find($id));

        $note = \App\Models\AppNotification::where('user_id', $owner->id)->where('source_type', 'moderation_content_removed')->latest('id')->first();
        $this->assertStringContainsString('احتيال', $note->body_ar);
        $this->assertStringContainsString('صودر العربون', $note->body_ar);
        $this->assertEquals(50, (float) \App\Models\ContentReport::where('reportable_id', $id)->where('reportable_type', 'owner_listing')->value('confiscated_amount'));
    }

    public function test_a_guarantee_holder_has_the_coverage_frozen_for_the_listings_life_and_loses_it_on_a_violation(): void
    {
        $this->priceDeposit(100);
        $owner = $this->user('own');
        $this->giveBronze($owner);          // coverage 1000
        $this->fund($owner, 300);
        $guarantee = fn () => UserGuarantee::where('user_id', $owner->id)->first();
        Sanctum::actingAs($owner);

        $a = $this->postJson('/api/v2/owner-listings', $this->carBody())->assertCreated()->assertJsonPath('data.listing.deposit.state', 'held')->json('data.listing.id');
        $this->assertSame('guarantee', OwnerListing::find($a)->deposit_kind);
        $this->assertEquals(100, (float) $guarantee()->used_coverage_amount, 'frozen for the life of the listing');
        $this->assertEquals(300, (float) app(WalletService::class)->getOrCreateWallet($owner->id)->refresh()->balance, 'the wallet is not touched');

        // sold: unfrozen
        $this->postJson("/api/v2/owner-listings/{$a}/finish", ['reason' => 'sold'])->assertOk();
        $this->assertEquals(0, (float) $guarantee()->used_coverage_amount);

        // a second listing, removed for a violation: charged to the guarantee holder
        $b = $this->postJson('/api/v2/owner-listings', $this->carBody(['category' => 'phones', 'deal' => 'sale', 'title' => 'Phone', 'attributes' => ['condition' => 'used']]))->assertCreated()->json('data.listing.id');
        $this->assertEquals(100, (float) $guarantee()->used_coverage_amount);

        $reports = app(\App\Services\Moderation\ContentReportService::class);
        $reports->report($this->user('rep'), 'owner_listing', $b, 'prohibited');
        $taken = $reports->remove('owner_listing', $b, $this->user('mod'), 'prohibited');

        $this->assertEquals(100, $taken);
        $this->assertEquals(200, (float) app(WalletService::class)->getOrCreateWallet($owner->id)->refresh()->balance, 'the penalty came out of his funds');
        $this->assertNull(OwnerListing::find($b));
    }

    public function test_a_guarantee_whose_free_coverage_is_below_the_stake_does_not_count_so_a_deposit_is_asked(): void
    {
        $this->priceDeposit(5000);          // more than the bronze coverage (1000)
        $u = $this->user('own');
        $this->giveBronze($u);
        Sanctum::actingAs($u);

        $req = $this->getJson('/api/v2/owner-listings/requirements')->json('data.requirements');
        $this->assertFalse($req['guarantee_ok']);
        $this->assertTrue($req['needs_deposit']);
        $this->assertContains('fraud', array_column($req['violations'], 'key'), 'the rules the app warns about come with the requirement');
        $this->assertGreaterThanOrEqual(7, count($req['violations']));
    }

    public function test_the_moderator_gets_the_list_of_rules_a_listing_is_removed_for(): void
    {
        $mod = $this->user('mod');
        \Silber\Bouncer\BouncerFacade::allow($mod)->to(\App\Support\AdminAbility::CONTENT);
        \Silber\Bouncer\BouncerFacade::refreshFor($mod);

        Sanctum::actingAs($this->user('plain'));
        $this->getJson('/api/v2/moderation/violations')->assertStatus(404);

        Sanctum::actingAs($mod);
        $keys = array_column($this->getJson('/api/v2/moderation/violations')->assertOk()->json('data.violations'), 'key');
        $this->assertEqualsCanonicalizing(array_keys(\App\Services\OwnerListings\OwnerListingSeriousness::VIOLATIONS), $keys);
    }
}
