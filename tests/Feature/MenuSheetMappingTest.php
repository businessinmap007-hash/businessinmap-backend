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
 * «ربط أعمدة ملف الاستيراد بالترقيم» — المالك، 2026-10-05: a file whose headers are called something else still
 * imports, because each of OUR columns is pointed at the file's column NUMBER. Rolls back.
 */
class MenuSheetMappingTest extends TestCase
{
    use DatabaseTransactions;

    private User $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $bedroomId = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->factory->id, 'option_id' => $bedroomId], []);
        Sanctum::actingAs($this->factory);
    }

    /** A file with the owner's own headers, in his own order. */
    private function grid(): array
    {
        return [
            ['كود', 'المنتج', 'بيع', 'النوع'],
            ['', 'غرفة مربوطة', '30,000', 'غرفة نوم'],
            ['', 'غرفة ثانية', '12500', 'غرفة نوم'],
        ];
    }

    private function itemsNamed(string $name): int
    {
        return MenuItem::query()->where('business_id', $this->factory->id)->where('name_ar', $name)->count();
    }

    public function test_inspect_numbers_the_files_headers_and_suggests_only_what_it_recognises(): void
    {
        $data = $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/inspect', ['grid' => $this->grid()])->assertOk()->json('data');

        $this->assertSame(['كود', 'المنتج', 'بيع', 'النوع'], $data['headers']);
        $this->assertSame(2, $data['total_rows']);
        $this->assertSame(4, $data['mapping']['line'], 'النوع is recognised by name');
        $this->assertNull($data['mapping']['name_ar'], 'المنتج is not one of our names — the owner points it');
        $this->assertNull($data['mapping']['price']);
        $this->assertSame('غرفة مربوطة', $data['sample'][0][1]);
        $this->assertContains('name_ar', array_column($data['columns'], 'key'));
    }

    public function test_a_file_with_other_headers_imports_through_the_mapping(): void
    {
        $mapping = ['name_ar' => 2, 'price' => 3, 'line' => 4];

        $preview = $this->postJson('/api/v2/business/menu/import', ['grid' => $this->grid(), 'mapping' => $mapping, 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame(['create' => 2, 'update' => 0, 'error' => 0], $preview['summary']);
        $this->assertSame(0, $this->itemsNamed('غرفة مربوطة'), 'a preview writes nothing');

        $this->postJson('/api/v2/business/menu/import', ['grid' => $this->grid(), 'mapping' => $mapping, 'dry_run' => false])->assertOk();

        $item = MenuItem::query()->where('business_id', $this->factory->id)->where('name_ar', 'غرفة مربوطة')->firstOrFail();
        $this->assertEquals(30000, $item->base_price);
        $this->assertSame(1, $this->itemsNamed('غرفة ثانية'));
    }

    public function test_a_column_left_unmapped_is_simply_absent(): void
    {
        // «كود» and «بيع» are not mapped: the price is missing, so the rows are reported — nothing is guessed.
        $preview = $this->postJson('/api/v2/business/menu/import', ['grid' => $this->grid(), 'mapping' => ['name_ar' => 2, 'line' => 4], 'dry_run' => true])->assertOk()->json('data');

        $this->assertSame(2, $preview['summary']['error']);
    }

    public function test_a_csv_file_takes_the_mapping_as_json(): void
    {
        $csv = "كود,المنتج,بيع,النوع\n,غرفة من ملف,9000,غرفة نوم\n";
        $file = UploadedFile::fake()->createWithContent('my.csv', $csv);

        $this->post('/api/v2/business/menu/import', [
            'file' => $file, 'mapping' => json_encode(['name_ar' => 2, 'price' => 3, 'line' => 4]), 'dry_run' => 0,
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $this->itemsNamed('غرفة من ملف'));
    }

    public function test_without_a_mapping_the_headers_are_still_recognised_by_name(): void
    {
        $grid = [['الاسم عربي', 'السعر', 'النوع'], ['غرفة بالاسم', '700', 'غرفة نوم']];

        $this->postJson('/api/v2/business/menu/import', ['grid' => $grid, 'dry_run' => false])->assertOk();

        $this->assertSame(1, $this->itemsNamed('غرفة بالاسم'));
    }

    public function test_the_old_rows_form_still_works(): void
    {
        $rows = [['line' => 'غرفة نوم', 'name_ar' => 'غرفة قديمة', 'price' => '100']];

        $this->postJson('/api/v2/business/menu/import', ['rows' => $rows, 'dry_run' => false])->assertOk();

        $this->assertSame(1, $this->itemsNamed('غرفة قديمة'));
    }

    public function test_the_web_panel_inspects_and_imports_a_grid_with_its_mapping(): void
    {
        $this->actingAs($this->factory);

        $this->get('/business/menu/import')->assertOk()->assertSee('mappingCard', false);

        $data = $this->postJson('/business/menu/inspect', ['grid' => $this->grid()])->assertOk()->json('data');
        $this->assertSame(['كود', 'المنتج', 'بيع', 'النوع'], $data['headers']);

        $mapping = ['name_ar' => 2, 'price' => 3, 'line' => 4];
        $this->postJson('/business/menu/import', ['grid' => $this->grid(), 'mapping' => $mapping, 'dry_run' => 0])->assertOk()->assertJsonPath('data.summary.create', 2);
        $this->assertSame(1, $this->itemsNamed('غرفة مربوطة'));
    }
}
