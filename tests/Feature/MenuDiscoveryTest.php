<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * Customer-facing menu browse: GET /api/v2/discovery/menu/{business} returns the
 * active menu grouped by sections, each item with its price + variants + extras.
 */
class MenuDiscoveryTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private function business(): User
    {
        return User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_menu_is_grouped_by_section_with_variants_and_extras(): void
    {
        $biz = $this->business();
        $section = $this->seedSection($biz->id, 'أطباق رئيسية');
        $item = $this->seedMenuItem($biz->id, $section->id, 50.0, 'برجر');
        $this->seedVariant($item, 'كبير', price: 70.0, default: true);
        $this->seedExtra($item, 'جبنة', price: 10.0);

        $res = $this->getJson("/api/v2/discovery/menu/{$biz->id}")->assertOk()->assertJsonPath('success', true);

        $sections = collect($res->json('data.sections'));
        $sec = $sections->firstWhere('name', 'أطباق رئيسية');
        $this->assertNotNull($sec, 'section must surface');

        $row = collect($sec['items'])->firstWhere('id', $item->id);
        $this->assertNotNull($row);
        $this->assertSame(50.0, (float) $row['base_price']);
        $this->assertSame(70.0, (float) $row['variants'][0]['price']);
        $this->assertTrue((bool) $row['variants'][0]['is_default']);
        $this->assertSame(10.0, (float) $row['extras'][0]['price']);
    }

    public function test_inactive_items_and_sections_do_not_surface(): void
    {
        $biz = $this->business();
        $item = $this->seedMenuItem($biz->id, null, 30.0, 'صنف مخفي');
        $item->update(['is_active' => 0]);

        $res = $this->getJson("/api/v2/discovery/menu/{$biz->id}")->assertOk();

        $ids = collect($res->json('data.sections'))->flatMap(fn ($s) => collect($s['items'])->pluck('id'))->all();
        $this->assertNotContains($item->id, $ids, 'inactive item must be hidden');
    }

    public function test_ungrouped_items_fall_into_other_bucket(): void
    {
        $biz = $this->business();
        $item = $this->seedMenuItem($biz->id, null, 25.0, 'صنف بلا قسم');

        $res = $this->getJson("/api/v2/discovery/menu/{$biz->id}")->assertOk();

        $other = collect($res->json('data.sections'))->firstWhere('name', 'أخرى');
        $this->assertNotNull($other, 'ungrouped bucket must exist');
        $this->assertContains($item->id, collect($other['items'])->pluck('id')->all());
    }

    public function test_missing_business_returns_404(): void
    {
        $this->getJson('/api/v2/discovery/menu/999999999')->assertNotFound();
    }
}
