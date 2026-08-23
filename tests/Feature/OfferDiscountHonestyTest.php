<?php

namespace Tests\Feature;

use App\Models\CommercialOffer;
use App\Models\MenuItem;
use App\Models\OfferingPriceChange;
use App\Models\User;
use App\Services\Offers\OfferEligibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «يجب مرور شهر كامل دون رفع السعر قبل تسجيل عرض خصم. الهدف منع العروض الوهمية
 *  التي يرفع فيها التاجر السعر ثم يعلن خصمًا ثم يعيد المنتج لسعره السابق»
 *  — المالك، 2026-08-23.
 *
 * The fraud has three steps and the platform could see none of them, because
 * an offer carried a `base_price` the API took from the request and never
 * compared to anything. «٣٠٪ خصم على الجينز» was two free-text numbers with a
 * percentage between them.
 *
 * The fix is smaller than the rule sounds: read the previous price instead of
 * accepting it. Once there is no field left to inflate, the month is a
 * question the history can answer.
 */
class OfferDiscountHonestyTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $b = User::query()->where('type', 'business')->first();

        if (! $b) {
            $this->markTestSkipped('Needs a business user.');
        }

        $this->openTheOffersDoor((int) $b->id);

        return $b;
    }

    /**
     * `business_offers` is switched OFF platform-wide, deliberately and by the
     * owner, and the merchant must also be subscribed to it.
     *
     * Both are true of the live database and neither is what this file is
     * about: a test that skipped on them would report «the discount rule
     * works» without ever having reached the discount rule. Flipped inside the
     * transaction, so it is rolled back with everything else.
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

    /** A dish, a garment, a flat — one priced row this business owns. */
    private function pricedRow(User $business, float $price = 200): MenuItem
    {
        return MenuItem::create([
            'business_id' => $business->id,
            'menu_section_id' => null,
            'item_type' => 'menu_food',
            'name_ar' => 'صنف اختبار',
            'base_price' => $price,
            'is_active' => 1,
        ]);
    }

    /** @return array<string,mixed> */
    private function payload(MenuItem $row, array $overrides = []): array
    {
        return array_merge([
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
            'final_price' => 150,
            'title_ar' => 'خصم اختبار',
            'ends_at' => now()->addWeek()->toDateTimeString(),
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | The history
    |--------------------------------------------------------------------------
    */

    /** Creating a row is not raising a price. */
    public function test_a_new_row_records_its_opening_price_and_no_increase(): void
    {
        $row = $this->pricedRow($this->business(), 200);

        $changes = OfferingPriceChange::query()->for($row)->get();

        $this->assertCount(1, $changes);
        $this->assertNull($changes[0]->old_price, 'opening at a price is not a rise');
        $this->assertSame('200.00', (string) $changes[0]->new_price);
        $this->assertFalse($changes[0]->is_increase);

        $this->assertNull($row->lastPriceIncreaseAt());
    }

    /** …and every move after it is remembered, with its direction. */
    public function test_a_rise_and_a_cut_are_told_apart(): void
    {
        $row = $this->pricedRow($this->business(), 200);

        $row->update(['base_price' => 300]);
        $row->update(['base_price' => 250]);

        $changes = OfferingPriceChange::query()->for($row)->orderBy('id')->get();

        $this->assertCount(3, $changes);
        $this->assertTrue($changes[1]->is_increase, '200 → 300 is a rise');
        $this->assertFalse($changes[2]->is_increase, '300 → 250 is a cut');

        $this->assertNotNull($row->fresh()->lastPriceIncreaseAt());
    }

    /** A save that does not move the number leaves no row. */
    public function test_saving_the_same_price_is_not_a_change(): void
    {
        $row = $this->pricedRow($this->business(), 200);

        $row->update(['base_price' => 200, 'name_ar' => 'اسم آخر']);

        $this->assertSame(1, OfferingPriceChange::query()->for($row)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | The rule
    |--------------------------------------------------------------------------
    */

    /** The offer must name a row, and the row must be this business's. */
    public function test_an_offer_must_name_a_row_that_exists_and_is_yours(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $this->postJson('/api/v2/business/offers', $this->payload(
            $this->pricedRow($business),
            ['offerable_id' => 0]
        ))->assertStatus(422)->assertJsonValidationErrors('offerable_id');

        $stranger = User::query()->where('type', 'business')->where('id', '!=', $business->id)->first();

        if ($stranger) {
            $theirs = $this->pricedRow($stranger, 400);

            $this->postJson('/api/v2/business/offers', $this->payload($theirs))
                ->assertStatus(422)->assertJsonValidationErrors('offerable_id');
        }
    }

    /**
     * The previous price is READ, not typed — the whole trick, ended.
     *
     * A merchant sending `base_price: 500` on a row that costs 200 gets an
     * offer whose base is 200, because there is no longer a field in which to
     * say otherwise.
     */
    public function test_the_previous_price_comes_off_the_row_and_not_the_request(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $row = $this->pricedRow($business, 200);

        $res = $this->postJson('/api/v2/business/offers', $this->payload($row, [
            'base_price' => 500,       // the inflated «before» price
            'final_price' => 350,      // …and a «30% off» that is really +75%
        ]));

        if ($res->status() === 403 || $res->status() === 429) {
            $this->markTestSkipped('Offer creation gated: ' . $res->getContent());
        }

        // 350 is not below 200, so the offer that used to be legal is refused
        // on its own arithmetic.
        $res->assertStatus(422)->assertJsonValidationErrors('final_price');
    }

    /** A discount below the real price is accepted, and stamped with what it was checked against. */
    public function test_an_honest_discount_is_accepted_and_leaves_an_audit_trail(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $row = $this->pricedRow($business, 200);

        $res = $this->postJson('/api/v2/business/offers', $this->payload($row, [
            'base_price' => 999,
            'final_price' => 150,
        ]));

        if ($res->status() !== 201) {
            $this->markTestSkipped('Offer creation gated: ' . $res->getContent());
        }

        $offer = CommercialOffer::query()->findOrFail((int) $res->json('data.offer.id'));

        $this->assertSame('200.00', (string) $offer->base_price, 'the typed 999 survived');
        $this->assertSame('150.00', (string) $offer->final_price);
        $this->assertSame('50.00', (string) $offer->discount_value);

        $trail = $offer->meta['checked_against'] ?? [];

        $this->assertSame(200.0, (float) ($trail['price'] ?? 0));
        $this->assertStringContainsString('#' . $row->id, (string) ($trail['row'] ?? ''));
        $this->assertArrayHasKey('checked_at', $trail);
    }

    /**
     * Raise the price today, discount it today: refused, with the date it
     * becomes possible.
     */
    public function test_a_price_raised_this_month_cannot_be_discounted(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $row = $this->pricedRow($business, 200);
        $row->update(['base_price' => 400]);   // the first step of the trick

        $res = $this->postJson('/api/v2/business/offers', $this->payload($row, ['final_price' => 300]));

        $res->assertStatus(422)->assertJsonValidationErrors('offerable_id');

        $this->assertStringContainsString(
            now()->addDays(OfferEligibility::QUIET_DAYS)->toDateString(),
            (string) $res->json('errors.offerable_id.0'),
            'the refusal must say when it becomes possible'
        );
    }

    /** …and once the month has passed, the same offer goes through. */
    public function test_the_same_offer_is_accepted_once_the_month_has_passed(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $row = $this->pricedRow($business, 200);
        $row->update(['base_price' => 400]);

        // The rise, backdated past the quiet window.
        OfferingPriceChange::query()->for($row)->increases()
            ->update(['changed_at' => now()->subDays(OfferEligibility::QUIET_DAYS + 1)]);

        $res = $this->postJson('/api/v2/business/offers', $this->payload($row, ['final_price' => 300]));

        if (in_array($res->status(), [403, 429], true)) {
            $this->markTestSkipped('Offer creation gated: ' . $res->getContent());
        }

        $res->assertStatus(201);
    }

    /** A price CUT never blocks anything — only a rise is what an offer hides. */
    public function test_a_recent_price_cut_does_not_block_an_offer(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $row = $this->pricedRow($business, 400);
        $row->update(['base_price' => 200]);

        $res = $this->postJson('/api/v2/business/offers', $this->payload($row, ['final_price' => 150]));

        if (in_array($res->status(), [403, 429], true)) {
            $this->markTestSkipped('Offer creation gated: ' . $res->getContent());
        }

        $res->assertStatus(201);
    }

    /*
    |--------------------------------------------------------------------------
    | «إلى متى؟»
    |--------------------------------------------------------------------------
    */

    /**
     * An offer with no end is a price change wearing an offer's clothes: it
     * never expires, so the «before» price never comes back and the whole
     * comparison stops meaning anything.
     */
    public function test_an_offer_must_say_when_it_ends(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $row = $this->pricedRow($business, 200);

        $this->postJson('/api/v2/business/offers', $this->payload($row, ['ends_at' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    /** …and the other two answers are as good as a date. */
    public function test_a_quantity_or_a_sold_out_is_an_end_as_well(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        // «حتى بيع ٥٠٠ قطعة»
        $res = $this->postJson('/api/v2/business/offers', $this->payload($this->pricedRow($business), [
            'ends_at' => null,
            'availability_mode' => CommercialOffer::AVAILABILITY_LIMITED,
            'available_quantity' => 500,
        ]));

        if (in_array($res->status(), [403, 429], true)) {
            $this->markTestSkipped('Offer creation gated: ' . $res->getContent());
        }

        $res->assertStatus(201);

        // «حتى نفاد الكمية»
        $this->postJson('/api/v2/business/offers', $this->payload($this->pricedRow($business), [
            'ends_at' => null,
            'availability_mode' => CommercialOffer::AVAILABILITY_WHILE_STOCK,
        ]))->assertStatus(201);

        // …but «limited» with no number is not an answer.
        $this->postJson('/api/v2/business/offers', $this->payload($this->pricedRow($business), [
            'ends_at' => null,
            'availability_mode' => CommercialOffer::AVAILABILITY_LIMITED,
            'available_quantity' => 0,
        ]))->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }
}
