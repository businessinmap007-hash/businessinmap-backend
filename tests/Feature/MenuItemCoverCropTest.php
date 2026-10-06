<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AnswersDelivery;
use Tests\TestCase;

/**
 * «الصور المرفوعة تظهر على الكارت، وعليها علامة الكاميرا/الجاليري للعميل، واختيار أى صورة تظهر وأى جزء منها» — المالك،
 * 2026-10-06. Rolls back (the uploaded files are removed).
 */
class MenuItemCoverCropTest extends TestCase
{
    use AnswersDelivery;
    use DatabaseTransactions;

    private User $shop;
    private User $customer;
    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();

        // an item linked to a catalog master that carries its own open-licensed photo — the case where the card used
        // to ignore the merchant's uploads
        $product = (int) DB::table('catalog_products')->whereNotNull('main_image')->value('id');
        $this->item = MenuItem::create(['business_id' => $this->shop->id, 'catalog_product_id' => $product ?: null, 'name_ar' => 'صنف بصور', 'name_en' => 'Pictured', 'base_price' => 50, 'is_active' => 1]);
        Sanctum::actingAs($this->shop);
    }

    protected function tearDown(): void
    {
        foreach (Image::query()->where('imageable_type', (new MenuItem)->getMorphClass())->where('imageable_id', $this->item->id)->get() as $image) {
            app(ImageUploadService::class)->delete($image->image);
        }

        parent::tearDown();
    }

    private function png(string $name = 'p.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function upload(string $source): array
    {
        return $this->post("/api/v2/business/menu/items/{$this->item->id}/images", ['images' => [$this->png()], 'source' => $source], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.images.0');
    }

    private function customerCard(): array
    {
        Sanctum::actingAs($this->customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->shop->id)->assertOk()->json('data');
        $row = collect($menu['sections'])->flatMap(fn ($s) => $s['items'] ?? [])->firstWhere('id', $this->item->id);
        Sanctum::actingAs($this->shop);

        return $row;
    }

    public function test_an_uploaded_photo_is_what_the_card_shows_even_when_the_item_has_a_catalog_photo(): void
    {
        $up = $this->upload(Image::SOURCE_UPLOAD);

        $card = $this->customerCard();

        $this->assertSame($up['image'], $card['image'], 'the merchant\'s own photo, not the catalog master\'s');
        $this->assertNull($card['image_credit']);
    }

    public function test_the_customer_is_told_whether_each_photo_is_a_live_shot_or_an_upload(): void
    {
        $this->upload(Image::SOURCE_UPLOAD);
        $this->upload(Image::SOURCE_CAMERA);

        $card = $this->customerCard();

        $this->assertSame(['upload', 'camera'], array_column($card['images'], 'source'));
    }

    public function test_the_merchant_chooses_which_photo_the_card_shows(): void
    {
        $first = $this->upload(Image::SOURCE_UPLOAD);
        $second = $this->upload(Image::SOURCE_CAMERA);
        $this->assertSame($first['image'], $this->customerCard()['image'], 'the first one until he chooses');

        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/cover", ['image_id' => $second['id']])->assertOk();

        $card = $this->customerCard();
        $this->assertSame($second['image'], $card['image']);
        $this->assertSame($second['id'], $card['cover_image_id']);
        $this->assertSame([false, true], array_column($card['images'], 'is_cover'));
    }

    public function test_a_photo_of_another_item_cannot_be_the_cover_and_a_deleted_cover_falls_back(): void
    {
        $first = $this->upload(Image::SOURCE_UPLOAD);
        $second = $this->upload(Image::SOURCE_UPLOAD);
        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/cover", ['image_id' => 99999999])->assertNotFound();

        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/cover", ['image_id' => $second['id']])->assertOk();
        $this->deleteJson("/api/v2/business/menu/items/{$this->item->id}/images/{$second['id']}")->assertOk();

        $this->assertSame($first['image'], $this->customerCard()['image'], 'back to the first one left');
    }

    public function test_the_part_of_the_photo_the_card_shows_is_saved_and_validated(): void
    {
        $up = $this->upload(Image::SOURCE_UPLOAD);
        $this->assertEquals(['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0], $this->customerCard()['images'][0]['crop'], 'the whole photo, centred, until he sets it');

        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/images/{$up['id']}/crop", ['x' => 0.3, 'y' => 0.7, 'zoom' => 2.5])
            ->assertOk()->assertJsonPath('data.crop.zoom', 2.5);

        $this->assertEquals(['x' => 0.3, 'y' => 0.7, 'zoom' => 2.5], $this->customerCard()['images'][0]['crop']);

        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/images/{$up['id']}/crop", ['x' => 1.5, 'y' => 0.5, 'zoom' => 1])->assertUnprocessable();
        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/images/{$up['id']}/crop", ['x' => 0.5, 'y' => 0.5, 'zoom' => 9])->assertUnprocessable();
    }

    public function test_another_business_cannot_set_the_cover_or_the_crop(): void
    {
        $up = $this->upload(Image::SOURCE_UPLOAD);
        $other = User::query()->where('type', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();
        Sanctum::actingAs($other);

        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/cover", ['image_id' => $up['id']])->assertNotFound();
        $this->putJson("/api/v2/business/menu/items/{$this->item->id}/images/{$up['id']}/crop", ['x' => 0.1, 'y' => 0.1, 'zoom' => 2])->assertNotFound();
    }
}
