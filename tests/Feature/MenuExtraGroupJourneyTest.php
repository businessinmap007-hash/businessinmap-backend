<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuItemExtraGroup;
use App\Models\MenuSection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The priced option-groups system («زي الإضافات فى منيو المطاعم»): a group of
 * extras decides on its own whether the customer picks exactly one (radio,
 * like «المقاس») or any number (checkbox, like «الصوصات»). The customer's
 * side is the real guarantee — resolveExtras() must refuse a bypassed
 * multi-pick from a single-select group even if the app's own UI would never
 * send one.
 */
class MenuExtraGroupJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Secret-password1';

    private User $business;
    private MenuItem $item;
    private MenuItemExtraGroup $sizeGroup;
    private MenuItemExtraGroup $sauceGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = new User();
        $this->business->name = 'مطعم الاختيارات';
        $this->business->email = 'extra-groups-' . uniqid() . '@example.test';
        $this->business->phone = '0100' . random_int(1000000, 9999999);
        $this->business->password = self::PASSWORD;
        $this->business->type = User::TYPE_BUSINESS;
        $this->business->api_token = Str::random(80);
        $this->business->save();

        $section = MenuSection::query()->create([
            'business_id' => $this->business->id,
            'name_ar' => 'ساندوتشات',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->item = MenuItem::query()->create([
            'business_id' => $this->business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'برجر',
            'base_price' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // «المقاس» — اختيارٌ واحد.
        $this->sizeGroup = $this->item->extraGroups()->create([
            'name_ar' => 'المقاس',
            'selection_type' => MenuItemExtraGroup::SELECTION_SINGLE,
            'is_active' => true,
        ]);
        $this->item->extras()->create(['name_ar' => 'وسط', 'price' => 0, 'extra_group_id' => $this->sizeGroup->id, 'is_active' => true]);
        $this->item->extras()->create(['name_ar' => 'كبير', 'price' => 20, 'extra_group_id' => $this->sizeGroup->id, 'is_active' => true]);

        // «الصوصات» — اختيارٌ متعدد.
        $this->sauceGroup = $this->item->extraGroups()->create([
            'name_ar' => 'الصوصات',
            'selection_type' => MenuItemExtraGroup::SELECTION_MULTIPLE,
            'is_active' => true,
        ]);
        $this->item->extras()->create(['name_ar' => 'كاتشب', 'price' => 5, 'extra_group_id' => $this->sauceGroup->id, 'is_active' => true]);
        $this->item->extras()->create(['name_ar' => 'مايونيز', 'price' => 5, 'extra_group_id' => $this->sauceGroup->id, 'is_active' => true]);
    }

    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function registerCustomer(): string
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'عميل الاختيارات',
            'email' => 'extra-groups-customer-' . uniqid() . '@example.test',
            'phone' => '0155' . random_int(1000000, 9999999),
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms_accepted' => true,
        ])->assertCreated();

        return $response->json('token');
    }

    private function businessToken(): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $this->business->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }

    public function test_the_customer_facing_menu_carries_the_groups_and_their_selection_type(): void
    {
        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)
            ->assertOk()
            ->json('data');

        $item = $menu['sections'][0]['items'][0];

        $this->assertCount(2, $item['extra_groups']);
        $groupsByName = collect($item['extra_groups'])->keyBy('name');
        $this->assertSame('single', $groupsByName['المقاس']['selection_type']);
        $this->assertSame('multiple', $groupsByName['الصوصات']['selection_type']);

        foreach ($item['extras'] as $extra) {
            $this->assertArrayHasKey('extra_group_id', $extra);
        }
    }

    public function test_picking_one_option_per_group_succeeds(): void
    {
        $token = $this->registerCustomer();

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)->assertOk()->json('data');
        $item = $menu['sections'][0]['items'][0];
        $bigSize = collect($item['extras'])->firstWhere('name', 'كبير');
        $ketchup = collect($item['extras'])->firstWhere('name', 'كاتشب');
        $mayo = collect($item['extras'])->firstWhere('name', 'مايونيز');

        $this->actingWithToken($token)->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $item['id'],
            'qty' => 1,
            'extras' => [$bigSize['id'], $ketchup['id'], $mayo['id']],
        ])->assertSuccessful();

        $cart = $this->actingWithToken($token)->getJson('/api/v2/cart')->assertOk()->json('data');
        $this->assertNotEmpty($cart);
    }

    public function test_picking_two_options_from_a_single_select_group_is_refused(): void
    {
        $token = $this->registerCustomer();

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)->assertOk()->json('data');
        $item = $menu['sections'][0]['items'][0];
        $medium = collect($item['extras'])->firstWhere('name', 'وسط');
        $big = collect($item['extras'])->firstWhere('name', 'كبير');

        // A direct API call bypassing the app's own radio-button UI — the
        // server, not the client, is the real guarantee here.
        $this->actingWithToken($token)->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $item['id'],
            'qty' => 1,
            'extras' => [$medium['id'], $big['id']],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('extras');
    }

    public function test_the_owner_manages_groups_from_the_panel(): void
    {
        $this->withSession([])->actingAs($this->business, 'web');

        $this->post('/business/menu/' . $this->item->id . '/extra-groups', [
            'name_ar' => 'الإضافات',
            'selection_type' => 'multiple',
            'is_active' => '1',
        ])->assertRedirect();

        $created = $this->item->extraGroups()->where('name_ar', 'الإضافات')->firstOrFail();

        $this->put('/business/menu/' . $this->item->id . '/extra-groups/' . $created->id, [
            'name_ar' => 'الإضافات',
            'selection_type' => 'single',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('single', $created->fresh()->selection_type);

        // Deleting a group orphans its extras (nullOnDelete) — they stay
        // sellable as standalone checkboxes, they don't vanish with it.
        $extra = $this->item->extras()->create([
            'name_ar' => 'تجربة',
            'price' => 1,
            'extra_group_id' => $created->id,
            'is_active' => true,
        ]);

        $this->delete('/business/menu/' . $this->item->id . '/extra-groups/' . $created->id)
            ->assertRedirect();

        $this->assertNull($extra->fresh()->extra_group_id);
        $this->assertModelMissing($created);
    }

    public function test_a_stranger_cannot_manage_another_businesss_groups(): void
    {
        $stranger = new User();
        $stranger->name = 'صاحب محل آخر';
        $stranger->email = 'stranger-' . uniqid() . '@example.test';
        $stranger->phone = '0111' . random_int(1000000, 9999999);
        $stranger->password = self::PASSWORD;
        $stranger->type = User::TYPE_BUSINESS;
        $stranger->api_token = Str::random(80);
        $stranger->save();

        $this->withSession([])->actingAs($stranger, 'web');

        $this->post('/business/menu/' . $this->item->id . '/extra-groups', [
            'name_ar' => 'تسلل',
            'selection_type' => 'multiple',
            'is_active' => '1',
        ])->assertNotFound();
    }
}
