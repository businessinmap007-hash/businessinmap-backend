<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * The business owner panel is now bilingual: SetPanelLocale (already in the web
 * group) applies the session locale the toggle stores, and the panel blades read
 * their labels through __(). Renders in both languages.
 */
class BusinessPanelLocaleTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_the_orders_screen_renders_in_arabic_by_default(): void
    {
        $this->actingAs($this->business)
            ->get('/business/orders')
            ->assertOk()
            ->assertSee('طلبات المنيو')
            ->assertSee('الطاولة');
    }

    public function test_the_orders_screen_renders_in_english_when_chosen(): void
    {
        $this->actingAs($this->business)
            ->withSession(['panel_locale' => 'en'])
            ->get('/business/orders')
            ->assertOk()
            ->assertSee('Menu orders')
            ->assertSee('Table')
            ->assertDontSee('طلبات المنيو');
    }

    public function test_the_dashboard_and_nav_localize(): void
    {
        $this->actingAs($this->business)
            ->withSession(['panel_locale' => 'en'])
            ->get('/business')
            ->assertOk()
            ->assertSee('Home')          // nav
            ->assertSee('Table Calls')   // nav
            ->assertSee('Next steps');   // dashboard body
    }

    public function test_the_locale_switch_stores_the_choice(): void
    {
        $this->actingAs($this->business)
            ->get('/business/locale/en')
            ->assertRedirect();

        $this->assertSame('en', session('panel_locale'));
    }

    public function test_the_other_wrapped_screens_compile_and_render(): void
    {
        // Renders the remaining wrapped blades in English so any Blade-compile
        // error in the __() wrapping surfaces here.
        $this->actingAs($this->business)->withSession(['panel_locale' => 'en']);

        $this->get('/business/tables')->assertOk()->assertSee('Restaurant tables');
        $this->get('/business/table-calls')->assertOk()->assertSee('Table Calls');

        $order = \App\Models\Order::create([
            'user_id' => $this->business->id,
            'business_id' => $this->business->id,
            'booking_id' => null,
            'status' => 'pending',
            'fulfillment_type' => 'pickup',
            'total' => 0,
            'discount' => 0,
            'delivery_fee' => 0,
            'final_total' => 0,
            'payment_method' => '',
            'address' => '',
        ]);
        $this->get("/business/orders/{$order->id}")->assertOk()->assertSee('Order #' . $order->id)->assertSee('Invoice');
    }

    public function test_the_menu_module_screens_render_in_both_languages(): void
    {
        $item = $this->seedMenuItem($this->business->id, null, 25.0, 'شاورما');

        // Arabic (default) — a couple of anchors.
        $this->actingAs($this->business)->get('/business/menu')->assertOk()->assertSee('منيو نشاطي');

        // English — the whole menu-management flow, incl. the variants/extras editor.
        $this->actingAs($this->business)->withSession(['panel_locale' => 'en']);
        $this->get('/business/menu')->assertOk()->assertSee('My business menu')->assertSee('Available');
        $this->get('/business/menu/create')->assertOk()->assertSee('Item details');
        $this->get("/business/menu/{$item->id}/edit")->assertOk()->assertSee('Sizes / options')->assertSee('Extras');
        $this->get('/business/menu-sections')->assertOk()->assertSee('Menu sections');
        $this->get('/business/menu-sections/create')->assertOk()->assertSee('Section details');
        $this->get('/business/menu-settings')->assertOk()->assertSee('Menu settings');
        $this->get('/business/orders/create')->assertOk()->assertSee('New menu order');
    }

    public function test_the_commerce_screens_render_in_english(): void
    {
        $this->actingAs($this->business)->withSession(['panel_locale' => 'en']);

        $this->get('/business/products')->assertOk()->assertSee('My products for sale');
        $this->get('/business/products/create')->assertOk()->assertSee('Add a product for sale');
        $this->get('/business/offerings')->assertOk()->assertSee('Everything you sell in one place', false);
        $this->get('/business/staff')->assertOk()->assertSee('Staff and permissions');
        $this->get('/business/share-store')->assertOk()->assertSee('Share Store');
        $this->get('/business/tables/print')->assertOk()->assertSee('Print table codes');
    }

    public function test_the_login_page_localizes_and_defaults_to_arabic(): void
    {
        $this->get('/business/login')->assertOk()->assertSee('دخول لوحة النشاط التجاري');

        $this->withSession(['panel_locale' => 'en'])
            ->get('/business/login')->assertOk()->assertSee('Business panel login')->assertSee('Password');
    }
}
