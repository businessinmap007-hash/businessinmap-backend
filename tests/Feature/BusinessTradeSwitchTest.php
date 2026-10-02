<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «بدل من عمل حسابات مختلفه كل مرة خلينى اقدر اغير التصنيف الرئيسي والفرعى وع
 * التغيير تحذف كل المنيو … لو غيرت حساب فهيم - خضروات وفاكهه لمصنع آثاث تحذف
 * منتجاتى وقائمتى وتحولنى لما هو محدد لمصنع الاثاث» — المالك، 2026-10-02.
 * Rolls back; the shop is made here, so no real account or file is touched.
 */
class BusinessTradeSwitchTest extends TestCase
{
    use DatabaseTransactions;

    /** «خضار وفاكهة» under «المحلات». */
    private const GROCER = [17, 114];
    /** «آثاث» under «مصانع». */
    private const FURNITURE = [23, 116];

    private function shop(array $trade = self::GROCER): User
    {
        return User::create([
            'name' => 'فهيم للاختبار',
            'email' => 'switch' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => $trade[0],
            'category_child_id' => $trade[1],
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    private function stock(User $shop): int
    {
        $section = DB::table('menu_sections')->insertGetId(['business_id' => $shop->id, 'name_ar' => 'خضار', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $item = MenuItem::create(['business_id' => $shop->id, 'menu_section_id' => $section, 'name_ar' => 'طماطم', 'base_price' => 20, 'is_active' => true]);
        DB::table('menu_item_variants')->insert(['menu_item_id' => $item->id, 'name_ar' => 'كيلو', 'type' => 'size', 'price' => 20, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('menu_item_extras')->insert(['menu_item_id' => $item->id, 'name_ar' => 'تغليف', 'price' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('option_user')->insert(['user_id' => $shop->id, 'option_id' => (int) DB::table('options')->value('id')]);
        DB::table('business_menu_settings')->insert(['business_id' => $shop->id, 'display_mode' => 'grid', 'created_at' => now(), 'updated_at' => now()]);

        return $item->id;
    }

    public function test_the_app_is_told_what_would_be_deleted_before_anything_is(): void
    {
        $shop = $this->shop();
        $item = $this->stock($shop);
        Sanctum::actingAs($shop);

        $res = $this->patchJson('/api/v2/profile', ['category_id' => self::FURNITURE[0], 'category_child_id' => self::FURNITURE[1]])
            ->assertStatus(409)
            ->assertJsonPath('requires_confirmation', true);

        $this->assertSame(1, $res->json('will_delete.menu_items'));
        $this->assertSame(1, $res->json('will_delete.menu_sections'));
        $this->assertTrue(MenuItem::query()->whereKey($item)->exists(), 'nothing is deleted until the merchant confirms');
        $this->assertEquals(self::GROCER, [$shop->fresh()->category_id, $shop->fresh()->category_child_id]);
    }

    public function test_confirming_moves_the_account_to_the_new_trade_and_wipes_the_old_one(): void
    {
        $shop = $this->shop();
        $item = $this->stock($shop);
        Sanctum::actingAs($shop);

        $res = $this->patchJson('/api/v2/profile', [
            'category_id' => self::FURNITURE[0], 'category_child_id' => self::FURNITURE[1], 'confirm_reset' => true,
        ])->assertOk();

        $this->assertSame(1, $res->json('trade_switch.deleted.menu_items'));

        $fresh = $shop->fresh();
        $this->assertEquals(self::FURNITURE, [$fresh->category_id, $fresh->category_child_id], 'same account, new trade');

        $this->assertFalse(MenuItem::query()->whereKey($item)->exists());
        foreach (['menu_sections', 'business_menu_settings'] as $table) {
            $this->assertSame(0, DB::table($table)->where('business_id', $shop->id)->count(), "{$table} gone");
        }
        $this->assertSame(0, DB::table('menu_item_variants')->where('menu_item_id', $item)->count(), 'its sizes go with it');
        $this->assertSame(0, DB::table('menu_item_extras')->where('menu_item_id', $item)->count(), 'and its extras');
        $this->assertSame(0, DB::table('option_user')->where('user_id', $shop->id)->count(), 'the old trade\'s vocabulary ticks go');

        // …and what it IS now comes from the furniture factory's own definition.
        $vocab = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines');
        $this->assertContains('أثاث وتشطيب منزلي', array_column($vocab, 'group_name'), 'the furniture trade\'s vocabulary, not the grocer\'s');
    }

    public function test_a_shop_with_nothing_to_lose_switches_without_asking(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);

        $this->patchJson('/api/v2/profile', ['category_id' => self::FURNITURE[0], 'category_child_id' => self::FURNITURE[1]])->assertOk();

        $this->assertEquals(self::FURNITURE[1], $shop->fresh()->category_child_id);
    }

    public function test_the_child_must_stand_under_the_root(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);

        // «آثاث» is under شركات/معارض/مصانع — not under «المحلات» (17).
        $this->patchJson('/api/v2/profile', ['category_id' => 17, 'category_child_id' => self::FURNITURE[1], 'confirm_reset' => true])
            ->assertStatus(422)->assertJsonValidationErrors('category_child_id');

        $this->assertEquals(self::GROCER, [$shop->fresh()->category_id, $shop->fresh()->category_child_id]);
    }

    public function test_an_open_order_blocks_the_switch_and_nothing_is_deleted(): void
    {
        $shop = $this->shop();
        $item = $this->stock($shop);
        $client = User::query()->where('type', 'client')->orderBy('id')->first() ?: $this->markTestSkipped('No client account.');

        DB::table('orders')->insert(['user_id' => $client->id, 'business_id' => $shop->id, 'status' => 'pending', 'total' => 100, 'address' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($shop);

        $this->patchJson('/api/v2/profile', ['category_id' => self::FURNITURE[0], 'category_child_id' => self::FURNITURE[1], 'confirm_reset' => true])
            ->assertStatus(422)
            ->assertJsonPath('blockers.open_orders', 1)
            ->assertJsonPath('cooling_off.days', 15);

        $this->assertTrue(MenuItem::query()->whereKey($item)->exists());
        $this->assertEquals(self::GROCER[1], $shop->fresh()->category_child_id);
    }

    public function test_leaving_the_trade_fields_alone_changes_nothing_else_about_the_flow(): void
    {
        $shop = $this->shop();
        $this->stock($shop);
        Sanctum::actingAs($shop);

        // The same trade re-sent (the edit form always posts it) is not a change.
        $this->patchJson('/api/v2/profile', ['name' => 'اسم جديد', 'category_id' => self::GROCER[0], 'category_child_id' => self::GROCER[1]])->assertOk();

        $this->assertSame(1, DB::table('menu_items')->where('business_id', $shop->id)->count());
        $this->assertSame('اسم جديد', $shop->fresh()->name);
    }

    private function order(User $shop, string $status, string $when): void
    {
        $client = User::query()->where('type', 'client')->orderBy('id')->first() ?: $this->markTestSkipped('No client account.');
        DB::table('orders')->insert(['user_id' => $client->id, 'business_id' => $shop->id, 'status' => $status, 'total' => 100, 'address' => 'x', 'created_at' => $when, 'updated_at' => $when]);
    }

    private function trySwitch(User $shop)
    {
        Sanctum::actingAs($shop);

        return $this->patchJson('/api/v2/profile', ['category_id' => self::FURNITURE[0], 'category_child_id' => self::FURNITURE[1], 'confirm_reset' => true]);
    }

    /**
     * «لا يمكن تغيير النشاط الا بعد مرور 15 يوم على اخر عملية بيع او حجز حتى
     * تكون هذه الفترة ضمانا لعدم بيع منتج غير مطابق للمواصفات او خدمة وهمية» —
     * المالك، 2026-10-02.
     */
    public function test_a_sale_within_fifteen_days_holds_the_trade_and_says_when_it_opens(): void
    {
        $shop = $this->shop();
        $this->order($shop, 'completed', now()->subDays(3)->toDateTimeString());

        $res = $this->trySwitch($shop)->assertStatus(422);

        $this->assertSame(15, $res->json('cooling_off.days'));
        $this->assertEqualsWithDelta(12, now()->diffInDays(\Illuminate\Support\Carbon::parse($res->json('cooling_off.available_at'))), 1);
        $this->assertStringContainsString('15', $res->json('message'));
        $this->assertEquals(self::GROCER[1], $shop->fresh()->category_child_id, 'still the same trade');
    }

    public function test_fifteen_days_after_the_last_sale_the_trade_may_change(): void
    {
        $shop = $this->shop();
        $this->order($shop, 'completed', now()->subDays(16)->toDateTimeString());

        $this->trySwitch($shop)->assertOk();

        $this->assertEquals(self::FURNITURE[1], $shop->fresh()->category_child_id);
    }

    public function test_a_cart_or_a_cancelled_order_is_not_a_sale(): void
    {
        $shop = $this->shop();
        $this->order($shop, 'cart', now()->subDay()->toDateTimeString());
        $this->order($shop, 'cancelled', now()->subDay()->toDateTimeString());

        $this->trySwitch($shop)->assertOk();
    }

    public function test_a_recent_booking_holds_the_trade_too(): void
    {
        $shop = $this->shop();
        $client = User::query()->where('type', 'client')->orderBy('id')->first() ?: $this->markTestSkipped('No client account.');
        DB::table('bookings')->insert([
            'user_id' => $client->id, 'business_id' => $shop->id, 'status' => 'completed',
            'date' => now()->subDays(5)->toDateString(), 'time' => '10:00:00',
            'created_at' => now()->subDays(20)->toDateTimeString(), 'updated_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        $this->trySwitch($shop)->assertStatus(422)->assertJsonPath('cooling_off.days', 15);
    }

    public function test_the_wait_is_measured_from_the_latest_of_them(): void
    {
        $shop = $this->shop();
        $this->order($shop, 'completed', now()->subDays(40)->toDateTimeString());
        $this->order($shop, 'completed', now()->subDays(10)->toDateTimeString());

        $this->trySwitch($shop)->assertStatus(422);
    }
}
