<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemExtraGroup;
use App\Models\MenuItemVariant;
use App\Models\User;
use App\Services\Menu\MenuSheetDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «استيراد وتصدير المنيو» — the second phase: sizes, extras (grouped or not), photos by link and the catalog
 * barcode, each in one cell. Rolls back (photos written to disk are removed at the end).
 */
class MenuSheetDetailsTest extends TestCase
{
    use DatabaseTransactions;

    private User $shop;

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A kitchen: no types, it writes its own sections.
        $this->shop = User::query()->where('type', 'business')->orderByDesc('id')->firstOrFail();
        DB::table('users')->where('id', $this->shop->id)->update(['category_id' => 16, 'category_child_id' => 245]);
        $this->shop = $this->shop->fresh();
        Sanctum::actingAs($this->shop);
    }

    protected function tearDown(): void
    {
        foreach (MenuItem::query()->where('business_id', $this->shop->id)->get() as $item) {
            foreach ($item->images()->pluck('image') as $path) {
                @unlink(public_path((string) $path));
            }
        }
        MenuSheetDetails::$resolveHost = null;
        parent::tearDown();
    }

    private function import(array $rows, bool $dryRun = false): array
    {
        return $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/import', ['rows' => $rows, 'dry_run' => $dryRun])->assertOk()->json('data');
    }

    private function item(string $name): MenuItem
    {
        return MenuItem::query()->where('business_id', $this->shop->id)->where('name_ar', $name)->firstOrFail();
    }

    public function test_sizes_and_grouped_extras_go_in_and_come_back_out_the_same(): void
    {
        $this->import([[
            'section' => 'برجر', 'name_ar' => 'برجر كلاسيك', 'price' => 120,
            'variants' => 'صغير=100; وسط=120; كبير=150',
            'extras' => 'جبنة=10; بيض=8 | الصوصات: كاتشب=5, مايو=5 | الخبز (واحد): أبيض=0, أسمر=3',
        ]]);

        $item = $this->item('برجر كلاسيك');
        $sizes = MenuItemVariant::query()->where('menu_item_id', $item->id)->orderBy('id')->get();
        $this->assertSame(['صغير', 'وسط', 'كبير'], $sizes->pluck('name_ar')->all());
        $this->assertEquals([100, 120, 150], $sizes->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertTrue((bool) $sizes[0]->is_default, 'the first size is the default');

        $groups = MenuItemExtraGroup::query()->where('menu_item_id', $item->id)->get()->keyBy('name_ar');
        $this->assertSame('multiple', $groups['الصوصات']->selection_type);
        $this->assertSame('single', $groups['الخبز']->selection_type, '«(واحد)» = pick one');
        $this->assertSame(2, MenuItemExtra::query()->where('menu_item_id', $item->id)->whereNull('extra_group_id')->count(), 'cheese and egg are loose extras');

        $row = collect($this->getJson('/api/v2/business/menu/sheet')->assertOk()->json('data.rows'))->firstWhere('name_ar', 'برجر كلاسيك');
        $this->assertSame('صغير=100; وسط=120; كبير=150', $row['variants']);
        $this->assertSame('جبنة=10; بيض=8 | الصوصات: كاتشب=5, مايو=5 | الخبز (واحد): أبيض=0, أسمر=3', $row['extras']);

        $again = $this->import([$row]);
        $this->assertSame(1, $again['summary']['update']);
        $this->assertSame(3, MenuItemVariant::query()->where('menu_item_id', $item->id)->count(), 'the round trip does not double them');
        $this->assertSame(6, MenuItemExtra::query()->where('menu_item_id', $item->id)->count());
    }

    public function test_an_empty_cell_removes_and_a_missing_column_leaves_alone_and_the_shops_services_stay(): void
    {
        $this->import([['section' => 'برجر', 'name_ar' => 'برجر ثاني', 'price' => 90, 'variants' => 'صغير=80; كبير=100', 'extras' => 'جبنة=10']]);
        $item = $this->item('برجر ثاني');
        // A shop service synced onto the item («طريقة الطهي») — not the sheet's to touch.
        MenuItemExtra::query()->create(['menu_item_id' => $item->id, 'name_ar' => 'مشوي', 'price' => 20, 'max_qty' => 1, 'is_active' => true])
            ->forceFill(['source_option_id' => 11617])->save();

        $this->import([['name_ar' => 'برجر ثاني', 'price' => 95]]);
        $this->assertSame(2, MenuItemVariant::query()->where('menu_item_id', $item->id)->count(), 'no «المقاسات» column: untouched');

        $this->import([['name_ar' => 'برجر ثاني', 'price' => 95, 'variants' => '', 'extras' => '']]);
        $this->assertSame(0, MenuItemVariant::query()->where('menu_item_id', $item->id)->count());
        $this->assertSame(['مشوي'], MenuItemExtra::query()->where('menu_item_id', $item->id)->pluck('name_ar')->all(), 'only the shop service is left');
    }

    public function test_a_badly_written_cell_is_an_error_of_its_row(): void
    {
        $report = $this->import([
            ['section' => 'برجر', 'name_ar' => 'سليم', 'price' => 50, 'extras' => 'جبنة=10'],
            ['section' => 'برجر', 'name_ar' => 'سعر غلط', 'price' => 50, 'extras' => 'جبنة=عشرة'],
            ['section' => 'برجر', 'name_ar' => 'مقاس بلا سعر', 'price' => 50, 'variants' => 'صغير; كبير=80'],
        ]);

        $this->assertSame(1, $report['summary']['create']);
        $errors = collect($report['rows'])->where('action', 'error')->keyBy('row');
        $this->assertStringContainsString('جبنة', $errors[3]['errors'][0]);
        $this->assertStringContainsString('صغير', $errors[4]['errors'][0]);
    }

    public function test_a_catalog_barcode_links_and_names_the_item_and_an_unknown_one_is_a_warning(): void
    {
        $product = DB::table('catalog_products')->whereNotNull('default_barcode')->where('approval_status', 'approved')->whereNull('deleted_at')->first(['id', 'name_ar', 'default_barcode']);
        if (! $product) {
            $this->markTestSkipped('No catalog product with a barcode.');
        }

        $report = $this->import([
            ['section' => 'بقالة', 'barcode' => $product->default_barcode, 'name_ar' => '', 'price' => 25],
            ['section' => 'بقالة', 'barcode' => '0000000000000', 'name_ar' => 'منتج محلي', 'price' => 10],
        ]);

        $this->assertSame(2, $report['summary']['create']);
        $linked = $this->item($product->name_ar);
        $this->assertSame((int) $product->id, (int) $linked->catalog_product_id, 'named and linked by the barcode');
        $this->assertNull($this->item('منتج محلي')->catalog_product_id);
        $this->assertStringContainsString('0000000000000', collect($report['rows'])->firstWhere('row', 3)['warnings'][0]);

        $row = collect($this->getJson('/api/v2/business/menu/sheet')->json('data.rows'))->firstWhere('id', $linked->id);
        $this->assertSame($product->default_barcode, $row['barcode']);
    }

    public function test_photos_by_link_are_fetched_on_the_import_only_and_never_from_the_private_network(): void
    {
        MenuSheetDetails::$resolveHost = fn (string $host) => $host === 'cdn.example.com' ? ['93.184.216.34'] : ['10.0.0.5'];
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Http::fake([
            'cdn.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('nope', 200, ['Content-Type' => 'text/html']),
        ]);
        $cell = "https://cdn.example.com/burger.png\nhttps://intranet.local/secret.png\nليس-رابط";

        $preview = $this->import([['section' => 'برجر', 'name_ar' => 'برجر بصورة', 'price' => 70, 'images' => $cell]], dryRun: true);
        Http::assertNothingSent();
        $this->assertStringContainsString('ليس-رابط', $preview['rows'][0]['warnings'][0]);

        $done = $this->import([['section' => 'برجر', 'name_ar' => 'برجر بصورة', 'price' => 70, 'images' => $cell]]);
        $item = $this->item('برجر بصورة');
        $this->assertSame(1, $item->images()->count(), 'the public image only');
        $this->assertTrue(collect($done['rows'][0]['warnings'])->contains(fn ($w) => str_contains($w, 'intranet.local')), 'the private host is refused');

        // The exported sheet comes back without adding the same photo twice.
        $row = collect($this->getJson('/api/v2/business/menu/sheet')->json('data.rows'))->firstWhere('id', $item->id);
        $this->import([$row]);
        $this->assertSame(1, $item->images()->count());
    }

    public function test_the_template_explains_how_to_write_the_cells(): void
    {
        $help = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/sheet?template=1')->json('data.vocabulary.help');

        $this->assertNotEmpty($help);
        $this->assertTrue(collect($help)->contains(fn ($h) => str_contains($h, '(واحد)')));
    }
}
