<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «المنيو يحفظ فى ملف csv او اكسل … ثم يتم استيرادها للحساب — للمطاعم والسوبر ماركت» — المالك، 2026-10-05.
 * Export, template, a preview that changes nothing, then the import. Rolls back.
 */
class MenuSheetImportTest extends TestCase
{
    use DatabaseTransactions;

    private User $factory;
    private string $bedroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $bedroomId = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->factory->id, 'option_id' => $bedroomId], []);
        $this->bedroom = 'غرفة نوم';
        Sanctum::actingAs($this->factory);
    }

    private function import(array $rows, bool $dryRun)
    {
        return $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/import', ['rows' => $rows, 'dry_run' => $dryRun])->assertOk()->json('data');
    }

    private function itemsNamed(string $name): int
    {
        return MenuItem::query()->where('business_id', $this->factory->id)->where('name_ar', $name)->count();
    }

    public function test_the_template_speaks_the_merchants_own_vocabulary(): void
    {
        $data = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/sheet?template=1')->assertOk()->json('data');

        $this->assertSame('النوع', collect($data['columns'])->firstWhere('key', 'line')['label']);
        $this->assertContains($this->bedroom, array_column($data['vocabulary']['lines'], 'name'));
        $this->assertContains('كجم', $data['vocabulary']['units']);
        $this->assertCount(2, $data['rows']);
    }

    public function test_a_preview_changes_nothing_and_the_import_writes_through_the_same_door(): void
    {
        $rows = [['line' => $this->bedroom, 'name_ar' => 'غرفة مستوردة', 'price' => '30,000', 'quantity' => '3', 'active' => 'نعم']];

        $preview = $this->import($rows, true);
        $this->assertSame(['create' => 1, 'update' => 0, 'error' => 0], $preview['summary']);
        $this->assertSame(0, $this->itemsNamed('غرفة مستوردة'), 'a preview writes nothing');

        $done = $this->import($rows, false);
        $this->assertSame(1, $done['summary']['create']);
        $item = MenuItem::query()->where('business_id', $this->factory->id)->where('name_ar', 'غرفة مستوردة')->firstOrFail();
        $this->assertEquals(30000, $item->base_price, 'thousands separator read');
        $this->assertSame(3, (int) $item->available_quantity);
        $this->assertNotNull($item->menu_section_id, 'the section grows from the type, as when added by hand');
        $this->assertTrue(DB::table('offering_options')->where('offering_id', $item->id)->where('role', 'line')->exists());
    }

    public function test_the_same_name_again_updates_the_item_instead_of_adding_a_twin(): void
    {
        $this->import([['line' => $this->bedroom, 'name_ar' => 'غرفة تتحدث', 'price' => 20000]], false);
        $report = $this->import([['line' => $this->bedroom, 'name_ar' => 'غرفة تتحدث', 'price' => 22000]], false);

        $this->assertSame(1, $report['summary']['update']);
        $this->assertSame(1, $this->itemsNamed('غرفة تتحدث'));
        $this->assertEquals(22000, MenuItem::query()->where('business_id', $this->factory->id)->where('name_ar', 'غرفة تتحدث')->value('base_price'));
    }

    public function test_a_bad_row_is_reported_by_its_row_number_and_the_good_rows_still_go_in(): void
    {
        $other = MenuItem::query()->where('business_id', '!=', $this->factory->id)->value('id');
        $report = $this->import([
            ['line' => $this->bedroom, 'name_ar' => 'غرفة سليمة', 'price' => 100],
            ['line' => 'صاروخ فضائي', 'name_ar' => 'نوع غريب', 'price' => 100],
            ['line' => $this->bedroom, 'name_ar' => 'بلا سعر', 'price' => ''],
            ['id' => $other, 'name_ar' => 'صنف غيري', 'price' => 5],
        ], false);

        $this->assertSame(1, $report['summary']['create']);
        $this->assertSame(3, $report['summary']['error']);
        $errors = collect($report['rows'])->where('action', 'error')->keyBy('row');
        $this->assertStringContainsString('صاروخ فضائي', $errors[3]['errors'][0], 'row 3 of the sheet (header is row 1)');
        $this->assertArrayHasKey(4, $errors->all());
        $this->assertArrayHasKey(5, $errors->all());
        $this->assertSame(1, $this->itemsNamed('غرفة سليمة'));
    }

    public function test_a_csv_from_excel_with_arabic_headers_and_semicolons_is_read(): void
    {
        $csv = "\xEF\xBB\xBF" . "النوع;الاسم عربي;السعر;الوحدة;نشط\n" . $this->bedroom . ";غرفة من ملف;15000;قطعة;لا\n";
        $file = UploadedFile::fake()->createWithContent('menu.csv', $csv);

        $report = $this->withHeaders(['Accept-Language' => 'ar'])->post('/api/v2/business/menu/import', ['file' => $file, 'dry_run' => 0], ['Accept' => 'application/json'])->assertOk()->json('data');

        $this->assertSame(1, $report['summary']['create']);
        $item = MenuItem::query()->where('business_id', $this->factory->id)->where('name_ar', 'غرفة من ملف')->firstOrFail();
        $this->assertFalse((bool) $item->is_active, '«نشط: لا»');
        $this->assertSame('pcs', $item->sale_unit);
    }

    public function test_the_export_is_the_same_sheet_and_comes_back_unchanged(): void
    {
        $this->import([['line' => $this->bedroom, 'name_ar' => 'غرفة للتصدير', 'price' => 12345, 'unit' => 'كجم']], false);

        $rows = $this->getJson('/api/v2/business/menu/sheet')->assertOk()->json('data.rows');
        $row = collect($rows)->firstWhere('name_ar', 'غرفة للتصدير');
        $this->assertSame($this->bedroom, $row['line']);
        $this->assertSame('كجم', $row['unit']);

        $again = $this->import([$row], false);
        $this->assertSame(1, $again['summary']['update'], 'the exported id brings it back to the same item');
        $this->assertSame(1, $this->itemsNamed('غرفة للتصدير'));

        $csv = $this->get('/api/v2/business/menu/sheet.csv')->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->getContent());
        $this->assertStringContainsString('غرفة للتصدير', $csv->getContent());
    }

    public function test_a_kitchen_without_types_writes_its_own_sections(): void
    {
        $kitchen = User::query()->where('type', 'business')->where('id', '!=', $this->factory->id)->orderByDesc('id')->firstOrFail();
        DB::table('users')->where('id', $kitchen->id)->update(['category_id' => 16, 'category_child_id' => 245]);
        Sanctum::actingAs($kitchen->fresh());

        $report = $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/import', ['rows' => [
            ['القسم' => 'مشويات', 'الاسم عربي' => 'كباب', 'السعر' => 180],
            ['القسم' => 'مشويات', 'الاسم عربي' => 'كفتة', 'السعر' => 150],
        ], 'dry_run' => false])->assertOk()->json('data');

        $this->assertSame(2, $report['summary']['create']);
        $section = DB::table('menu_sections')->where('business_id', $kitchen->id)->where('name_ar', 'مشويات')->get();
        $this->assertCount(1, $section, 'one section for both rows');
        $this->assertSame(2, MenuItem::query()->where('business_id', $kitchen->id)->where('menu_section_id', $section[0]->id)->count());
    }
}
