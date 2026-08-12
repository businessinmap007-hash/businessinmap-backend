<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «افحص مستوى الامان فى الحسابات لعدم السرقة او التداخل بينهم».
 *
 * Two questions, held here as tests rather than as an opinion:
 *
 *  - **Theft** — can someone become an account they are not? Privilege
 *    escalation through mass assignment, self-activation, resetting one's own
 *    lockout, or reading a credential back out of the API.
 *  - **Bleed** — can one business reach another's rows? The panel resolves an
 *    ACTING business from a header, which is exactly the shape of hole that
 *    lets an id be swapped.
 *
 * These run against real endpoints. A reading of the code is an opinion; a
 * request that comes back 403 is evidence.
 */
class AccountSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): User
    {
        return User::create([
            'name' => 'Zz Client',
            'email' => 'zz-client-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_CLIENT,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    private function business(string $name = 'Zz Shop'): User
    {
        $child = \Illuminate\Support\Facades\DB::table('category_parent_child')->first();

        return User::create([
            'name' => $name,
            'name_en' => $name,
            'email' => 'zz-biz-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => $child?->parent_id,
            'category_child_id' => $child?->child_id,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Theft: becoming an account you are not
    |--------------------------------------------------------------------------
    */

    /** `type` is fillable, so only the validator stands between a client and admin. */
    public function test_a_client_cannot_promote_itself_through_its_profile(): void
    {
        $user = $this->client();
        Sanctum::actingAs($user);

        $this->putJson('/api/v2/profile', ['type' => 'admin', 'name' => 'Zz Renamed'])
            ->assertOk();

        $this->assertSame(User::TYPE_CLIENT, $user->fresh()->type, 'a client became an admin by asking');
    }

    /**
     * `balance` and the trust flags are out of $fillable, and this is the test
     * that keeps them out — they are one careless `fill()` away from being money
     * anyone can grant themselves.
     */
    public function test_a_client_cannot_grant_itself_money_or_trust(): void
    {
        $user = $this->client();
        $before = (float) $user->balance;

        Sanctum::actingAs($user);

        $this->putJson('/api/v2/profile', [
            'balance' => 999999,
            'guarantee_enabled' => 1,
            'commercial_operations_enabled' => 1,
            'rating_enabled' => 1,
        ])->assertOk();

        $fresh = $user->fresh();

        $this->assertSame($before, (float) $fresh->balance);
        $this->assertFalse((bool) $fresh->guarantee_enabled);
        $this->assertFalse((bool) $fresh->commercial_operations_enabled);
    }

    /**
     * `activated_at`, `api_token`, `pin_attempts` and `pin_locked_until` are all
     * fillable. Any of them arriving from a request would mean self-activation,
     * a chosen session token, or a wiped brute-force lockout.
     */
    public function test_the_profile_endpoint_ignores_the_dangerous_fillable_columns(): void
    {
        $user = $this->client();
        $user->forceFill(['pin_attempts' => 5, 'pin_locked_until' => now()->addHour()])->save();

        $token = $user->api_token;

        Sanctum::actingAs($user);

        $this->putJson('/api/v2/profile', [
            'activated_at' => now()->toDateTimeString(),
            'api_token' => 'chosen-by-the-attacker',
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ])->assertOk();

        $fresh = $user->fresh();

        $this->assertSame($token, $fresh->api_token, 'a caller chose its own token');
        $this->assertSame(5, (int) $fresh->pin_attempts, 'a caller cleared its own lockout counter');
        $this->assertNotNull($fresh->pin_locked_until, 'a caller lifted its own lockout');
    }

    /** Nothing that authenticates may be read back out. */
    public function test_no_credential_is_serialised(): void
    {
        $user = $this->client();
        Sanctum::actingAs($user);

        $body = $this->getJson('/api/v2/profile')->assertOk()->json();
        $flat = json_encode($body);

        foreach (['password', 'remember_token', 'pin_code', 'api_token'] as $secret) {
            $this->assertStringNotContainsString('"' . $secret . '"', (string) $flat, "{$secret} is exposed");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bleed: reaching another account's rows
    |--------------------------------------------------------------------------
    */

    private function price(User $business): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $business->id,
            'child_id' => $business->category_child_id ?: 1,
            'service_id' => 1,
            'bookable_item_type' => 'category',
            'price' => 100,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
    }

    /**
     * The acting business is resolved from an `X-Business-Id` header, which is
     * precisely the shape of thing that gets swapped. A caller with no
     * membership must be refused rather than believed.
     */
    public function test_a_business_cannot_act_as_another_by_naming_it(): void
    {
        $mine = $this->business('Zz Mine');
        $theirs = $this->business('Zz Theirs');

        Sanctum::actingAs($mine);

        $this->getJson('/api/v2/business/prices', ['X-Business-Id' => (string) $theirs->id])
            ->assertForbidden();
    }

    /** A row belonging to someone else is not found, not merely refused. */
    public function test_a_business_cannot_read_or_change_another_businesss_price(): void
    {
        $mine = $this->business('Zz Mine2');
        $theirs = $this->business('Zz Theirs2');
        $row = $this->price($theirs);

        Sanctum::actingAs($mine);

        $this->putJson('/api/v2/business/prices/' . $row->id, ['price' => 1])
            ->assertNotFound();

        $this->assertSame('100.00', (string) $row->fresh()->price, 'another business rewrote the price');

        $this->deleteJson('/api/v2/business/prices/' . $row->id)->assertNotFound();
        $this->assertNotNull($row->fresh(), 'another business deleted the row');
    }

    /** A customer is not a merchant, whatever they post. */
    public function test_a_client_cannot_reach_the_business_surface(): void
    {
        Sanctum::actingAs($this->client());

        $this->getJson('/api/v2/business/prices')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | What a public search may ask about
    |--------------------------------------------------------------------------
    */

    /**
     * `GET /api/v2/search/offers` needs no authentication and used to match
     * `users.email`, so anyone could confirm an address belonged to a
     * registered account — and harvest addresses by narrowing.
     */
    public function test_a_public_search_cannot_probe_contact_details(): void
    {
        $business = $this->business('Zz Findable');

        $byEmail = $this->getJson('/api/v2/search/offers?q=' . urlencode($business->email))->assertOk();
        $byPhone = $this->getJson('/api/v2/search/offers?q=' . urlencode($business->phone))->assertOk();

        foreach ([$byEmail, $byPhone] as $response) {
            $this->assertStringNotContainsString(
                'Zz Findable',
                (string) $response->getContent(),
                'a contact detail identified an account to an anonymous caller'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The two names
    |--------------------------------------------------------------------------
    */

    /** «المطعم ممكن يكون كاتب الاسم بالانجليزى والباحث يكتب بالعربية». */
    public function test_a_shop_is_found_by_either_of_its_names(): void
    {
        $shop = $this->business('Zz Panda Restaurant');
        $shop->forceFill(['name' => 'مطعم باندا الاختباري', 'name_en' => 'Zz Panda Restaurant'])->save();

        foreach (['باندا الاختباري', 'Zz Panda'] as $typed) {
            $this->assertTrue(
                User::query()->searchByName($typed)->where('id', $shop->id)->exists(),
                "«{$typed}» must find it"
            );
        }
    }

    /** A business must give both; a customer is never asked twice. */
    public function test_registration_asks_a_business_for_both_names_and_a_client_for_one(): void
    {
        $child = \Illuminate\Support\Facades\DB::table('category_parent_child')->first();

        $this->postJson('/api/v2/auth/register', [
            'name' => 'محل بلا اسم إنجليزي',
            'email' => 'zz-noen-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => 'Passw0rdTest',
            'password_confirmation' => 'Passw0rdTest',
            'type' => User::TYPE_BUSINESS,
            'category_child_id' => $child?->child_id,
            'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('name_en');

        $this->postJson('/api/v2/auth/register', [
            'name' => 'عميل باسم واحد',
            'email' => 'zz-client-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => 'Passw0rdTest',
            'password_confirmation' => 'Passw0rdTest',
            'type' => User::TYPE_CLIENT,
            'terms_accepted' => true,
        ])->assertCreated();
    }

    /** The rule pointed at a table that does not exist, so every such save 500'd. */
    public function test_a_business_can_change_its_specialty_without_a_server_error(): void
    {
        $business = $this->business('Zz Specialty');
        $child = \Illuminate\Support\Facades\DB::table('category_parent_child')
            ->where('child_id', '!=', (int) $business->category_child_id)->first();

        if (! $child) {
            $this->markTestSkipped('Needs a second child.');
        }

        Sanctum::actingAs($business);

        $this->putJson('/api/v2/profile', ['category_child_id' => (int) $child->child_id])
            ->assertOk();

        $this->assertSame((int) $child->child_id, (int) $business->fresh()->category_child_id);
    }
}
