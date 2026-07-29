<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The Taxonomy Lab sandbox: the `_new` clone tables exist, mirror the live
 * atoms, and start with the groupings empty (that is what the lab rebuilds).
 * The landing renders for a catalog admin.
 */
class TaxonomyLabTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->orderBy('id')->firstOrFail();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::CATALOG);
        Bouncer::refresh();

        return $admin;
    }

    public function test_sandbox_tables_all_exist(): void
    {
        foreach ([
            'platform_services_new',
            'platform_service_item_types_new',
            'platform_service_item_groups_new',
            'platform_service_item_group_type_new',
            'option_groups_new',
            'options_new',
            'category_child_option_new',
        ] as $t) {
            $this->assertTrue(Schema::hasTable($t), "missing sandbox table {$t}");
        }
    }

    public function test_atoms_mirror_the_live_tables_and_groupings_start_empty(): void
    {
        // Atoms copied faithfully.
        $this->assertSame(
            DB::table('platform_services')->count(),
            DB::table('platform_services_new')->count()
        );
        $this->assertSame(
            DB::table('platform_service_item_types')->count(),
            DB::table('platform_service_item_types_new')->count()
        );
        $this->assertSame(
            DB::table('options')->count(),
            DB::table('options_new')->count()
        );
        $this->assertSame(
            DB::table('category_child_option')->count(),
            DB::table('category_child_option_new')->count()
        );

        // Groupings are the clean slate we rebuild.
        $this->assertSame(0, DB::table('platform_service_item_groups_new')->count());
        $this->assertSame(0, DB::table('platform_service_item_group_type_new')->count());
        $this->assertSame(0, DB::table('option_groups_new')->count());

        // Options copied ungrouped.
        $this->assertSame(0, DB::table('options_new')->whereNotNull('group_id')->count());
    }

    public function test_lab_landing_renders_for_a_catalog_admin(): void
    {
        $res = $this->actingAs($this->admin())->get('/admin/taxonomy-lab')->assertOk();

        $stats = $res->viewData('stats');
        $this->assertSame(DB::table('platform_services_new')->count(), $stats['services']);
        $this->assertSame(DB::table('options_new')->count(), $stats['options']);
    }
}
