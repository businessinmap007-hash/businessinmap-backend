<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\TripProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «برنامج الرحلة»: a tour company writes a trip as days and items and drops into it the card of a room a hotel gave it. The card
 * carries the photos and what the room has, at the COMPANY's price; the contract behind it never leaves the two parties.
 */
class TripProgramTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private User $stranger;
    private User $guest;
    private BookableItem $unit;
    private BookableAllocation $allocation;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->stranger = $this->user(User::TYPE_BUSINESS);
        $this->guest = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 5, 'is_active' => 1,
        ]);

        $p = BusinessPartnership::create([
            'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency',
            'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id,
        ]);

        $this->allocation = BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => 'guaranteed',
            'quantity_total' => 3, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
            'contract_price' => 800, 'currency' => 'EGP', 'markup_type' => 'fixed', 'markup_value' => 200, 'status' => 'active',
        ]);
    }

    private function user(string $type, bool $hotel = false): User
    {
        return User::create([
            'name' => 'Zz ' . uniqid(), 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => $hotel ? self::ROOT : null, 'category_child_id' => $hotel ? self::CHILD : null,
        ]);
    }

    /** @return array{0:int,1:int} [program, day] */
    private function program(): array
    {
        Sanctum::actingAs($this->agency);
        $pid = (int) $this->postJson('/api/v2/business/trip-programs', ['title' => 'الجلالة ٣ ليالٍ'])->assertCreated()->json('data.program.id');
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days", ['title' => 'الوصول'])->assertCreated();
        $day = (int) $this->getJson("/api/v2/business/trip-programs/{$pid}")->json('data.program.days.0.id');

        return [$pid, $day];
    }

    public function test_the_company_writes_a_programme_with_a_room_card_at_its_own_price(): void
    {
        [$pid, $day] = $this->program();

        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'text', 'body' => 'استقبال الثامنة صباحًا'])->assertCreated();
        $res = $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'room', 'allocation_id' => $this->allocation->id])->assertCreated();

        $items = $res->json('data.program.days.0.items');
        $this->assertSame(['text', 'room'], array_column($items, 'kind'));
        $room = $items[1]['room'];
        $this->assertEquals(1000.0, $room['price'], 'the company\'s price, contract 800 + markup 200');
        $this->assertSame($this->unit->id, $room['bookable_id']);
        $this->assertSame($this->hotel->id, $room['from']['id']);
        $this->assertArrayHasKey('images', $room);
        $this->assertTrue($items[1]['room_available']);
    }

    public function test_only_a_room_the_company_was_given_can_be_added(): void
    {
        [$pid, $day] = $this->program();

        // a hotel's allocation to someone else
        $other = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->stranger->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);
        $theirs = BookableAllocation::create([
            'partnership_id' => $other->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->stranger->id, 'bookable_item_id' => $this->unit->id,
            'platform_service_id' => $this->serviceId, 'allocation_type' => 'guaranteed', 'quantity_total' => 1, 'contract_price' => 700, 'currency' => 'EGP', 'status' => 'active',
        ]);

        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'room', 'allocation_id' => $theirs->id])->assertStatus(422);
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'room'])->assertStatus(422);
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'text', 'body' => ' '])->assertStatus(422);

        $cards = $this->getJson('/api/v2/business/trip-programs/room-cards')->assertOk()->json('data.rooms');
        $this->assertCount(1, $cards);
        $this->assertSame($this->allocation->id, $cards[0]['allocation_id']);
    }

    public function test_a_draft_is_private_and_a_published_one_is_public_without_the_contract(): void
    {
        [$pid, $day] = $this->program();

        $this->postJson("/api/v2/business/trip-programs/{$pid}/publish")->assertStatus(422);
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'room', 'allocation_id' => $this->allocation->id])->assertCreated();

        Sanctum::actingAs($this->guest);
        $this->getJson("/api/v2/businesses/{$this->agency->id}/trip-programs/{$pid}")->assertNotFound();
        $this->assertSame([], $this->getJson("/api/v2/businesses/{$this->agency->id}/trip-programs")->json('data.programs'));

        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/trip-programs/{$pid}/publish")->assertOk()->assertJsonPath('data.program.status', 'published');

        Sanctum::actingAs($this->guest);
        $list = $this->getJson("/api/v2/businesses/{$this->agency->id}/trip-programs")->assertOk()->json('data.programs');
        $this->assertCount(1, $list);

        $res = $this->getJson("/api/v2/businesses/{$this->agency->id}/trip-programs/{$pid}")->assertOk();
        $this->assertEquals(1000.0, $res->json('data.program.days.0.items.0.room.price'));

        $raw = json_encode($res->json('data'));
        foreach (['contract_price', 'markup', 'quantity_total', 'quantity_sold'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "{$secret} stays between the two parties");
        }
    }

    public function test_when_the_hotel_stops_the_allocation_the_card_goes_and_the_words_stay(): void
    {
        [$pid, $day] = $this->program();
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'room', 'allocation_id' => $this->allocation->id, 'body' => 'غرفتان للعائلة'])->assertCreated();
        $this->postJson("/api/v2/business/trip-programs/{$pid}/publish")->assertOk();

        $this->allocation->update(['status' => 'stopped']);

        Sanctum::actingAs($this->guest);
        $item = $this->getJson("/api/v2/businesses/{$this->agency->id}/trip-programs/{$pid}")->json('data.program.days.0.items.0');
        $this->assertNull($item['room']);
        $this->assertFalse($item['room_available']);
        $this->assertSame('غرفتان للعائلة', $item['body']);
    }

    public function test_another_business_cannot_read_or_change_the_programme(): void
    {
        [$pid, $day] = $this->program();

        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/v2/business/trip-programs/{$pid}")->assertNotFound();
        $this->putJson("/api/v2/business/trip-programs/{$pid}", ['title' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v2/business/trip-programs/{$pid}")->assertNotFound();
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'text', 'body' => 'x'])->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v2/business/trip-programs')->json('data.programs'));
    }

    public function test_days_and_items_move_and_renumber(): void
    {
        [$pid, $day] = $this->program();
        $second = (int) $this->postJson("/api/v2/business/trip-programs/{$pid}/days", ['title' => 'يوم حر'])->json('data.program.days.1.id');

        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$second}/move", ['direction' => 'up'])->assertOk();
        $this->assertSame(['يوم حر', 'الوصول'], array_column($this->getJson("/api/v2/business/trip-programs/{$pid}")->json('data.program.days'), 'title'));

        $a = (int) $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'text', 'body' => 'أ'])->json('data.item_id');
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'text', 'body' => 'ب'])->assertCreated();
        $this->postJson("/api/v2/business/trip-programs/{$pid}/items/{$a}/move", ['direction' => 'down'])->assertOk();
        $items = $this->getJson("/api/v2/business/trip-programs/{$pid}")->json('data.program.days.1.items');
        $this->assertSame(['ب', 'أ'], array_column($items, 'body'));
        $this->assertSame([1, 2], array_column($items, 'position'));

        $this->deleteJson("/api/v2/business/trip-programs/{$pid}/items/{$a}")->assertOk();
        $this->assertSame([1], array_column($this->getJson("/api/v2/business/trip-programs/{$pid}")->json('data.program.days.1.items'), 'position'));
    }

    public function test_a_photo_item_keeps_one_photo_and_deleting_the_programme_removes_it(): void
    {
        [$pid, $day] = $this->program();
        $item = (int) $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'image', 'body' => 'شاطئ الفندق'])->json('data.item_id');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $paths = [];

        foreach (['a.png', 'b.png'] as $name) {
            $this->post("/api/v2/business/trip-programs/{$pid}/items/{$item}/image", ['image' => UploadedFile::fake()->createWithContent($name, $png)], ['Accept' => 'application/json'])->assertOk();
            $paths[] = public_path($this->getJson("/api/v2/business/trip-programs/{$pid}")->json('data.program.days.0.items.0.images.0.image'));
        }

        try {
            $images = $this->getJson("/api/v2/business/trip-programs/{$pid}")->json('data.program.days.0.items.0.images');
            $this->assertCount(1, $images, 'a new photo replaces the old one');
            $this->assertFileDoesNotExist($paths[0]);

            $this->deleteJson("/api/v2/business/trip-programs/{$pid}")->assertOk();
            $this->assertDatabaseMissing('trip_program_items', ['id' => $item]);
            $this->assertSame(0, \App\Models\Image::query()->where('imageable_type', \App\Models\TripProgramItem::class)->where('imageable_id', $item)->count());
            $this->assertFileDoesNotExist($paths[1], 'the photo went with the programme');
        } finally {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    public function test_the_page_shows_the_booking_and_programmes_tabs_and_the_hotel_sees_only_a_count(): void
    {
        [$pid, $day] = $this->program();
        $this->postJson("/api/v2/business/trip-programs/{$pid}/days/{$day}/items", ['kind' => 'room', 'allocation_id' => $this->allocation->id])->assertCreated();
        $this->postJson("/api/v2/business/trip-programs/{$pid}/publish")->assertOk();

        Sanctum::actingAs($this->guest);
        $sections = $this->getJson("/api/v2/businesses/{$this->agency->id}")->assertOk()->json('data.sections');
        $this->assertTrue($sections['booking']);
        $this->assertTrue($sections['partner_rooms']);
        $this->assertTrue($sections['programs']);

        Sanctum::actingAs($this->hotel);
        $partnership = $this->allocation->partnership_id;
        $alloc = $this->getJson("/api/v2/business/partnerships/{$partnership}")->assertOk()->json('data.allocations.0');
        $this->assertSame(1, $alloc['programs_count']);
        $this->assertStringNotContainsString('الجلالة', json_encode($alloc), 'the programme itself is the company\'s');
        $this->assertSame(1, TripProgram::query()->where('business_id', $this->agency->id)->count());
    }
}
