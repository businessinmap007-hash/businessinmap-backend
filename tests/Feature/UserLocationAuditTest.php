<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CityLocatorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** GPS -> governorate/city, and the audit that cross-checks stored accounts. */
class UserLocationAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registering_with_a_gps_point_keeps_it_on_the_account_and_its_first_address(): void
    {
        $city = DB::table('cities')->whereNotNull('latitude')->orderBy('id')->first();

        $this->postJson('/api/v2/auth/register', [
            'name' => 'GPS User', 'email' => 'gps-signup@example.test', 'phone' => '01099922222',
            'password' => 'Test12345!', 'password_confirmation' => 'Test12345!', 'type' => 'client', 'terms_accepted' => true,
            'governorate_id' => $city->governorate_id, 'city_id' => $city->id, 'address_line' => 'شارع الاختبار 1',
            'latitude' => $city->latitude, 'longitude' => $city->longitude,
        ])->assertCreated();

        $user = User::query()->where('email', 'gps-signup@example.test')->firstOrFail();
        $this->assertEqualsWithDelta((float) $city->latitude, (float) $user->latitude, 0.0001);
        $this->assertSame((int) $city->id, (int) $user->city_id);
        $this->assertEqualsWithDelta((float) $city->latitude, (float) $user->addresses()->first()->lat, 0.0001);
    }

    public function test_the_locator_resolves_a_point_inside_egypt_and_refuses_one_abroad(): void
    {
        $city = DB::table('cities')->whereNotNull('latitude')->orderBy('id')->first();
        $locator = app(CityLocatorService::class);

        $found = $locator->nearest((float) $city->latitude, (float) $city->longitude);
        $this->assertNotNull($found);
        $this->assertSame((int) $city->governorate_id, (int) $found->governorate_id);

        $this->assertNull($locator->nearest(24.4485738, 39.5403348), 'Madinah is not in Egypt');
    }

    public function test_the_audit_is_a_dry_run_unless_told_to_apply(): void
    {
        $city = DB::table('cities')->whereNotNull('latitude')->orderBy('id')->first();
        $id = DB::table('users')->insertGetId([
            'name' => 'Blank Place', 'email' => 'blank-place@example.test', 'phone' => '01099933333',
            'password' => 'x', 'type' => 'client', 'api_token' => \Illuminate\Support\Str::random(80),
            'latitude' => $city->latitude, 'longitude' => $city->longitude,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('users:audit-locations', ['--sample' => 0])->assertSuccessful();

        $this->assertNull(DB::table('users')->where('id', $id)->value('governorate_id'), 'a dry run writes nothing');
    }
}
