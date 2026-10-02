<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كمثال فى مصانع اثاث هل يمكننى الان اضافة غرفة نوم - وصفها مودرن - نوع الخشب
 * زان واضافة الصور وكتابة الوصف والسعر … اختيار الوصف ونوع الخشب من مجموعات
 * الخيارات» — المالك، 2026-10-02. Rolls back.
 */
class FurnitureFactoryItemTest extends TestCase
{
    use DatabaseTransactions;

    private function factory(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->first()
            ?: $this->markTestSkipped('No furniture factory (child #116 under «مصانع»).');
    }

    private function option(string $group, string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->where('o.name_ar', $name)->value('o.id');
    }

    /** A real 1×1 PNG — this machine has no GD for the framework's fake image. */
    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    public function test_a_bedroom_is_modern_and_beech_with_photos_a_description_and_a_price(): void
    {
        $shop = $this->factory();
        $bedroom = $this->option('أثاث وتشطيب منزلي', 'غرفة نوم');
        $modern = $this->option('طراز الأثاث', 'مودرن');
        $beech = $this->option('أنواع الأخشاب', 'زان');

        // What the factory has ticked is what it may say.
        foreach ([$bedroom, $modern, $beech] as $id) {
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $id], []);
        }
        // «مكونات الخدمة»' own curation for this trade: the wood and the style describe, they are not what is sold.
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        foreach (['أنواع الأخشاب', 'طراز الأثاث'] as $group) {
            DB::table('service_option_group_placements')->updateOrInsert(
                ['platform_service_id' => $menu, 'option_group_id' => (int) DB::table('option_groups')->where('name_ar', $group)->value('id'), 'child_id' => 116, 'item_type_key' => ''],
                ['usage' => 'descriptive', 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Sanctum::actingAs($shop);
        $vocab = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');

        // «غرفة نوم» is what it IS; wood and style are choices that DESCRIBE it.
        $lineGroups = array_column($vocab['lines'], 'group_name');
        $this->assertContains('أثاث وتشطيب منزلي', $lineGroups);
        $this->assertNotContains('أنواع الأخشاب', $lineGroups, 'wood is not a second «what is it»');
        $this->assertNotContains('طراز الأثاث', $lineGroups);
        $describing = collect($vocab['modifiers'])->keyBy('group_name');
        $this->assertTrue($describing['أنواع الأخشاب']['descriptive'], 'a client opens the full item form when a group describes');
        $this->assertFalse(collect($vocab['lines'])->firstWhere('group_name', 'أثاث وتشطيب منزلي')['descriptive']);
        $this->assertContains('زان', array_column($describing['أنواع الأخشاب']['options'], 'name_ar'));
        $this->assertContains('مودرن', array_column($describing['طراز الأثاث']['options'], 'name_ar'));

        $item = $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'غرفة نوم ماستر',
            'description_ar' => 'سرير ٢٠٠ سم ودولاب ٦ ضلف وتسريحة ومرآة',
            'base_price' => 48500,
            'line_option_id' => $bedroom,
            'modifier_option_ids' => [$modern, $beech],
        ])->assertCreated()->json('data');

        $this->assertSame($bedroom, $item['line_option']['id']);
        $this->assertEqualsCanonicalizing([$modern, $beech], array_column($item['modifier_options'], 'id'));

        $this->post("/api/v2/business/menu/items/{$item['id']}/images", [
            'images' => [$this->photo('bedroom-1.png'), $this->photo('bedroom-2.png')],
        ], ['Accept' => 'application/json'])->assertSuccessful();

        // What the customer sees.
        $public = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu/' . $shop->id)->assertOk()->json('data.sections');
        $seen = collect($public)->flatMap(fn ($s) => $s['items'])->firstWhere('id', $item['id']);

        $this->assertNotNull($seen);
        $this->assertSame(48500.0, (float) $seen['base_price']);
        $this->assertSame('سرير ٢٠٠ سم ودولاب ٦ ضلف وتسريحة ومرآة', $seen['description']);
        $this->assertCount(2, $seen['images']);
        $this->assertEqualsCanonicalizing([$bedroom, $modern, $beech], $seen['option_ids']);
        $this->assertStringContainsString('مودرن', (string) $seen['offering_label']);
        $this->assertStringContainsString('زان', (string) $seen['offering_label']);
    }
}
