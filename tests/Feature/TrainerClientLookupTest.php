<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The trainer's client picker: an EXACT phone or e-mail and nothing looser. A
 * partial match over every account on the platform would be a people-finder, so
 * that is what these tests refuse to let it become.
 */
class TrainerClientLookupTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private function user(string $type, string $tag): User
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        Sanctum::actingAs($this->trainer);
    }

    private function lookup(string $q)
    {
        return $this->getJson('/api/v2/business/training/clients/lookup?q=' . urlencode($q));
    }

    public function test_an_exact_phone_or_email_finds_the_client(): void
    {
        $client = $this->user(User::TYPE_CLIENT, 'Sara');

        $byPhone = $this->lookup($client->phone)->assertOk()->json('data');
        $this->assertTrue($byPhone['found']);
        $this->assertSame($client->id, $byPhone['client']['id']);
        $this->assertSame($client->name, $byPhone['client']['name']);

        $this->assertSame($client->id, $this->lookup($client->email)->assertOk()->json('data.client.id'));
        $this->assertSame($client->id, $this->lookup('  ' . $client->email . '  ')->json('data.client.id'), 'stray spaces are ignored');
    }

    public function test_it_is_not_a_search(): void
    {
        $client = $this->user(User::TYPE_CLIENT, 'Sara');

        // A prefix of the phone, a fragment of the e-mail, a name: none of them match.
        foreach ([substr($client->phone, 0, 8), substr($client->email, 0, 10), $client->name, '%', '0'] as $q) {
            $this->assertFalse($this->lookup($q)->assertOk()->json('data.found'), "«{$q}» must not find anyone");
        }
    }

    public function test_only_client_accounts_are_found_and_never_the_trainer_himself(): void
    {
        $otherBusiness = $this->user(User::TYPE_BUSINESS, 'OtherGym');

        $this->assertFalse($this->lookup($otherBusiness->phone)->json('data.found'), 'a business is not someone\'s client');
        $this->assertFalse($this->lookup($this->trainer->phone)->json('data.found'), 'a trainer is never his own client');
    }

    public function test_a_client_account_has_no_lookup(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Client'));

        $this->lookup('01000000000')->assertStatus(403);
    }

    public function test_a_query_is_required(): void
    {
        $this->getJson('/api/v2/business/training/clients/lookup')->assertStatus(422)->assertJsonValidationErrors(['q']);
    }

    public function test_lookups_are_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->lookup('nobody-' . $i)->assertOk();
        }

        $this->lookup('one-too-many')->assertStatus(429);
    }
}
