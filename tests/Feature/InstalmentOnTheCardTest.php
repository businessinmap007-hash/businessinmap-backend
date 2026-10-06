<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «امكانية تقسيط تظهر على الكارت للعميل وللفلترة (غرف نوم — قسط) وكذلك السيارة والموبايل» — المالك،
 * 2026-10-05. A furniture factory writes cash AND an instalment plan on a bedroom: the card says it, and the
 * search can be narrowed to what is bought on instalments. Rolls back.
 */
class InstalmentOnTheCardTest extends TestCase
{
    use \Tests\Concerns\AnswersDelivery;
    use DatabaseTransactions;

    private User $factory;
    private int $withPlan;
    private int $cashOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->factory->id, 'option_id' => $bedroom], []);

        Sanctum::actingAs($this->factory);
        $this->withPlan = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة قسط للبطاقة', 'base_price' => 30000, 'line_option_id' => $bedroom])->assertCreated()->json('data.id');
        $this->cashOnly = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة كاش للبطاقة', 'base_price' => 25000, 'line_option_id' => $bedroom])->assertCreated()->json('data.id');
        // Two plans: the card quotes the lowest month.
        $this->putJson("/api/v2/business/menu/items/{$this->withPlan}/payment-plans", ['plans' => [
            ['months' => 6, 'total_price' => 33000],
            ['months' => 12, 'down' => 6000, 'total_price' => 36000],
        ]])->assertOk();
    }

    private function card(int $id): ?array
    {
        $sections = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu/' . $this->factory->id)->assertOk()->json('data.sections');

        return collect($sections)->flatMap(fn ($s) => $s['items'])->firstWhere('id', $id);
    }

    public function test_the_card_says_the_lowest_month_on_instalments(): void
    {
        $card = $this->card($this->withPlan);

        $this->assertEquals(12, $card['installment']['months']);
        $this->assertEquals(2500, $card['installment']['monthly'], '(36000 − 6000 down) over 12 months');
        $this->assertCount(2, $card['payment_plans']);
    }

    public function test_a_cash_only_item_has_no_instalment_line(): void
    {
        $this->assertNull($this->card($this->cashOnly)['installment']);
    }

    public function test_the_search_can_be_narrowed_to_what_is_bought_on_instalments(): void
    {
        $all = collect($this->getJson('/api/v2/discovery/menu-items/search?q=للبطاقة')->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->withPlan, $this->cashOnly], $all);

        $items = collect($this->getJson('/api/v2/discovery/menu-items/search?q=للبطاقة&installments=1')->assertOk()->json('data.items'));

        $this->assertSame([$this->withPlan], $items->pluck('id')->all(), 'غرف نوم — قسط');
        $this->assertEquals(2500, $items[0]['installment']['monthly']);
    }

    public function test_the_offerings_of_a_specialty_can_be_narrowed_to_instalments_and_say_the_monthly(): void
    {
        $base = '/api/v2/discovery/offerings?child_id=116&category_id=23&per_page=50';
        $all = collect($this->getJson($base)->assertOk()->json('data.offerings.data'));
        $this->assertTrue($all->pluck('id')->contains($this->cashOnly));

        $on = collect($this->getJson($base . '&installments=1')->assertOk()->json('data.offerings.data'));
        $this->assertTrue($on->pluck('id')->contains($this->withPlan));
        $this->assertFalse($on->pluck('id')->contains($this->cashOnly), 'cash only is not on instalments');
        $this->assertEquals(2500, $on->firstWhere('id', $this->withPlan)['installment']['monthly']);
    }

    public function test_the_offerings_list_is_the_root_opened_not_every_root_of_the_child(): void
    {
        // The factory is filed under مصانع (23): opened through شركات (22) it is not there.
        $other = collect($this->getJson('/api/v2/discovery/offerings?child_id=116&category_id=22&per_page=50')->assertOk()->json('data.offerings.data'));
        $this->assertFalse($other->pluck('id')->contains($this->withPlan));
    }
}
