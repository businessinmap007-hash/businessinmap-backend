<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RentalAndWinchVocabularySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A rented car's driver / insurance / delivery and a furniture hoist's floor and way in become add-ons a business PRICES
 * and the customer picks, like a hotel's meal plan (owner, 2026-10-18). Rolls back.
 */
class RentalAndWinchVocabularyTest extends TestCase
{
    use DatabaseTransactions;

    private function business(string $rootSlug, int $childId): User
    {
        return User::create([
            'name' => 'Zz Vocab ' . uniqid(),
            'email' => 'zz-vocab-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => (int) DB::table('categories')->where('slug', $rootSlug)->where('parent_id', 0)->value('id'),
            'category_child_id' => $childId,
        ]);
    }

    private function optionId(string $nameAr): int
    {
        return (int) DB::table('options')->where('name_ar', $nameAr)->value('id');
    }

    private function addOns(User $business): array
    {
        return $this->actingAs($business, 'sanctum')->getJson('/api/v2/business/booking-add-ons')->assertOk()->json('data.add_ons');
    }

    public function test_the_seeder_is_idempotent_and_files_each_word_under_its_own_group(): void
    {
        $count = fn () => [
            DB::table('option_groups')->whereIn('name_ar', ['إضافات تأجير السيارة', 'ارتفاع رفع الأثاث', 'طريقة رفع الأثاث'])->count(),
            DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->whereIn('g.name_ar', ['إضافات تأجير السيارة', 'ارتفاع رفع الأثاث', 'طريقة رفع الأثاث'])->count(),
            DB::table('category_child_option')->whereIn('child_id', [188, 556])->count(),
        ];

        (new RentalAndWinchVocabularySeeder())->run();
        $first = $count();
        (new RentalAndWinchVocabularySeeder())->run();

        $this->assertSame([3, 12, $first[2]], [$first[0], $first[1], $first[2]]);
        $this->assertSame($first, $count());
    }

    public function test_a_showroom_prices_the_drivers_and_the_insurance_per_car(): void
    {
        foreach ([188] as $child) {
            $dealer = $this->business('cars', $child);
            $group = collect($this->addOns($dealer))->firstWhere('group', 'إضافات تأجير السيارة');

            $this->assertNotNull($group, "child {$child} offers the rental add-ons");
            $this->assertSame('multiple', $group['selection_type'], 'tick any: a driver AND a child seat');
            $ids = array_column($group['options'], 'id');
            $this->assertContains($this->optionId('مع سائق'), $ids);
            $this->assertContains($this->optionId('تأمين شامل'), $ids);

            $driver = ['id' => $this->optionId('مع سائق')];
            $insurance = ['id' => $this->optionId('تأمين شامل')];
            $this->actingAs($dealer, 'sanctum')->putJson('/api/v2/business/booking-add-ons', [
                'add_ons' => [
                    ['option_id' => $driver['id'], 'enabled' => true, 'value' => 300, 'adjust_type' => 'amount'],
                    ['option_id' => $insurance['id'], 'enabled' => true, 'value' => 10, 'adjust_type' => 'percent'],
                ],
            ])->assertOk();

            $back = collect(collect($this->addOns($dealer))->firstWhere('group', 'إضافات تأجير السيارة')['options'])->keyBy('id');
            $this->assertTrue($back[$driver['id']]['enabled']);
            $this->assertEquals(300, $back[$driver['id']]['value']);
            $this->assertSame('percent', $back[$insurance['id']]['adjust_type']);
            $this->assertFalse($back[$this->optionId('كرسي أطفال')]['enabled'], 'an add-on he did not price is not offered');
        }
    }

    public function test_the_furniture_hoist_prices_the_floor_as_one_choice_and_the_way_in_as_any(): void
    {
        $winch = $this->business('shipping-delivery', 556);
        $groups = collect($this->addOns($winch));

        $height = $groups->firstWhere('group', 'ارتفاع رفع الأثاث');
        $way = $groups->firstWhere('group', 'طريقة رفع الأثاث');

        $this->assertNotNull($height);
        $this->assertNotNull($way);
        $this->assertSame('single', $height['selection_type'], 'the floor is one band');
        $this->assertSame('multiple', $way['selection_type']);
        $this->assertCount(4, $height['options']);
        $this->assertContains($this->optionId('من البلكونة'), array_column($way['options'], 'id'));
    }

    public function test_other_trades_are_not_offered_them(): void
    {
        // «ونش إنقاذ» sits beside the hoist but lifts nothing; a hotel rents rooms, not cars
        $tow = $this->business('shipping-delivery', 244);
        $this->assertNull(collect($this->addOns($tow))->firstWhere('group', 'ارتفاع رفع الأثاث'));

        $hotel = $this->business('tourist-hotels', 536);
        $this->assertNull(collect($this->addOns($hotel))->firstWhere('group', 'إضافات تأجير السيارة'));
    }
}
