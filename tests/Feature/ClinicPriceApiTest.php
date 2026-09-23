<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «تحديد اسعار الكشف الخ يتم بشكل معقد جدا» — المالك. A one-call door for a
 * clinic's own visit-kind fees (كشف/استشارة/...), without first discovering
 * service_id/bookable_item_type via business/prices/options, and without the
 * general merchant-pricing shape (line_option_id, modifiers) a clinic never
 * uses at all.
 */
class ClinicPriceApiTest extends TestCase
{
    use DatabaseTransactions;

    private function clinic(): User
    {
        $u = new User();
        $u->name = 'Clinic ' . Str::random(4);
        $u->email = 'clinic-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->category_child_id = User::DOCTOR_OWN_CLINIC_CHILD_ID;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_the_index_lists_priceable_visit_kinds_with_nothing_priced_yet(): void
    {
        $clinic = $this->clinic();

        Sanctum::actingAs($clinic);
        $res = $this->getJson('/api/v2/business/clinic-prices')->assertOk();

        $kinds = $res->json('data.kinds');
        $this->assertNotEmpty($kinds, 'a clinic child must offer at least one bookable visit kind');
        $this->assertContains('booking_examination', array_column($kinds, 'key'));

        foreach ($kinds as $kind) {
            $this->assertNull($kind['price']);
        }
    }

    public function test_setting_several_fees_in_one_call_and_reading_them_back(): void
    {
        $clinic = $this->clinic();

        Sanctum::actingAs($clinic);
        $this->patchJson('/api/v2/business/clinic-prices', [
            'prices' => [
                'booking_examination' => ['price' => 100, 'duration_minutes' => 30],
                'booking_consultation' => ['price' => 80, 'duration_minutes' => 20],
            ],
        ])->assertOk();

        $res = $this->getJson('/api/v2/business/clinic-prices')->assertOk();
        $kinds = collect($res->json('data.kinds'))->keyBy('key');

        $this->assertEquals(100.0, $kinds['booking_examination']['price']);
        $this->assertSame(30, $kinds['booking_examination']['duration_minutes']);
        $this->assertEquals(80.0, $kinds['booking_consultation']['price']);

        $this->assertDatabaseHas('business_service_prices', [
            'business_id' => $clinic->id, 'bookable_item_type' => 'booking_examination',
            'price' => 100, 'line_option_id' => 0,
        ]);
    }

    public function test_updating_one_fee_does_not_disturb_another_already_set(): void
    {
        $clinic = $this->clinic();
        Sanctum::actingAs($clinic);

        $this->patchJson('/api/v2/business/clinic-prices', [
            'prices' => ['booking_examination' => ['price' => 100]],
        ])->assertOk();

        $this->patchJson('/api/v2/business/clinic-prices', [
            'prices' => ['booking_consultation' => ['price' => 80]],
        ])->assertOk();

        $kinds = collect($this->getJson('/api/v2/business/clinic-prices')->json('data.kinds'))->keyBy('key');
        $this->assertEquals(100.0, $kinds['booking_examination']['price']);
        $this->assertEquals(80.0, $kinds['booking_consultation']['price']);
    }

    /** A second PATCH for the same kind updates the same row, never a duplicate. */
    public function test_resetting_a_fee_updates_the_same_row(): void
    {
        $clinic = $this->clinic();
        Sanctum::actingAs($clinic);

        $this->patchJson('/api/v2/business/clinic-prices', [
            'prices' => ['booking_examination' => ['price' => 100]],
        ])->assertOk();
        $this->patchJson('/api/v2/business/clinic-prices', [
            'prices' => ['booking_examination' => ['price' => 150]],
        ])->assertOk();

        $this->assertSame(1, BusinessServicePrice::query()
            ->where('business_id', $clinic->id)
            ->where('bookable_item_type', 'booking_examination')
            ->count());

        $kinds = collect($this->getJson('/api/v2/business/clinic-prices')->json('data.kinds'))->keyBy('key');
        $this->assertEquals(150.0, $kinds['booking_examination']['price']);
    }

    public function test_an_unoffered_visit_kind_is_rejected(): void
    {
        $clinic = $this->clinic();

        Sanctum::actingAs($clinic);
        $this->patchJson('/api/v2/business/clinic-prices', [
            'prices' => ['not_a_real_kind' => ['price' => 100]],
        ])->assertStatus(422)->assertJsonValidationErrors('prices');
    }

    public function test_a_non_clinic_business_is_refused(): void
    {
        $shop = new User();
        $shop->name = 'Shop ' . Str::random(4);
        $shop->email = 'shop-' . uniqid() . '@example.test';
        $shop->phone = '01' . random_int(100000000, 999999999);
        $shop->password = 'secret-password';
        $shop->type = User::TYPE_BUSINESS;
        $shop->category_child_id = 116;
        $shop->api_token = Str::random(80);
        $shop->save();

        Sanctum::actingAs($shop);
        $this->getJson('/api/v2/business/clinic-prices')->assertStatus(422);
    }
}
