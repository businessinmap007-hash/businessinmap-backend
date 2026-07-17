<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * v2 address book: per-user scoping and the single-primary invariant
 * (first-is-primary, setPrimary, delete-primary promotes newest). Rolls back.
 *
 * This test used to post the SAME id as both governorate_id and city_id, taken
 * from `locations` — and passed, because it encoded exactly the assumption the
 * code got wrong (BIM-11.1). The invariants below were always real; the address
 * they were asserted against was geographic nonsense. Now it uses a real
 * governorate and one of its own cities.
 */
class AddressApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $governorateId;
    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->orderBy('id')->firstOrFail();
        $this->user->addresses()->delete();

        $governorate = Governorate::query()->whereHas('cities')->first();

        if (! $governorate) {
            $this->markTestSkipped('Needs a governorate with cities.');
        }

        $this->governorateId = (int) $governorate->id;
        $this->cityId = (int) City::query()->where('governorate_id', $governorate->id)->value('id');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'governorate_id' => $this->governorateId,
            'city_id' => $this->cityId,
            'address_line' => 'Some street, building 5',
        ], $override);
    }

    public function test_first_address_is_primary_and_second_is_not(): void
    {
        $first = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/addresses', $this->payload())
            ->assertCreated()->json('data');
        $this->assertTrue((bool) $first['is_primary']);

        $second = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/addresses', $this->payload(['address_line' => 'Another street 10']))
            ->assertCreated()->json('data');
        $this->assertFalse((bool) $second['is_primary']);
    }

    public function test_set_primary_moves_the_flag(): void
    {
        $a = $this->actingAs($this->user, 'sanctum')->postJson('/api/v2/addresses', $this->payload())->json('data');
        $b = $this->actingAs($this->user, 'sanctum')->postJson('/api/v2/addresses', $this->payload(['address_line' => 'Second one here']))->json('data');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v2/addresses/{$b['id']}/primary")->assertOk();

        $this->assertDatabaseHas('addresses', ['id' => $b['id'], 'is_primary' => 1]);
        $this->assertDatabaseHas('addresses', ['id' => $a['id'], 'is_primary' => 0]);
    }

    public function test_deleting_primary_promotes_newest_remaining(): void
    {
        $a = $this->actingAs($this->user, 'sanctum')->postJson('/api/v2/addresses', $this->payload())->json('data');
        $b = $this->actingAs($this->user, 'sanctum')->postJson('/api/v2/addresses', $this->payload(['address_line' => 'Second one here']))->json('data');
        // $a is primary (first). Delete it → newest remaining ($b) is promoted.

        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v2/addresses/{$a['id']}")->assertOk();

        $this->assertDatabaseMissing('addresses', ['id' => $a['id']]);
        $this->assertDatabaseHas('addresses', ['id' => $b['id'], 'is_primary' => 1]);
    }

    public function test_cannot_touch_another_users_address(): void
    {
        $mine = $this->actingAs($this->user, 'sanctum')->postJson('/api/v2/addresses', $this->payload())->json('data');
        $other = User::query()->where('id', '!=', $this->user->id)->orderBy('id')->firstOrFail();

        $this->actingAs($other, 'sanctum')->deleteJson("/api/v2/addresses/{$mine['id']}")->assertNotFound();
    }
}
