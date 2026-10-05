<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «الروابط النسبية في XAMPP» — a panel page's own fetch() URLs must keep the app's base path. Served from a
 * sub-folder (`localhost/testing/public`) `route(.., false)` gave `/business/...`, which the browser resolved against
 * the host root and answered 404. `panel_route()` keeps the base path, and adds nothing when the site owns the host.
 * Rolls back.
 */
class PanelBasePathTest extends TestCase
{
    use DatabaseTransactions;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $u = new User();
        $u->name = 'Clinic ' . Str::random(4);
        $u->email = 'clinic-' . uniqid() . '@example.test';
        $u->phone = '0105' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->category_child_id = 514;
        $u->api_token = Str::random(80);
        $u->save();
        $this->doctor = $u;
    }

    public function test_from_a_sub_folder_the_pages_urls_keep_the_base_path(): void
    {
        // what Apache hands PHP when the project is served from localhost/testing/public
        $html = $this->actingAs($this->doctor)->call('GET', '/testing/public/business/prescriptions', [], [], [], [
            'SCRIPT_NAME' => '/testing/public/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/testing/public/index.php',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('"\/testing\/public\/business\/prescriptions\/data\/issued"', $html);
        $this->assertStringContainsString('"\/testing\/public\/business\/prescriptions"', $html, 'the store URL');
    }

    public function test_when_the_site_owns_the_host_nothing_is_added(): void
    {
        $html = $this->actingAs($this->doctor)->get('/business/prescriptions')->assertOk()->getContent();

        $this->assertStringContainsString('"\/business\/prescriptions\/data\/issued"', $html);
        $this->assertStringNotContainsString('testing', $html);
    }

    public function test_the_helper_returns_the_path_without_the_host(): void
    {
        $this->assertSame('/business/prescriptions', panel_route('business.prescriptions.index'));
        $this->assertSame('/business/prescriptions/__ID__/revise', panel_route('business.prescriptions.revise', ['id' => '__ID__']));
    }
}
