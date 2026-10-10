<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessServicePrice;
use App\Models\InvestigationOrder;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الطبيب يطلب تحاليل وأشعة … والمريض يشاركها مع معمل مسجّل» — المالك، 2026-10-08.
 *
 * Rolls back (the order is deleted at the end of a test that attaches files, so the files go too).
 */
class InvestigationOrdersTest extends TestCase
{
    use DatabaseTransactions;

    private const HEALTH = 20;
    private const CLINIC = 514;
    private const LAB = 163;
    private const RADIOLOGY = 252;

    /** A real 1×1 PNG — this PHP build has no GD, so fake()->image() cannot run. */
    private function file(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function user(string $type, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $child ? self::HEALTH : null, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    private function option(string $group, string $name): int
    {
        $id = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->where('o.name_ar', $name)->value('o.id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    private function price(User $center, int $optionId, float $price): void
    {
        BusinessServicePrice::create([
            'business_id' => $center->id, 'service_id' => (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id'),
            'child_id' => (int) $center->category_child_id, 'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'line_option_id' => $optionId, 'price' => $price, 'currency' => 'EGP', 'is_active' => 1,
        ]);
    }

    /** @return array{0:User,1:User,2:array<string,int>} doctor, patient, option ids */
    private function setUpOrder(): array
    {
        $options = [
            'cbc' => $this->option('التحاليل الطبية', 'صورة دم كاملة CBC'),
            'sugar' => $this->option('التحاليل الطبية', 'سكر صائم وفاطر'),
            'liver' => $this->option('التحاليل الطبية', 'وظائف كبد'),
            'sono' => $this->option('أنواع الأشعة', 'موجات صوتية / سونار'),
        ];

        return [$this->user(User::TYPE_BUSINESS, self::CLINIC, 'Doctor'), $this->user(User::TYPE_CLIENT, null, 'Patient'), $options];
    }

    private function issue(User $doctor, User $patient, array $ids, array $extra = [])
    {
        Sanctum::actingAs($doctor);

        return $this->postJson('/api/v2/investigation-orders', ['patient_id' => $patient->id, 'option_ids' => $ids] + $extra);
    }

    public function test_the_catalog_is_the_platforms_own_lists(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $data = $this->getJson('/api/v2/investigations/catalog')->assertOk()->json('data');

        $this->assertNotEmpty($data['lab']);
        $this->assertNotEmpty($data['radiology']);
        $this->assertNotEmpty($data['lab'][0]['name']);
    }

    public function test_a_doctor_orders_tests_picked_from_the_lists_and_the_patient_is_told(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();

        $res = $this->issue($doctor, $patient, [$o['cbc'], $o['sugar'], $o['sono']], ['notes' => 'صيام 10 ساعات'])->assertCreated();

        $this->assertSame('issued', $res->json('data.order.status'));
        $this->assertCount(3, $res->json('data.order.items'));
        $this->assertSame(['lab', 'lab', 'radiology'], array_column($res->json('data.order.items'), 'kind'));
        $this->assertTrue(
            AppNotification::query()->where('user_id', $patient->id)->where('notifiable_type', InvestigationOrder::class)->where('notifiable_id', $res->json('data.order.id'))->exists()
        );

        // a word that is not on the lists is refused — never typed, never any option
        $this->issue($doctor, $patient, [999999999])->assertStatus(422);
        $this->issue($doctor, $patient, [$this->option('الغرف', 'غرفة فردية')])->assertStatus(422);
    }

    public function test_only_a_doctor_issues_and_a_lab_only_receives(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();

        $this->issue($this->user(User::TYPE_CLIENT), $patient, [$o['cbc']])->assertStatus(403);
        $this->issue($this->user(User::TYPE_BUSINESS, self::LAB, 'Lab'), $patient, [$o['cbc']])->assertStatus(403);
        $this->issue($doctor, $doctor, [$o['cbc']])->assertStatus(422);
    }

    public function test_the_centre_never_learns_which_doctor_ordered_the_tests(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $this->price($lab, $o['cbc'], 100);

        $id = $this->issue($doctor, $patient, [$o['cbc']], ['notes' => 'صيام'])->json('data.order.id');
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $lab->id])->assertOk();

        // «الطبيب غير معلوم لهم — عمولات»: the centre's list, its detail and its answers carry no doctor
        Sanctum::actingAs($lab);
        $row = $this->getJson('/api/v2/business/investigation-orders')->assertOk()->json('data.data.0');
        $this->assertNull($row['doctor']);
        $this->assertStringNotContainsString((string) $doctor->name, json_encode($row, JSON_UNESCAPED_UNICODE));
        $this->assertNull($this->getJson("/api/v2/investigation-orders/{$id}")->assertOk()->json('data.order.doctor'));
        $this->assertNull($this->postJson("/api/v2/business/investigation-orders/{$id}/accept")->assertOk()->json('data.order.doctor'));

        // the patient and the doctor still see each other
        Sanctum::actingAs($patient);
        $this->assertSame($doctor->id, $this->getJson("/api/v2/investigation-orders/{$id}")->json('data.order.doctor.id'));
        Sanctum::actingAs($doctor);
        $this->assertSame($patient->id, $this->getJson("/api/v2/investigation-orders/{$id}")->json('data.order.patient.id'));

        // nor does the centre's notification name him
        $body = AppNotification::query()->where('user_id', $lab->id)->where('notifiable_id', $id)->get()->map(fn ($n) => $n->title . ' ' . $n->body)->implode(' ');
        $this->assertStringNotContainsString((string) $doctor->name, $body);
    }

    public function test_a_result_is_written_as_text_beside_its_test_and_read_in_the_app(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $this->price($lab, $o['cbc'], 100);
        $this->price($lab, $o['sugar'], 50);

        $id = $this->issue($doctor, $patient, [$o['cbc'], $o['sugar']])->json('data.order.id');
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $lab->id])->assertOk();
        Sanctum::actingAs($lab);
        $this->postJson("/api/v2/business/investigation-orders/{$id}/accept")->assertOk();

        $items = $this->getJson("/api/v2/investigation-orders/{$id}")->json('data.order.items');
        [$cbc, $sugar] = [$items[0]['id'], $items[1]['id']];

        // nothing at all is not a result
        $this->postJson("/api/v2/business/investigation-orders/{$id}/results", [], ['Accept' => 'application/json'])->assertStatus(422);
        // a result for a test that is not in the order is refused
        $this->postJson("/api/v2/business/investigation-orders/{$id}/results", ['texts' => [999999999 => 'x']])->assertStatus(422);

        $res = $this->postJson("/api/v2/business/investigation-orders/{$id}/results", [
            'texts' => [$cbc => 'Hb 13.2 g/dL (12-16) — WBC 6.1', $sugar => '92 mg/dL'], 'note' => 'طبيعي',
        ])->assertOk();

        $this->assertSame('ready', $res->json('data.order.status'));
        $this->assertSame(['Hb 13.2 g/dL (12-16) — WBC 6.1', '92 mg/dL'], array_column($res->json('data.order.items'), 'result'));
        $this->assertCount(0, $res->json('data.order.result_files'), 'text only: no file stored');

        foreach ([$patient, $doctor] as $party) {
            Sanctum::actingAs($party);
            $this->assertSame('92 mg/dL', $this->getJson("/api/v2/investigation-orders/{$id}")->json('data.order.items.1.result'));
        }
    }

    public function test_a_lab_is_not_offered_the_clinic_or_the_prescriptions(): void
    {
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $keys = array_keys(\App\Support\BusinessCapability::forBusiness($lab));

        $this->assertContains('investigations', $keys);
        $this->assertNotContains('clinic', $keys);
        $this->assertNotContains('prescriptions', $keys);
    }

    public function test_the_patient_sees_what_each_centre_charges_for_the_whole_order(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();
        $cheap = $this->user(User::TYPE_BUSINESS, self::LAB, 'Cheap');
        $full = $this->user(User::TYPE_BUSINESS, self::LAB, 'Full');
        $partial = $this->user(User::TYPE_BUSINESS, self::LAB, 'Partial');
        $none = $this->user(User::TYPE_BUSINESS, self::LAB, 'None');

        foreach ([$cheap, $full] as $lab) {
            $this->price($lab, $o['cbc'], $lab === $cheap ? 100 : 120);
            $this->price($lab, $o['sugar'], $lab === $cheap ? 50 : 60);
            $this->price($lab, $o['liver'], $lab === $cheap ? 150 : 160);
        }
        $this->price($partial, $o['cbc'], 90);

        $id = $this->issue($doctor, $patient, [$o['cbc'], $o['sugar'], $o['liver']])->json('data.order.id');

        Sanctum::actingAs($patient);
        $centers = collect($this->getJson("/api/v2/investigation-orders/{$id}/centers")->assertOk()->json('data.centers'));

        $this->assertEquals([$cheap->id, $full->id, $partial->id], $centers->pluck('id')->take(3)->all(), 'covers all first, then the cheapest');
        $this->assertEquals(300, $centers->firstWhere('id', $cheap->id)['total']);
        $this->assertEquals(340, $centers->firstWhere('id', $full->id)['total']);
        $this->assertSame(1, $centers->firstWhere('id', $partial->id)['covers']);
        $this->assertCount(2, $centers->firstWhere('id', $partial->id)['missing']);
        $this->assertNull($centers->firstWhere('id', $none->id), 'a centre that does none of it is not offered');

        // another patient cannot ask for the centres of this order
        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $this->getJson("/api/v2/investigation-orders/{$id}/centers")->assertNotFound();
    }

    public function test_sending_prices_the_order_at_the_centre_and_the_centre_works_it_to_the_results(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $this->price($lab, $o['cbc'], 100);
        $this->price($lab, $o['sugar'], 50);

        $id = $this->issue($doctor, $patient, [$o['cbc'], $o['sugar']])->json('data.order.id');

        Sanctum::actingAs($patient);
        $sent = $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $lab->id])->assertOk();
        $this->assertSame('sent', $sent->json('data.order.status'));
        $this->assertEquals(150, $sent->json('data.order.total'));
        $this->assertEquals([100, 50], array_column($sent->json('data.order.items'), 'price'));
        $this->assertTrue(AppNotification::query()->where('user_id', $lab->id)->where('notifiable_id', $id)->where('notifiable_type', InvestigationOrder::class)->exists());

