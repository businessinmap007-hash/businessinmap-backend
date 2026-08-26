<?php

namespace Tests\Feature;

use App\Models\CategoryChildServiceFee;
use App\Models\FeeGroup;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Three things are decided per (root, child), and until now they lived on three
 * pages: what describes it (options), what it may sell (services), and what the
 * platform charges for selling it (fees). The fee page never mentioned the
 * other two, so «this child may sell bookings» and «bookings cost it 5%» were
 * answered in different places and could disagree without anyone noticing.
 *
 * They are now one screen on one key. And since 2026-08-26 the fee itself is
 * ONE row per (root, child) — not one per service — so a business offering
 * booking AND menu is charged once, not twice: «بدل ما يكون هناك رسوم بوكينج
 * -منيو -دليفري … يكون هناك رسم موحّد». The BULK fee screen stays — it
 * answers a different question, «all of these at once».
 */
class ChildWorkbenchFeesTest extends TestCase
{
    use DatabaseTransactions;

    private int $rootId;

    private int $childId;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $pair = DB::table('category_parent_child')->first(['parent_id', 'child_id']);
        $serviceId = (int) PlatformService::query()->where('is_active', 1)->value('id');

        if (! $pair || $serviceId <= 0) {
            $this->markTestSkipped('Needs a (root, child) pair and an active service.');
        }

        $this->rootId = (int) $pair->parent_id;
        $this->childId = (int) $pair->child_id;
        $this->serviceId = $serviceId;
    }

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    private function saveFee(array $fee)
    {
        return $this->actingAs($this->admin())->post(route('admin.child-workbench.fees', [], false), [
            'root_id' => $this->rootId,
            'child_id' => $this->childId,
            'fee' => $fee,
        ]);
    }

    private function fee(): ?CategoryChildServiceFee
    {
        return CategoryChildServiceFee::query()
            ->where('category_id', $this->rootId)
            ->where('child_id', $this->childId)
            ->first();
    }

    /** All three axes on one screen, keyed the same way. */
    public function test_the_workbench_shows_options_services_and_fee_together(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.child-workbench.index', [
                'root_id' => $this->rootId,
                'child_id' => $this->childId,
            ], false))
            ->assertOk();

        $this->assertNotNull($response->viewData('optionPanel'));
        $this->assertNotNull($response->viewData('servicePanel'));
        $this->assertNotNull($response->viewData('feePanel'));
        $this->assertNotNull($response->viewData('feeGroups'));
    }

    public function test_a_fee_is_saved_for_a_child_offered_at_least_one_service(): void
    {
        app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $this->serviceId);

        $this->saveFee([
            'is_active' => 1,
            'business_fee_enabled' => 1,
            'business_fee_type' => 'percent',
            'business_fee_amount' => 7.5,
            'client_fee_enabled' => 0,
            'client_fee_amount' => 0,
            'currency' => 'egp',
        ])->assertRedirect();

        $fee = $this->fee();

        $this->assertNotNull($fee);
        $this->assertTrue((bool) $fee->is_active);
        $this->assertTrue((bool) $fee->business_fee_enabled);
        $this->assertSame('percent', $fee->business_fee_type);
        $this->assertEquals(7.5, $fee->business_fee_amount);
        $this->assertFalse((bool) $fee->client_fee_enabled);
        $this->assertSame('EGP', $fee->currency, 'the currency is normalised');
    }

    /**
     * One row covers every service the child offers — enabling a second
     * service must not create a second fee row.
     */
    public function test_one_fee_row_covers_every_service_this_child_offers(): void
    {
        app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $this->serviceId);
        $this->saveFee(['is_active' => 1, 'business_fee_enabled' => 1, 'business_fee_amount' => 5]);

        $secondServiceId = (int) PlatformService::query()->where('is_active', 1)
            ->where('id', '!=', $this->serviceId)->value('id');

        if ($secondServiceId > 0) {
            app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $secondServiceId);
        }

        $this->assertSame(
            1,
            CategoryChildServiceFee::query()
                ->where('category_id', $this->rootId)->where('child_id', $this->childId)->count()
        );
    }

    /**
     * A fee on a child offering no service at all is a row nothing will ever
     * read. The screen refuses to create one and says so rather than failing
     * quietly.
     */
    public function test_a_fee_is_refused_for_a_child_offering_no_service(): void
    {
        // Disable every service this real taxonomy child might already carry —
        // disabling only one is not enough to make $offersAnyService false if
        // it happens to offer others too.
        DB::table('category_platform_services')
            ->where('category_id', $this->rootId)
            ->where('child_id', $this->childId)
            ->update(['is_active' => 0]);

        CategoryChildServiceFee::query()
            ->where('category_id', $this->rootId)
            ->where('child_id', $this->childId)
            ->delete();

        $this->saveFee(['is_active' => 1, 'business_fee_enabled' => 1, 'business_fee_amount' => 10])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertNull($this->fee(), 'a fee was written for a child that offers nothing');
    }

    /** A junk calculation type must not reach the column. */
    public function test_an_unknown_fee_type_falls_back_to_fixed(): void
    {
        app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $this->serviceId);

        $this->saveFee([
            'is_active' => 1,
            'business_fee_enabled' => 1,
            'business_fee_type' => 'nonsense',
            'business_fee_amount' => 3,
        ])->assertRedirect();

        $this->assertSame(CategoryChildServiceFee::CALC_TYPE_FIXED, $this->fee()->business_fee_type);
    }

    /**
     * Assigning a fee group makes the group's numbers the ones actually
     * charged — «مجموعة أبناء» — even though this row's own columns are
     * untouched.
     */
    public function test_assigning_a_fee_group_is_what_actually_gets_charged(): void
    {
        app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $this->serviceId);

        $group = FeeGroup::create([
            'name_ar' => 'مجموعة اختبار',
            'business_fee_amount' => 9,
            'client_fee_amount' => 3,
        ]);

        $this->saveFee([
            'is_active' => 1,
            'fee_group_id' => $group->id,
            'business_fee_enabled' => 1,
            'business_fee_amount' => 999,
            'client_fee_enabled' => 1,
            'client_fee_amount' => 999,
        ])->assertRedirect();

        $fee = $this->fee();

        $this->assertSame((int) $group->id, (int) $fee->fee_group_id);
        $this->assertSame(9.0, $fee->amountFor(CategoryChildServiceFee::PAYER_BUSINESS, 100));
        $this->assertSame(3.0, $fee->amountFor(CategoryChildServiceFee::PAYER_CLIENT, 100));
    }

    /** The retired screens land somewhere useful instead of 404. */
    public function test_the_retired_screens_redirect_rather_than_break(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.service-catalog-matrix.index', [], false))
            ->assertRedirect(route('admin.categories.services-bulk.index', [], false));

        $this->actingAs($this->admin())
            ->get(route('admin.category-child-service-fees.edit', ['categoryChild' => $this->childId], false))
            ->assertRedirect(route('admin.child-workbench.index', [
                'root_id' => $this->rootId,
                'child_id' => $this->childId,
            ], false));
    }
}
