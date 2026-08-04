<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An order line pointed at a menu item and looked its name up live, so «غرفة
 * نوم — مودرن» would silently become «غرفة نوم — كلاسيك» the day the merchant
 * re-tagged the item, and lose its name entirely if the item were deleted.
 *
 * The price on a line has always been a snapshot for exactly that reason. What
 * the line WAS now gets the same treatment.
 *
 * @see \App\Models\OrderItem::booted()
 */
class OrderLineLabelTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $user = User::query()->where('type', 'business')->first();

        if (! $user) {
            $this->markTestSkipped('No business account.');
        }

        return $user;
    }

    private function optionInRole(string $role): ?int
    {
        return DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', $role)
            ->where('g.is_active', 1)
            ->value('o.id');
    }

    private function item(User $business, string $name = 'صنف اختبار'): MenuItem
    {
        return MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => $name,
            'base_price' => 100,
            'is_active' => 1,
            'sort_order' => 0,
        ]);
    }

    private function order(User $business): Order
    {
        return Order::create([
            'user_id' => User::query()->where('type', 'client')->value('id'),
            'business_id' => $business->id,
            'status' => 'draft',
            'total' => 0,
            'final_total' => 0,
            'address' => '',
        ]);
    }

    private function line(Order $order, MenuItem $item)
    {
        return $order->items()->create([
            'menu_id' => $item->id,
            'offering_type' => MenuItem::class,
            'offering_id' => $item->id,
            'qty' => 1,
            'price' => $item->base_price,
            'total_price' => $item->base_price,
        ]);
    }

    /** «صنف اختبار — <line> — <modifier>» is frozen onto the line. */
    public function test_a_line_freezes_what_was_ordered(): void
    {
        $business = $this->business();
        $line = $this->optionInRole(OptionGroup::ROLE_LINE);
        $modifier = $this->optionInRole(OptionGroup::ROLE_MODIFIER);

        if (! $line || ! $modifier) {
            $this->markTestSkipped('No priced vocabulary exists.');
        }

        $item = $this->item($business);
        $item->syncOfferingOptions($line, [$modifier]);

        $row = $this->line($this->order($business), $item);

        $this->assertNotNull($row->offering_label);
        $this->assertStringContainsString('صنف اختبار', $row->offering_label);

        // the label is frozen in the language the order was placed in
        $this->assertStringContainsString(
            \App\Models\Option::query()->find($line)->displayName,
            $row->offering_label
        );
    }

    /** Re-tagging the item must not rewrite an order already placed. */
    public function test_re_tagging_the_item_does_not_change_a_placed_order(): void
    {
        $business = $this->business();
        $first = $this->optionInRole(OptionGroup::ROLE_LINE);

        $other = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->where('o.id', '!=', $first)
            ->value('o.id');

        if (! $first || ! $other) {
            $this->markTestSkipped('Fewer than two line options exist.');
        }

        $item = $this->item($business);
        $item->syncOfferingOptions($first);

        $row = $this->line($this->order($business), $item);
        $frozen = $row->offering_label;

        $item->syncOfferingOptions((int) $other);
        $item->update(['name_ar' => 'اسم جديد تمامًا']);

        $this->assertSame($frozen, $row->fresh()->offering_label);
        $this->assertSame($frozen, $row->fresh()->displayName());
    }

    /** Deleting the item must not leave the line nameless. */
    public function test_a_deleted_item_leaves_the_line_its_name(): void
    {
        $business = $this->business();
        $item = $this->item($business, 'صنف سيُحذف');

        $row = $this->line($this->order($business), $item);

        $item->delete();

        $this->assertStringContainsString('صنف سيُحذف', $row->fresh()->displayName());
    }

    /** An item with no vocabulary still freezes its own name. */
    public function test_an_untagged_item_freezes_its_plain_name(): void
    {
        $business = $this->business();
        $item = $this->item($business, 'برجر لحم');

        $row = $this->line($this->order($business), $item);

        $this->assertSame('برجر لحم', $row->offering_label);
    }

    /** A label supplied by the caller is never overwritten. */
    public function test_an_explicit_label_is_kept(): void
    {
        $business = $this->business();
        $item = $this->item($business);

        $row = $this->order($business)->items()->create([
            'menu_id' => $item->id,
            'offering_type' => MenuItem::class,
            'offering_id' => $item->id,
            'offering_label' => 'اسم مكتوب يدويًا',
            'qty' => 1,
            'price' => 10,
            'total_price' => 10,
        ]);

        $this->assertSame('اسم مكتوب يدويًا', $row->offering_label);
    }

    /** The customer's cart shows the frozen name. */
    public function test_the_cart_payload_shows_the_frozen_name(): void
    {
        $business = $this->business();
        $client = User::query()->where('type', 'client')->first();

        if (! $client) {
            $this->markTestSkipped('No client account.');
        }

        $item = $this->item($business, 'كنبة ركنة');
        $order = Order::create([
            'user_id' => $client->id,
            'business_id' => $business->id,
            'status' => \App\Services\CustomerCartService::STATUS_CART,
            'total' => 0,
            'final_total' => 0,
            'address' => '',
        ]);

        $this->line($order, $item);

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v2/cart');

        $response->assertOk();

        $this->assertStringContainsString('كنبة ركنة', json_encode($response->json(), JSON_UNESCAPED_UNICODE));
    }
}
