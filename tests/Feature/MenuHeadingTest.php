<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The platform hands «مطعم» fourteen food types — مقبلات، مشويات، ساندوتشات… —
 * and a menu item had no column to say which one it was. «مشويات» existed as a
 * permission and never as a heading, so the only grouping a customer could see
 * came from menu_sections, which the merchant types himself and of which there
 * were ZERO on the whole platform.
 *
 * The other half of the same problem: a furniture showroom's item type is the
 * useless «قطعة أثاث» while its heading lives in the line option («غرفة نوم»).
 * Neither vocabulary can be folded into the other, so the heading falls
 * through: section → item type → line option.
 *
 * @see \App\Models\MenuItem::heading()
 */
class MenuHeadingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Any business with a taxonomy child works for «section»/«item_type»
     * headings, but the option-combo tests specifically need a NON-goods-
     * catalog one: {@see \App\Models\MenuItem::heading()} takes the
     * catalog_group branch instead of option_combo the moment
     * {@see \App\Support\MarketCatalogChildren::includes()} is true, and the
     * platform now legitimately turns that on for real goods-catalog
     * businesses (retail-active children, distributed onto menu by
     * MenuServiceDistributionSeeder — see [[three-catalog-shapes]]). Picking
     * "the first business in the table" used to be safe only by accident.
     */
    private function business(): User
    {
        $business = User::query()->where('type', 'business')->whereNotNull('category_child_id')
            ->get()
            ->first(fn (User $b) => ! \App\Support\MarketCatalogChildren::includes($b));

        if (! $business) {
            $this->markTestSkipped('No business.');
        }

        return $business;
    }

    private function item(User $business, string $name, array $attrs = []): MenuItem
    {
        return MenuItem::create($attrs + [
            'business_id' => $business->id,
            'name_ar' => $name,
            'base_price' => 100,
            'is_active' => 1,
            'sort_order' => 0,
        ]);
    }

    private function menu(User $business): array
    {
        return $this->getJson('/api/v2/discovery/menu/' . $business->id)
            ->assertOk()
            ->json('data.sections');
    }

    /** @return array<int,string> the names of the headings the customer sees */
    private function headingNames(array $sections): array
    {
        return array_column($sections, 'name');
    }

    /** A restaurant puts five grills under «مشويات» — one heading, five items. */
    public function test_many_items_share_one_item_type_heading(): void
    {
        $business = $this->business();

        $type = \App\Models\PlatformServiceItemType::query()
            ->whereHas('service', fn ($q) => $q->where('key', PlatformService::KEY_MENU))
            ->orderBy('id')
            ->first();

        $this->assertNotNull($type, 'the menu service has no item types');

        foreach (['مشوي فراخ', 'مشوي كفتة', 'مشوي ريش', 'مشوي كباب', 'مشوي شيش'] as $name) {
            $this->item($business, $name, ['item_type' => $type->key]);
        }

        // Compared through displayName(), which is what the API returns —
        // matching on name_ar passed only by accident, on a row whose English
        // name was blank so the locale fallback landed back on Arabic.
        $label = $type->displayName();

        $sections = collect($this->menu($business))->firstWhere('name', $label);

        $this->assertNotNull($sections, "«{$label}» is not a heading");
        $this->assertSame('item_type', $sections['source']);
        $this->assertCount(5, $sections['items']);
    }

    /**
     * The showroom half: five different bedrooms under «غرفة نوم». Nothing may
     * stop the same line option repeating — a heading is a grouping, not a key.
     */
    public function test_many_items_share_one_line_option_heading(): void
    {
        $business = $this->business();

        $line = Option::query()
            ->whereIn('group_id', OptionGroup::query()->where('price_role', OptionGroup::ROLE_LINE)->select('id'))
            ->first();

        if (! $line) {
            $this->markTestSkipped('No line option.');
        }

        foreach (['غرفة نوم أ', 'غرفة نوم ب', 'غرفة نوم ج', 'غرفة نوم د', 'غرفة نوم هـ'] as $name) {
            $this->item($business, $name)->syncOfferingOptions((int) $line->id);
        }

        $group = collect($this->menu($business))->firstWhere('source', 'option_combo');

        $this->assertNotNull($group, 'the line option produced no heading');
        $this->assertSame($line->displayName(), $group['name']);
        $this->assertCount(5, $group['items']);
    }

    /**
     * The customer payload names the branch itself, not just the heading it
     * produced — the app groups a section's items by branch to let a
     * customer jump to one directly, and needs the id/name to do it.
     */
    public function test_each_items_line_option_is_named_in_its_payload(): void
    {
        $business = $this->business();

        $line = Option::query()
            ->whereIn('group_id', OptionGroup::query()->where('price_role', OptionGroup::ROLE_LINE)->select('id'))
            ->first();

        if (! $line) {
            $this->markTestSkipped('No line option.');
        }

        $item = $this->item($business, 'صنف بفرع');
        $item->syncOfferingOptions((int) $line->id);

        $group = collect($this->menu($business))->firstWhere('source', 'option_combo');
        $this->assertNotNull($group);

        $payload = collect($group['items'])->firstWhere('id', $item->id);
        $this->assertNotNull($payload);
        $this->assertSame((int) $line->id, $payload['line_option']['id']);
        $this->assertSame($line->name_ar, $payload['line_option']['name_ar']);
    }

    /**
     * «لا يوجد شاشة التفاصيل ولا حتى اقسام، والاقسام هنا مثل موبيلات يكون
     * تحتها فروع» — المالك، 2026-09-29. A goods-catalog child whose
     * «مكونات الخدمة» turned `branches_as_sections` on for its line group
     * gets one heading PER BRANCH («موبايل», «تابلت», «ساعة ذكية» each on
     * their own) instead of the ordinary one-heading-per-group behaviour
     * {@see MenuMarketCatalogTest::test_the_customer_facing_heading_is_the_groups_name()}
     * still covers for every child that leaves it off.
     */
    public function test_branches_as_sections_gives_each_branch_its_own_heading(): void
    {
        $business = User::query()->where('type', 'business')->where('category_child_id', 186)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business on child #186 (mobiles_accessories).');

        // Set this child's own item-type config within the transaction rather
        // than trusting the live row — an admin actively curating «مكونات
        // الخدمة»/«طاولة العمل» elsewhere can (and did, mid-session) repoint
        // #186 at a different item type at any moment.
        $menuService = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        DB::table('category_service_configs')->updateOrInsert(
            ['category_id' => (int) $business->category_id, 'child_id' => 186, 'platform_service_id' => $menuService],
            ['config' => json_encode(['allowed_item_types' => ['menu_market']]), 'is_active' => 1, 'updated_at' => now()]
        );

        $this->assertTrue(\App\Support\MarketCatalogChildren::includes($business->fresh()), 'child #186 is expected to be a goods catalog');

        $group = OptionGroup::query()->where('name_ar', 'أجهزة الموبايل')->firstOrFail();
        $mobile = Option::query()->where('group_id', $group->id)->where('name_ar', 'موبايل')->firstOrFail();
        $tablet = Option::query()->where('group_id', $group->id)->where('name_ar', 'تابلت')->firstOrFail();

        $menuService = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        \App\Models\ServiceOptionGroupPlacement::updateOrCreate(
            ['platform_service_id' => $menuService, 'option_group_id' => $group->id, 'child_id' => 186, 'item_type_key' => ''],
            ['usage' => \App\Models\ServiceOptionGroupPlacement::USAGE_SECTION, 'branches_as_sections' => true, 'is_active' => true, 'sort_order' => 10]
        );

        $this->item($business, 'آيفون تجريبي')->syncOfferingOptions((int) $mobile->id);
        $this->item($business, 'تابلت تجريبي')->syncOfferingOptions((int) $tablet->id);

        $sections = $this->menu($business);

        $mobileSection = collect($sections)->firstWhere('name', $mobile->displayName());
        $tabletSection = collect($sections)->firstWhere('name', $tablet->displayName());

        $this->assertNotNull($mobileSection, 'موبايل did not get its own heading');
        $this->assertNotNull($tabletSection, 'تابلت did not get its own heading');
        $this->assertSame('catalog_branch', $mobileSection['source']);
        $this->assertNotSame($mobileSection['name'], $tabletSection['name']);
        $this->assertCount(1, $mobileSection['items']);
        $this->assertCount(1, $tabletSection['items']);
    }

    /** A heading the merchant wrote himself wins over the taxonomy's. */
    public function test_a_hand_written_section_wins(): void
    {
        $business = $this->business();

        $type = DB::table('platform_service_item_types as t')
            ->join('platform_services as s', 's.id', '=', 't.platform_service_id')
            ->where('s.key', PlatformService::KEY_MENU)
            ->value('t.key');

        $section = MenuSection::create([
            'business_id' => $business->id,
            'name_ar' => 'أطباق الشيف',
            'sort_order' => 0,
            'is_active' => 1,
        ]);

        $this->item($business, 'طبق اليوم', ['item_type' => $type, 'menu_section_id' => $section->id]);

        $sections = $this->menu($business);

        $this->assertContains('أطباق الشيف', $this->headingNames($sections));

        $mine = collect($sections)->firstWhere('name', 'أطباق الشيف');
        $this->assertSame('section', $mine['source']);
    }

    /** Nothing is ever hidden: an item with no heading at all still shows. */
    public function test_an_item_with_no_heading_still_appears(): void
    {
        $business = $this->business();

        $this->item($business, 'صنف بلا تصنيف');

        $other = collect($this->menu($business))->firstWhere('source', 'none');

        $this->assertNotNull($other, 'an unclassified item vanished from the menu');
        $this->assertContains('صنف بلا تصنيف', array_column($other['items'], 'name'));
    }

    /**
     * The merchant may only file an item under a kind his own activity is
     * allowed to list — the posted key is never trusted.
     */
    public function test_a_foreign_item_type_is_refused(): void
    {
        $business = $this->business();

        $item = $this->item($business, 'صنف', ['item_type' => 'not_a_real_type']);

        $this->actingAs($business)
            ->put(route('business.menu.update', $item->id), [
                'name_ar' => 'صنف',
                'base_price' => 100,
                'item_type' => 'not_a_real_type',
                'is_active' => 1,
            ]);

        $this->assertNull($item->fresh()->item_type, 'an item type outside the activity was stored');
    }
}
