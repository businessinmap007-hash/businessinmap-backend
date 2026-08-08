<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «نحتاج اضافة صور ل menu_items ... وتكون الصور قابلة للحذف نهائيا عند حذف
 * المنتج» — owner, 2026-08-08.
 *
 * `menu_items.image` was a single column that NO screen ever wrote — not the
 * business panel, not the API — so every listing on the platform is text. And
 * one photo would not have been enough anyway: a restaurant dish, a flat, a car
 * are sold by a gallery.
 *
 * The photos ride on the polymorphic `images` table, the same one posts and
 * albums use. What is new is that they are OWNED: deleting the item deletes the
 * rows AND unlinks the files, because an orphaned upload can never be found
 * again to remove.
 */
class MenuItemImagesTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    /** @var array<int,string> absolute paths written during a test */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::create([
            'name' => 'مطعم الاختبار',
            'email' => 'menuimg' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 17,
            'category_child_id' => 245,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    protected function tearDown(): void
    {
        // The DB rolls back; the filesystem does not. Anything a test wrote and
        // did not expect to be deleted is cleaned by hand.
        foreach ($this->written as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    /** The owner uploads a gallery, and gets the paths back to render. */
    public function test_a_business_uploads_several_photos_for_one_item(): void
    {
        $item = $this->item();

        $body = $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/images", [
                'images' => [$this->file('one.png'), $this->file('two.png')],
            ])
            ->assertStatus(201)
            ->json('data.images');

        $this->assertCount(2, $body);

        foreach ($body as $image) {
            $this->assertStringStartsWith(ImageUploadService::PUBLIC_DIR . '/', $image['image']);
            $this->assertFileExists($this->track($image['image']));
        }

        $this->assertSame(2, $item->images()->count());
    }

    /** Uploading again ADDS. Wiping a gallery on every save was v1's mistake. */
    public function test_a_second_upload_appends_rather_than_replaces(): void
    {
        $item = $this->item();

        $this->upload($item, 'first.png');
        $this->upload($item, 'second.png');

        $this->assertSame(2, $item->images()->count());
    }

    /** Deleting one photo takes its FILE, not just its row. */
    public function test_deleting_one_image_removes_the_file(): void
    {
        $item = $this->item();
        $image = $this->upload($item, 'gone.png');
        $path = $this->track($image['image']);

        $this->assertFileExists($path);

        $this->actingAs($this->business, 'sanctum')
            ->deleteJson("/api/v2/business/menu/items/{$item->id}/images/{$image['id']}")
            ->assertOk();

        $this->assertFileDoesNotExist($path);
        $this->assertSame(0, $item->images()->count());
    }

    /**
     * The owner's requirement, and the reason this lives on the model rather
     * than in one controller: «قابلة للحذف نهائيا عند حذف المنتج».
     */
    public function test_deleting_the_item_takes_the_whole_gallery_rows_and_files(): void
    {
        $item = $this->item();
        $paths = [
            $this->track($this->upload($item, 'a.png')['image']),
            $this->track($this->upload($item, 'b.png')['image']),
        ];

        $this->actingAs($this->business, 'sanctum')
            ->deleteJson("/api/v2/business/menu/items/{$item->id}")
            ->assertOk();

        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path, 'the upload outlived the item it belonged to');
        }

        $this->assertSame(0, Image::query()
            ->where('imageable_type', MenuItem::class)
            ->where('imageable_id', $item->id)
            ->count());
    }

    /** Deleting from the panel or a seeder must behave the same way. */
    public function test_a_plain_model_delete_cleans_up_too(): void
    {
        $item = $this->item();
        $path = $this->track($this->upload($item, 'panel.png')['image']);

        $item->delete();

        $this->assertFileDoesNotExist($path);
    }

    /** Another business's item is not yours to photograph. */
    public function test_a_stranger_cannot_add_or_delete_photos(): void
    {
        $item = $this->item();
        $image = $this->upload($item, 'mine.png');
        $this->track($image['image']);

        $other = User::create([
            'name' => 'مطعم آخر',
            'email' => 'other' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 17,
            'category_child_id' => 245,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/images", ['images' => [$this->file('x.png')]])
            ->assertStatus(404);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v2/business/menu/items/{$item->id}/images/{$image['id']}")
            ->assertStatus(404);
    }

    /** A gallery has a ceiling, counted against what is already there. */
    public function test_the_gallery_is_capped(): void
    {
        $item = $this->item();

        for ($i = 0; $i < 10; $i++) {
            $this->track($this->upload($item, "fill{$i}.png")['image']);
        }

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/images", ['images' => [$this->file('eleven.png')]])
            ->assertStatus(422);

        $this->assertSame(10, $item->images()->count());
    }

    /** A PDF renamed .jpg is not a photo. */
    public function test_a_non_image_is_refused(): void
    {
        $item = $this->item();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/images", [
                'images' => [UploadedFile::fake()->create('menu.pdf', 12, 'application/pdf')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('images.0');
    }

    /** The customer's menu shows the gallery, not only the legacy column. */
    public function test_the_gallery_reaches_the_customer_menu(): void
    {
        $item = $this->item();
        $this->track($this->upload($item, 'dish.png')['image']);

        $body = $this->getJson("/api/v2/discovery/menu/{$this->business->id}?lang=ar")
            ->assertOk()
            ->json('data');

        $images = collect(data_get($body, 'sections.*.items.*.images'))->flatten(1);

        $this->assertCount(1, $images, 'the photo never reached the customer');
    }

    private function item(): MenuItem
    {
        return MenuItem::create([
            'business_id' => $this->business->id,
            'name_ar' => 'صنف مصوَّر',
            'base_price' => 120,
            'is_active' => 1,
        ]);
    }

    /** @return array{id:int,image:string} */
    private function upload(MenuItem $item, string $name): array
    {
        return $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/images", ['images' => [$this->file($name)]])
            ->assertStatus(201)
            ->json('data.images.0');
    }

    /**
     * A real 1×1 PNG, byte for byte.
     *
     * Not `UploadedFile::fake()->image()`: that draws with GD, which this PHP
     * build does not have, and the upload rules check the mime the file
     * actually is rather than what it is called — so the bytes have to be a
     * genuine image.
     */
    private function file(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    /** Remember an uploaded path so tearDown can clean it, and return it. */
    private function track(string $relative): string
    {
        $full = public_path($relative);
        $this->written[] = $full;

        return $full;
    }
}
