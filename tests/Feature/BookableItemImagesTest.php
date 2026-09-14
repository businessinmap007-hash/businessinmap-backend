<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Image;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Room photos for a booking unit — same `HasOwnedImages` gallery the menu
 * item and training plan galleries use, wired to a business-only door
 * mirroring the menu item gallery's own endpoints.
 */
class BookableItemImagesTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    /** @var array<int,string> absolute paths written during a test */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::create([
            'name' => 'فندق الاختبار',
            'email' => 'roomimg' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 24,
            'category_child_id' => 536,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_a_business_uploads_several_photos_for_one_room(): void
    {
        $item = $this->item();

        $body = $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$item->id}/images", [
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

    public function test_the_gallery_reaches_the_owner_show_endpoint(): void
    {
        $item = $this->item();
        $this->upload($item, 'room.png');

        $body = $this->actingAs($this->business, 'sanctum')
            ->getJson("/api/v2/business/bookable-items/{$item->id}")
            ->assertOk()
            ->json('data.images');

        $this->assertCount(1, $body);
    }

    public function test_deleting_one_image_removes_the_file(): void
    {
        $item = $this->item();
        $image = $this->upload($item, 'gone.png');
        $path = $this->track($image['image']);

        $this->assertFileExists($path);

        $this->actingAs($this->business, 'sanctum')
            ->deleteJson("/api/v2/business/bookable-items/{$item->id}/images/{$image['id']}")
            ->assertOk();

        $this->assertFileDoesNotExist($path);
        $this->assertSame(0, $item->images()->count());
    }

    public function test_deleting_the_unit_takes_the_whole_gallery_rows_and_files(): void
    {
        $item = $this->item();
        $paths = [
            $this->track($this->upload($item, 'a.png')['image']),
            $this->track($this->upload($item, 'b.png')['image']),
        ];

        $this->actingAs($this->business, 'sanctum')
            ->deleteJson("/api/v2/business/bookable-items/{$item->id}")
            ->assertOk();

        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path, 'the upload outlived the unit it belonged to');
        }

        $this->assertSame(0, Image::query()
            ->where('imageable_type', BookableItem::class)
            ->where('imageable_id', $item->id)
            ->count());
    }

    public function test_a_stranger_cannot_add_or_delete_photos(): void
    {
        $item = $this->item();
        $image = $this->upload($item, 'mine.png');
        $this->track($image['image']);

        $other = User::create([
            'name' => 'فندق آخر',
            'email' => 'other' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 24,
            'category_child_id' => 536,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$item->id}/images", ['images' => [$this->file('x.png')]])
            ->assertStatus(404);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v2/business/bookable-items/{$item->id}/images/{$image['id']}")
            ->assertStatus(404);
    }

    public function test_the_gallery_is_capped(): void
    {
        $item = $this->item();

        for ($i = 0; $i < 10; $i++) {
            $this->track($this->upload($item, "fill{$i}.png")['image']);
        }

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$item->id}/images", ['images' => [$this->file('eleven.png')]])
            ->assertStatus(422);

        $this->assertSame(10, $item->images()->count());
    }

    private function item(): BookableItem
    {
        $serviceId = (int) PlatformService::where('key', 'booking')->value('id');

        return BookableItem::create([
            'business_id' => $this->business->id,
            'service_id' => $serviceId,
            'item_type' => 'حجز إقامة',
            'code' => 'ROOM-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }

    /** @return array{id:int,image:string} */
    private function upload(BookableItem $item, string $name): array
    {
        return $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$item->id}/images", ['images' => [$this->file($name)]])
            ->assertStatus(201)
            ->json('data.images.0');
    }

    private function file(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function track(string $relative): string
    {
        $full = public_path($relative);
        $this->written[] = $full;

        return $full;
    }
}
