<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * v2 self-service pricing — a business manages its own price rows from the
 * app. Uses the real BIM hotel fixture (business_id 184, child_id 536)
 * since MerchantOfferingVocabulary narrows to what a merchant actually
 * ticked (option_user), which is real, curated data no factory reproduces.
 * Rolls back.
 */
class BusinessServicePriceApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->findOrFail(184);

        // This fixture already carries real, curated prices for booking_stay
        // (that's the whole reason it's used here — real vocabulary no
        // factory reproduces) — cleared per test so "no duplicate yet" holds
        // regardless of what's actually priced in the shared dev database.
        BusinessServicePrice::query()
            ->where('business_id', 184)
            ->where('service_id', 1)
            ->where('bookable_item_type', 'booking_stay')
            ->get()
            ->each(fn (BusinessServicePrice $row) => $row->offeringOptions()->delete());
        BusinessServicePrice::query()
            ->where('business_id', 184)
            ->where('service_id', 1)
            ->where('bookable_item_type', 'booking_stay')
            ->delete();
    }

    public function test_options_exposes_the_lines_this_merchant_may_sell(): void
    {
        $response = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/prices/options')
            ->assertOk();

        $this->assertNotEmpty($response->json('data.services'));
        $this->assertNotEmpty($response->json('data.lines'));
    }

    public function test_two_prices_for_the_same_service_and_item_type_can_differ_by_line(): void
    {
        // «غرفة مفردة» (965) and «غرفة مزدوجة» (966) — real ticked room types
        // on this fixture (see the earlier /profile/options exploration).
        $single = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/prices', [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 500,
                'line_option_id' => 965,
            ])
            ->assertCreated();

        $this->assertSame(965, $single->json('data.line_option.id'));

        // Same service + item type, DIFFERENT line — must not collide.
        $double = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/prices', [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 800,
                'line_option_id' => 966,
            ])
            ->assertCreated();

        $this->assertSame(966, $double->json('data.line_option.id'));
        $this->assertNotSame($single->json('data.id'), $double->json('data.id'));
    }

    public function test_same_service_item_type_and_line_is_rejected_as_a_duplicate(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/prices', [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 500,
                'line_option_id' => 965,
            ])
            ->assertCreated();

        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/prices', [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 550,
                'line_option_id' => 965,
            ])
            ->assertStatus(422);
    }

    public function test_a_line_option_id_from_outside_this_merchants_vocabulary_is_silently_dropped(): void
    {
        // Not in this merchant's picked vocabulary at all (an arbitrary high
        // id unlikely to exist) — chosenVocabulary() drops it, so the row
        // saves with no line rather than trusting a crafted id.
        $response = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/prices', [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 500,
                'line_option_id' => 999999999,
            ])
            ->assertCreated();

        $this->assertNull($response->json('data.line_option'));
    }

    public function test_updating_a_price_can_change_its_line(): void
    {
        $created = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/prices', [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 500,
                'line_option_id' => 965,
            ])
            ->assertCreated();

        $id = $created->json('data.id');

        $updated = $this->actingAs($this->business, 'sanctum')
            ->putJson("/api/v2/business/prices/{$id}", [
                'service_id' => 1,
                'bookable_item_type' => 'booking_stay',
                'price' => 600,
                'line_option_id' => 966,
            ])
            ->assertOk();

        $this->assertSame(966, $updated->json('data.line_option.id'));

        $row = BusinessServicePrice::find($id);
        $this->assertSame(966, (int) $row->lineOption()->id);
    }
}