        // it cannot be sent twice
        $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $lab->id])->assertStatus(422);

        // the centre sees it, nobody else's centre does
        Sanctum::actingAs($lab);
        $this->assertSame([$id], array_column($this->getJson('/api/v2/business/investigation-orders')->assertOk()->json('data.data'), 'id'));
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, self::LAB, 'Other'));
        $this->postJson("/api/v2/business/investigation-orders/{$id}/accept")->assertNotFound();

        // accepts with a time
        Sanctum::actingAs($lab);
        $at = now()->addDay()->startOfHour();
        $this->postJson("/api/v2/business/investigation-orders/{$id}/accept", ['appointment_at' => $at->toIso8601String(), 'note' => 'صيام 8 ساعات'])
            ->assertOk()->assertJsonPath('data.order.status', 'accepted');
        $this->assertTrue(AppNotification::query()->where('user_id', $patient->id)->where('notifiable_id', $id)->count() >= 2);

        // results are photos; the patient AND the doctor are told, both may read them
        $files = [$this->file('r1.png'), $this->file('r2.png')];
        $res = $this->post("/api/v2/business/investigation-orders/{$id}/results", ['images' => $files, 'note' => 'كل القيم طبيعية'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('ready', $res->json('data.order.status'));
        $this->assertCount(2, $res->json('data.order.result_files'));
        $this->assertTrue(AppNotification::query()->where('user_id', $doctor->id)->where('notifiable_id', $id)->where('notifiable_type', InvestigationOrder::class)->exists());

        $url = $res->json('data.order.result_files.0.image');
        $this->assertStringContainsString('investigation-files', $url);

        foreach ([$patient, $doctor, $lab] as $party) {
            Sanctum::actingAs($party);
            $this->getJson("/api/v2/investigation-orders/{$id}")->assertOk()->assertJsonCount(2, 'data.order.result_files');
        }

        // anybody else is a stranger: 404; and the file link is the credential — a tampered one is refused
        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $this->getJson("/api/v2/investigation-orders/{$id}")->assertNotFound();
        $this->get($url)->assertOk();
        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=deadbeef', $url))->assertStatus(403);

        InvestigationOrder::query()->find($id)->delete(); // takes its files with it
    }

    public function test_a_centre_page_lists_what_the_centre_does_and_charges(): void
    {
        [, , $o] = $this->setUpOrder();
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $this->price($lab, $o['cbc'], 100);
        $this->price($lab, $o['liver'], 160);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $tests = collect($this->getJson("/api/v2/investigation-centers/{$lab->id}/tests")->assertOk()->json('data.tests'));

        $this->assertEqualsCanonicalizing([$o['cbc'], $o['liver']], $tests->pluck('option_id')->all());
        $this->assertEquals(100, $tests->firstWhere('option_id', $o['cbc'])['price']);
        $this->assertSame('lab', $tests->first()['kind']);

        // a business that is not a centre has no such page
        $this->getJson('/api/v2/investigation-centers/' . $this->user(User::TYPE_BUSINESS, self::CLINIC)->id . '/tests')->assertNotFound();
    }

    public function test_a_centre_prices_its_own_tests_in_one_list(): void
    {
        [, , $o] = $this->setUpOrder();
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');

        Sanctum::actingAs($lab);
        $list = collect($this->getJson('/api/v2/business/investigation-prices')->assertOk()->json('data.tests'));
        $this->assertGreaterThan(20, $list->count());
        $this->assertNull($list->firstWhere('option_id', $o['cbc'])['price'], 'it does none of it until it prices it');

        $saved = collect($this->putJson('/api/v2/business/investigation-prices', ['prices' => [$o['cbc'] => 120, $o['liver'] => 180, 999999 => 5]])->assertOk()->json('data.tests'));
        $this->assertEquals(120, $saved->firstWhere('option_id', $o['cbc'])['price']);
        $this->assertCount(2, $saved->whereNotNull('price'), 'a word that is not on the lists is ignored');

        // a changed price changes the row; 0 / null removes it
        $this->putJson('/api/v2/business/investigation-prices', ['prices' => [$o['cbc'] => 130, $o['liver'] => 0]])->assertOk();
        $this->assertSame(1, BusinessServicePrice::query()->where('business_id', $lab->id)->count());
        $this->assertEquals(130, BusinessServicePrice::query()->where('business_id', $lab->id)->value('price'));

        // what it priced is what a patient sees on its page and what an order is charged
        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $this->assertSame([$o['cbc']], collect($this->getJson("/api/v2/investigation-centers/{$lab->id}/tests")->json('data.tests'))->pluck('option_id')->all());

        // a doctor / a client has no price list
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, self::CLINIC));
        $this->getJson('/api/v2/business/investigation-prices')->assertStatus(403);
    }

    public function test_a_declined_doctors_order_goes_back_to_the_patient_to_send_elsewhere(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();
        $first = $this->user(User::TYPE_BUSINESS, self::LAB, 'First');
        $second = $this->user(User::TYPE_BUSINESS, self::LAB, 'Second');
        $this->price($first, $o['cbc'], 100);
        $this->price($second, $o['cbc'], 110);

        $id = $this->issue($doctor, $patient, [$o['cbc']])->json('data.order.id');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $first->id])->assertOk();

        Sanctum::actingAs($first);
        $this->postJson("/api/v2/business/investigation-orders/{$id}/decline", ['note' => 'مغلق'])->assertOk()->assertJsonPath('data.order.status', 'issued');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $second->id])->assertOk()->assertJsonPath('data.order.center.id', $second->id);
    }

    public function test_the_patient_asks_a_centre_directly_with_a_photo_of_a_paper_request(): void
    {
        [, $patient, $o] = $this->setUpOrder();
        $radiology = $this->user(User::TYPE_BUSINESS, self::RADIOLOGY, 'Rad');
        $this->price($radiology, $o['sono'], 350);

        Sanctum::actingAs($patient);
        $res = $this->post('/api/v2/investigation-orders/request', [
            'center_id' => $radiology->id, 'option_ids' => [$o['sono']], 'notes' => 'سونار بطن', 'photo' => $this->file('request.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('sent', $res->json('data.order.status'));
        $this->assertNull($res->json('data.order.doctor'));
        $this->assertEquals(350, $res->json('data.order.total'));
        $this->assertCount(1, $res->json('data.order.request_files'));
        $id = $res->json('data.order.id');

        // a centre that does not do it is refused
        $other = $this->user(User::TYPE_BUSINESS, self::RADIOLOGY, 'Other');
        $this->post('/api/v2/investigation-orders/request', ['center_id' => $other->id, 'option_ids' => [$o['sono']]], ['Accept' => 'application/json'])->assertStatus(422);
        // a business that is not a centre is refused
        $this->post('/api/v2/investigation-orders/request', ['center_id' => $this->user(User::TYPE_BUSINESS, self::CLINIC)->id, 'option_ids' => [$o['sono']]], ['Accept' => 'application/json'])->assertStatus(422);

        // declining a patient's own request ends it
        Sanctum::actingAs($radiology);
        $this->postJson("/api/v2/business/investigation-orders/{$id}/decline")->assertOk()->assertJsonPath('data.order.status', 'declined');

        InvestigationOrder::query()->find($id)->delete();
    }

    public function test_an_order_is_cancelled_by_its_doctor_or_patient_until_the_centre_accepts(): void
    {
        [$doctor, $patient, $o] = $this->setUpOrder();
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $this->price($lab, $o['cbc'], 100);

        $id = $this->issue($doctor, $patient, [$o['cbc']])->json('data.order.id');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/investigation-orders/{$id}/send", ['center_id' => $lab->id])->assertOk();

        Sanctum::actingAs($lab);
        $this->postJson("/api/v2/business/investigation-orders/{$id}/accept")->assertOk();

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/investigation-orders/{$id}/cancel")->assertStatus(422);

        $second = $this->issue($doctor, $patient, [$o['cbc']])->json('data.order.id');
        Sanctum::actingAs($doctor);
        $this->postJson("/api/v2/investigation-orders/{$second}/cancel")->assertOk()->assertJsonPath('data.order.status', 'cancelled');

        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $this->postJson("/api/v2/investigation-orders/{$second}/cancel")->assertNotFound();
    }
}
