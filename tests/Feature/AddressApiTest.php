<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * v2 address book: per-user scoping and the single-primary invariant
 * (first-is-primary, setPrimary, delete-primary promotes newest). Rolls back.
 */
class AddressApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->orderBy('id')->firstOrFail();
        $this->user->addresses()->delete();

        $loc = DB::table('locations')->value('id');
        if (! $loc) {
            $this->markTestSkipped('Needs at least one locations row.');
        }
        $this->locationId = (int) $loc;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'governorate_id' => $this->locationId,
            'city_id' => $this->locationId,
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
