<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «إعدادات المنصة»: the platform's timezone, editable by an admin without a redeploy, and the picture of the server it
 * runs on. The value lives in a file (read at boot, before the database exists), so each test restores it.
 */
class PlatformSettingsAdminTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $backup = null;

    private string $zoneAtStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zoneAtStart = (string) config('app.timezone');
        $path = PlatformSettings::path();
        $this->backup = is_file($path) ? file_get_contents($path) : null;
    }

    protected function tearDown(): void
    {
        $path = PlatformSettings::path();

        if ($this->backup === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $this->backup);
        }

        PlatformSettings::flush();
        config(['app.timezone' => $this->zoneAtStart, 'app.business_timezone' => $this->zoneAtStart]);
        date_default_timezone_set($this->zoneAtStart);

        parent::tearDown();
    }

    private function admin(array $abilities = [AdminAbility::SETTINGS]): User
    {
        $user = new User();
        $user->name = 'Platform Settings Admin';
        $user->email = 'platform-' . uniqid() . '@example.test';
        $user->phone = '0155' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = User::TYPE_ADMIN;
        $user->api_token = Str::random(80);
        $user->save();

        foreach ($abilities as $ability) {
            Bouncer::allow($user)->to($ability);
        }
        Bouncer::allow($user)->to(AdminAbility::ACCESS);
        Bouncer::refresh();

        return $user->fresh();
    }

    private function business(): User
    {
        return User::create([
            'name' => 'Shop ' . Str::random(4), 'email' => 'shop' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'), 'type' => User::TYPE_BUSINESS, 'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    public function test_the_page_shows_the_zone_and_the_server_state(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/platform-settings')
            ->assertOk()
            ->assertSee('Africa/Cairo', false)
            ->assertSee('PHP', false)
            // the database clock row is there and says the session offset
            ->assertSee('+03:00', false);
    }

    public function test_only_an_admin_with_the_settings_ability_may_open_it(): void
    {
        $this->actingAs($this->admin([]))->get('/admin/platform-settings')->assertForbidden();

        $this->actingAs($this->admin([AdminAbility::SETTINGS]))->get('/admin/platform-settings')->assertOk();
    }

    public function test_saving_a_zone_writes_it_and_applies_it_at_once(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/platform-settings', ['timezone' => 'Asia/Riyadh'])
            ->assertRedirect(route('admin.platform-settings.edit'));

        $this->assertSame('Asia/Riyadh', json_decode(file_get_contents(PlatformSettings::path()), true)['timezone']);

        // applied to this process too: PHP's default, the app zone and the zone a business without one is judged in
        $this->assertSame('Asia/Riyadh', config('app.timezone'));
        $this->assertSame('Asia/Riyadh', date_default_timezone_get());
        $this->assertSame('Asia/Riyadh', \App\Services\BusinessHoursService::defaultTimezone());
    }

    public function test_a_zone_that_does_not_exist_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/platform-settings', ['timezone' => 'Mars/Olympus'])
            ->assertSessionHasErrors('timezone');

        $this->assertNull(PlatformSettings::timezone());
        $this->assertSame($this->zoneAtStart, config('app.timezone'));
    }

    public function test_the_zone_can_be_moved_to_every_business_in_one_go(): void
    {
        $a = $this->business();
        $b = $this->business();
        $this->assertSame('Africa/Cairo', (string) $a->fresh()->timezone);

        $this->actingAs($this->admin())
            ->put('/admin/platform-settings', ['timezone' => 'Asia/Dubai', 'apply_to_businesses' => 1])
            ->assertRedirect();

        $this->assertSame('Asia/Dubai', (string) $a->fresh()->timezone);
        $this->assertSame('Asia/Dubai', (string) $b->fresh()->timezone);

        // without the box, a business keeps its own
        $c = $this->business();
        $this->actingAs($this->admin())
            ->put('/admin/platform-settings', ['timezone' => 'Asia/Kuwait'])
            ->assertRedirect();
        $this->assertSame('Asia/Dubai', (string) $c->fresh()->timezone);
    }

    public function test_a_saved_zone_wins_over_the_config_baseline_when_the_app_boots(): void
    {
        PlatformSettings::put(['timezone' => 'Europe/Istanbul']);

        // what the service provider does at boot
        config(['app.timezone' => 'UTC']);
        PlatformSettings::applyTimezone();

        $this->assertSame('Europe/Istanbul', config('app.timezone'));
        $this->assertSame('Europe/Istanbul', date_default_timezone_get());
    }

    public function test_a_corrupt_file_is_ignored_not_fatal(): void
    {
        file_put_contents(PlatformSettings::path(), '{not json');
        PlatformSettings::flush();

        $this->assertNull(PlatformSettings::timezone());
        PlatformSettings::applyTimezone();
        $this->assertSame($this->zoneAtStart, config('app.timezone'));
    }
}
