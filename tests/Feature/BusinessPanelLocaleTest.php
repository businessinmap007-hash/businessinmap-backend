<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The business owner panel is now bilingual: SetPanelLocale (already in the web
 * group) applies the session locale the toggle stores, and the panel blades read
 * their labels through __(). Renders in both languages.
 */
class BusinessPanelLocaleTest extends TestCase
{
    use DatabaseTransactions;

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
}
