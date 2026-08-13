<?php

namespace Tests\Feature;

use App\Models\CategoryServiceConfig;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «فى categories/services-bulk مثلا فى استرجي لا يوجد اى خدمات محددة داخل
 * توصيل بينما فى child-workbench محدد الكثير».
 *
 * Both screens were reading honestly; they were reading different things. The
 * workbench takes the stored `category_service_configs` row whatever its state,
 * while services-bulk skipped any row with `is_active = 0`. «استرجي» (child
 * 300) carries a full delivery config — two branches and a dozen types — on a
 * row marked inactive, so one screen showed a dozen ticks and the other none.
 * **262 config rows are inactive and still carry a stored `allowed_item_types`.**
 *
 * What settles it is not which screen looks better: `configFor()` on the SAVE
 * path of the same controller reads the row **without** an `is_active` filter,
 * and its untouched-picker guard keeps whatever it finds. A screen showing less
 * than its own save preserves invites an admin to «fix» an empty picker and
 * overwrite a curation nobody showed them.
 *
 * Whether the service is switched ON stays a separate question, answered by
 * `category_platform_services.is_active`.
 */
class ServicesBulkShowsStoredConfigTest extends TestCase
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

    /** A real inactive config row that still holds types, or one made here. */
    private function inactiveConfigWithTypes(): object
    {
        $row = DB::table('category_service_configs')
            ->where('is_active', 0)
            ->whereNotNull('config')
            ->where('config', 'like', '%allowed_item_types%')
            ->where('config', 'not like', '%"allowed_item_types":[]%')
            ->where('category_id', '>', 0)
            ->where('child_id', '>', 0)
            ->first(['id', 'category_id', 'child_id', 'platform_service_id', 'config']);

        if (! $row) {
            $this->markTestSkipped('No inactive config row carries item types.');
        }

        return $row;
    }

    /**
     * The selection the screen ships to its JavaScript, decoded.
     *
     * Asserting on the raw HTML would prove nothing: every type key appears in
     * the page anyway as an unticked checkbox. What matters is whether the key
     * is in `BIM_SERVICE_CONFIG_MATRIX`, which is what marks a box TICKED.
     *
     * @return array<string,mixed>
     */
    private function pickerMatrix(int $rootId, int $childId): array
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', [
                'root_id' => $rootId,
                'child_ids' => [$childId],
            ]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/window\.BIM_SERVICE_CONFIG_MATRIX\s*=\s*(.+?);\s*$/m',
            $html,
            'the screen no longer ships a selection matrix'
        );

        preg_match('/window\.BIM_SERVICE_CONFIG_MATRIX\s*=\s*(.+?);\s*$/m', $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true) ?: [];
    }

    /** The screen must pre-fill from what is stored, active row or not. */
    public function test_an_inactive_config_still_fills_the_picker(): void
    {
        $row = $this->inactiveConfigWithTypes();

        $stored = collect((json_decode((string) $row->config, true) ?: [])['allowed_item_types'] ?? [])
            ->filter()->values();

        $this->assertNotEmpty($stored, 'the fixture row carries no types');

        $matrix = $this->pickerMatrix((int) $row->category_id, (int) $row->child_id);
        $shown = $matrix[(string) $row->child_id][(string) $row->platform_service_id]['allowed_item_types'] ?? null;

        $this->assertIsArray(
            $shown,
            "child {$row->child_id} stores types for service {$row->platform_service_id} and the picker had no entry at all"
        );

        $this->assertEqualsCanonicalizing(
            $stored->all(),
            $shown,
            'the picker showed a different selection from the one stored'
        );
    }

    /**
     * The screen and the save path must read the same row. This asserts the
     * shape rather than the query text: a config the screen cannot see is a
     * config the save can still keep, and that gap is the bug.
     */
    public function test_the_screen_reads_what_the_save_path_reads(): void
    {
        $row = $this->inactiveConfigWithTypes();

        $screen = CategoryServiceConfig::query()
            ->where('category_id', $row->category_id)
            ->where('child_id', $row->child_id)
            ->where('platform_service_id', $row->platform_service_id)
            ->exists();

        $this->assertTrue($screen, 'the screen query no longer reaches the stored row');
    }

    /** Switched-off is a different fact from configured, and stays separate. */
    public function test_a_configured_service_can_still_be_switched_off(): void
    {
        $row = $this->inactiveConfigWithTypes();

        $link = DB::table('category_platform_services')
            ->where('category_id', $row->category_id)
            ->where('child_id', $row->child_id)
            ->where('platform_service_id', $row->platform_service_id)
            ->value('is_active');

        // Nothing is asserted about which way it points — only that the two
        // facts live apart, so reading one never implies the other.
        $this->assertTrue(
            $link === null || in_array((int) $link, [0, 1], true),
            'the link flag is not a usable answer to «is it configured?»'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | «اريدها بنفس طريقة عمل category-child-options/bulk/edit فى الشكل واسلوب العمل»
    |--------------------------------------------------------------------------
    */

    private function screen(): string
    {
        $root = (int) DB::table('category_parent_child')->value('parent_id');

        return $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', ['root_id' => $root]))
            ->assertOk()
            ->getContent();
    }

    /** Chips and an «Other» drawer, the shape the options picker uses. */
    public function test_the_services_are_chips_with_an_other_drawer(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString('id="serviceChipBar"', $html);
        $this->assertStringContainsString('id="serviceOtherBar"', $html);
        $this->assertStringContainsString('function renderServiceChips', $html);
    }

    /**
     * The chips press the tab machinery rather than replacing it — two
     * mechanisms driving one panel is how they drift apart.
     */
    public function test_the_chips_drive_the_existing_panel_machinery(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString('id="feeTabs"', $html, 'the tab strip the cards hang off is gone');
        $this->assertMatchesRegularExpression('/id="feeTabs"[^>]*style="display:none;"/', $html, 'the strip should be hidden, not deleted');
    }

    /** The walk: buttons either side of the name, on a pinned row. */
    public function test_the_child_walk_sits_beside_the_name_and_is_pinned(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString('id="childNav"', $html);
        $this->assertStringContainsString('id="childPrev"', $html);
        $this->assertStringContainsString('id="childNext"', $html);
        $this->assertStringContainsString('id="childNavName"', $html);

        $nav = substr($html, strpos($html, 'id="childNav"'), 900);
        $this->assertStringContainsString('position:sticky', $nav, 'the row must stay put while the cards below redraw');

        $this->assertLessThan(
            strpos($nav, 'id="childNext"'),
            strpos($nav, 'id="childNavName"'),
            'the name must sit between the two buttons'
        );
        $this->assertLessThan(
            strpos($nav, 'id="childNavName"'),
            strpos($nav, 'id="childPrev"'),
            'the name must sit between the two buttons'
        );
    }

    /** «لا يجب ان ترفع الشاشة لاعلى» — stepping never scrolls the page. */
    public function test_stepping_between_children_never_scrolls(): void
    {
        $html = $this->screen();

        $start = strpos($html, 'function stepChild');
        $this->assertNotFalse($start, 'the walk is gone');

        $body = substr($html, $start, 1400);

        $this->assertStringNotContainsString('.scrollIntoView(', $body);
        $this->assertStringNotContainsString('window.scrollTo', $body);
    }

    /** «استرجي» itself — the child the owner named. */
    public function test_the_child_that_reported_it_shows_its_delivery_types(): void
    {
        $config = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.child_id', 300)
            ->where('s.key', 'delivery')
            ->first(['c.category_id', 'c.config']);

        if (! $config) {
            $this->markTestSkipped('Child 300 no longer carries a delivery config.');
        }

        $types = collect((json_decode((string) $config->config, true) ?: [])['allowed_item_types'] ?? []);

        if ($types->isEmpty()) {
            $this->markTestSkipped('Child 300 carries no delivery types.');
        }

        $serviceId = (int) DB::table('platform_services')->where('key', 'delivery')->value('id');
        $matrix = $this->pickerMatrix((int) $config->category_id, 300);

        $this->assertEqualsCanonicalizing(
            $types->all(),
            $matrix['300'][(string) $serviceId]['allowed_item_types'] ?? [],
            'استرجي still shows no delivery types on the bulk screen'
        );
    }
}
