<?php

namespace Tests\Feature;

use App\Models\CategoryChildServiceFee;
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
 * They are now one screen on one key. The BULK fee screen stays — it answers a
 * different question, «all of these at once».
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

    private function saveFees(array $fees)
    {
        return $this->actingAs($this->admin())->post(route('admin.child-workbench.fees', [], false), [
            'root_id' => $this->rootId,
            'child_id' => $this->childId,
            'fees' => $fees,
        ]);
    }

    private function fee(): ?CategoryChildServiceFee
    {
        return CategoryChildServiceFee::query()
            ->where('category_id', $this->rootId)
            ->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)
            ->first();
    }

    /** All three axes on one screen, keyed the same way. */
    public function test_the_workbench_shows_options_services_and_fees_together(): void
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
    }

    public function test_a_fee_is_saved_for_a_service_the_child_is_offered(): void
    {
        app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $this->serviceId);

        $this->saveFees([
            $this->serviceId => [
                'is_active' => 1,
                'business_fee_enabled' => 1,
                'business_fee_type' => 'percent',
                'business_fee_amount' => 7.5,
                'client_fee_enabled' => 0,
                'client_fee_amount' => 0,
                'currency' => 'egp',
            ],
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
     * A fee on a service the child cannot sell is a row nothing will ever read.
     * The screen refuses to create one and says so rather than failing quietly.
     */
    public function test_a_fee_is_refused_for_a_service_the_child_is_not_offered(): void
    {
        app(ChildServiceWriter::class)->disable($this->rootId, $this->childId, $this->serviceId);

        CategoryChildServiceFee::query()
            ->where('category_id', $this->rootId)
            ->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)
            ->delete();

        $this->saveFees([
            $this->serviceId => ['is_active' => 1, 'business_fee_enabled' => 1, 'business_fee_amount' => 10],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertNull($this->fee(), 'a fee was written for a service this child cannot sell');
    }

    /** A junk calculation type must not reach the column. */
    public function test_an_unknown_fee_type_falls_back_to_fixed(): void
    {
        app(ChildServiceWriter::class)->enable($this->rootId, $this->childId, $this->serviceId);

        $this->saveFees([
            $this->serviceId => [
                'is_active' => 1,
                'business_fee_enabled' => 1,
                'business_fee_type' => 'nonsense',
                'business_fee_amount' => 3,
            ],
        ])->assertRedirect();

        $this->assertSame(CategoryChildServiceFee::CALC_TYPE_FIXED, $this->fee()->business_fee_type);
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
