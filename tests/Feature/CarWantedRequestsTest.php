<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Requests\CarWantedRequestKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «أريد سيارة بهذه المواصفات» on the requests-and-offers core: the buyer (or renter) describes the car, the showrooms
 * and private sellers of his governorate answer with what they have, the one he picks becomes a viewing appointment.
 * Rolls back.
 */
class CarWantedRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private int $gov;
    private int $otherGov;
    private int $showroom;
    private int $owner;
    private int $sedan;
    private int $toyota;

    protected function setUp(): void
    {
        parent::setUp();

        $trades = (new CarWantedRequestKind)->tradeIds();
        if (count($trades) < 2) {
            $this->markTestSkipped('Needs the showroom and the private-seller trades.');
        }

        [$this->showroom, $this->owner] = [(int) DB::table('category_children_master')->where('name_ar', 'معرض سيارات')->value('id'), (int) DB::table('category_children_master')->where('name_ar', 'سيارة من المالك')->value('id')];
        $this->gov = (int) DB::table('governorates')->orderBy('id')->value('id');
        $this->otherGov = (int) DB::table('governorates')->where('id', '!=', $this->gov)->orderBy('id')->value('id');
        $this->sedan = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'نوع المركبة')->where('o.name_ar', 'سيدان')->value('o.id');
        $this->toyota = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'ماركات السيارات')->orderBy('o.id')->value('o.id');
    }

    private function user(string $type, string $tag, array $extra = []): User
    {
        $u = new User();
        $u->forceFill($extra + [
            'name' => $tag . ' Name ' . Str::random(4),
            'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999),
            'password' => 'secret-password',
            'type' => $type,
            'api_token' => Str::random(80),
        ])->save();

        return $u;
    }

    private function dealer(string $tag, int $childId, ?int $gov = null): User
    {
        return $this->user(User::TYPE_BUSINESS, $tag, ['category_id' => 13, 'category_child_id' => $childId, 'governorate_id' => $gov ?? $this->gov]);
    }

    private function wanted(array $extra = []): array
    {
        return $extra + [
            'kind' => 'car_wanted', 'deal' => 'buy', 'vehicle_type_option_id' => $this->sedan, 'brand_option_id' => $this->toyota,
            'year_min' => 2018, 'max_price' => 450000, 'governorate_id' => $this->gov,
        ];
    }

    public function test_the_request_is_posted_with_its_words_and_only_the_dealers_of_the_governorate_are_told(): void
    {
        $showroom = $this->dealer('show', $this->showroom);
        $owner = $this->dealer('own', $this->owner);
        $faraway = $this->dealer('far', $this->showroom, $this->otherGov);
        $wrongTrade = $this->dealer('shop', (int) DB::table('category_children_master')->whereNotIn('id', [$this->showroom, $this->owner])->value('id'));
        $customer = $this->user(User::TYPE_CLIENT, 'cust');

        Sanctum::actingAs($customer);
        $res = $this->postJson('/api/v2/service-requests', $this->wanted())->assertCreated()
            ->assertJsonPath('data.request.status', 'open')->assertJsonPath('data.request.budget', 450000)
            ->assertJsonPath('data.request.params.deal', 'buy');

        $this->assertStringContainsString('سيدان', $res->json('data.request.title'));
        $this->assertStringContainsString('2018', $res->json('data.request.title'));

        $requestId = (int) $res->json('data.request.id');
        $told = fn (User $u) => AppNotification::query()->where('user_id', $u->id)->where('source_type', 'service_request_new')->where('source_id', $requestId)->count();
        $this->assertSame(1, $told($showroom));
        $this->assertSame(1, $told($owner));
        $this->assertSame(0, $told($faraway));
        $this->assertSame(0, $told($wrongTrade));
    }

    public function test_a_rental_names_the_days_and_an_option_of_another_list_is_refused(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));

        $this->postJson('/api/v2/service-requests', $this->wanted(['deal' => 'rent', 'days' => 7, 'max_price' => 900]))->assertCreated()
            ->assertJsonPath('data.request.params.days', 7);

        // a brand where the kind of car belongs
        $this->postJson('/api/v2/service-requests', $this->wanted(['vehicle_type_option_id' => $this->toyota]))->assertStatus(422)
            ->assertJsonValidationErrors('vehicle_type_option_id');
    }

    public function test_dealers_see_it_each_answers_once_and_the_customer_picks_one_that_becomes_a_free_viewing(): void
    {
        $showroom = $this->dealer('show', $this->showroom);
        $owner = $this->dealer('own', $this->owner);
        $outsider = $this->dealer('far', $this->showroom, $this->otherGov);
        $customer = $this->user(User::TYPE_CLIENT, 'cust');

        Sanctum::actingAs($customer);
        $id = $this->postJson('/api/v2/service-requests', $this->wanted(['starts_at' => now()->addDays(2)->setTime(15, 0)->toDateTimeString()]))->assertCreated()->json('data.request.id');

        // both trades see it on the incoming list; another governorate does not
        foreach ([$showroom, $owner] as $dealer) {
            Sanctum::actingAs($dealer);
            $list = $this->getJson('/api/v2/business/service-requests?kind=car_wanted')->assertOk();
            $this->assertContains($id, array_column($list->json('data.data'), 'id'));
        }
        Sanctum::actingAs($outsider);
        $this->assertNotContains($id, array_column($this->getJson('/api/v2/business/service-requests?kind=car_wanted')->json('data.data'), 'id'));
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 400000])->assertStatus(422);

        // the showroom points at one of its own cars; the owner cannot point at someone else's
        $item = DB::table('menu_items')->insertGetId(['business_id' => $showroom->id, 'name_ar' => 'كورولا ٢٠٢٠', 'base_price' => 430000, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 410000, 'menu_item_id' => $item])->assertStatus(422)->assertJsonValidationErrors('menu_item_id');
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 410000, 'message' => 'من المالك'])->assertCreated();

        Sanctum::actingAs($showroom);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 430000, 'menu_item_id' => $item])->assertCreated();

        Sanctum::actingAs($customer);
        $shown = $this->getJson("/api/v2/service-requests/{$id}")->assertOk();
        $offers = collect($shown->json('data.request.offers'));
        $this->assertCount(2, $offers);
        $this->assertSame('كورولا ٢٠٢٠', $offers->firstWhere('price', 430000)['item']['name']);
        $this->assertNull($offers->firstWhere('price', 410000)['item']);

        $chosen = $offers->firstWhere('price', 430000)['id'];
        $bookingId = $this->postJson("/api/v2/service-requests/{$id}/offers/{$chosen}/accept")->assertOk()->json('data.booking_id');

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame($customer->id, (int) $booking->user_id);
        $this->assertSame($showroom->id, (int) $booking->business_id);
        $this->assertEquals(0, $booking->price, 'a viewing is free: the car is paid for with the dealer');
        $this->assertSame($item, (int) $booking->meta['menu_item_id']);
        $this->assertStringContainsString('معاينة', $booking->title());
        $this->assertSame('accepted', $booking->status);
        $this->assertSame(ServiceRequest::STATUS_ACCEPTED, ServiceRequest::findOrFail($id)->status);
    }

    public function test_the_ride_list_is_unaffected_by_the_new_kind(): void
    {
        $showroom = $this->dealer('show', $this->showroom);
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));
        $this->postJson('/api/v2/service-requests', $this->wanted())->assertCreated();

        Sanctum::actingAs($showroom);
        $this->assertSame([], $this->getJson('/api/v2/business/service-requests')->json('data.data'), 'a showroom is not a limousine driver');
    }
}
