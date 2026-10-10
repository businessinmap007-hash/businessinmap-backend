<?php

namespace App\Services\Investigations;

use App\Models\BusinessServicePrice;
use App\Models\Image;
use App\Models\PlatformService;
use App\Models\InvestigationOrder;
use App\Models\InvestigationOrderItem;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\BusinessCapability;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Investigation orders: a doctor's lab tests and radiology exams for a patient, shared by the patient with a registered
 * centre that prices them, accepts them (with a time) and attaches the results.
 *
 *   issued ─ send ─▶ sent ─ accept ─▶ accepted ─ results ─▶ ready
 *      ▲               │ decline (a doctor's order goes back to `issued`; a patient's own request is `declined`)
 *      └───────────────┘
 *   cancel: the doctor or the patient, until the centre accepts.
 *
 * The tests and exams are always picked from the platform's lists («التحاليل الطبية» and «أنواع الأشعة»), never typed, so
 * a centre prices exactly the same words the doctor chose — the price of a whole order at each centre is a sum.
 */
final class InvestigationOrderService
{
    /** option group name → the kind its words are. */
    private const GROUPS = ['التحاليل الطبية' => InvestigationOrderItem::KIND_LAB, 'أنواع الأشعة' => InvestigationOrderItem::KIND_RADIOLOGY];

    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    // ───────────────────────── who may do what ─────────────────────────

    /** A business of the health root issues orders — except a lab or a radiology centre, which only receive. */
    public function canIssue(?User $user): bool
    {
        return $user
            && $user->isBusiness()
            && BusinessCapability::standsUnderHealth($user)
            && ! in_array((int) ($user->category_child_id ?? 0), InvestigationOrder::RECEIVE_ONLY_CHILDREN, true);
    }

    public function canReceive(?User $user): bool
    {
        return InvestigationOrder::isCenter($user);
    }

    // ───────────────────────── the lists ─────────────────────────

    /** @return array{lab:list<array<string,mixed>>,radiology:list<array<string,mixed>>} */
    public function catalog(): array
    {
        $out = ['lab' => [], 'radiology' => []];

        $rows = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', array_keys(self::GROUPS))
            ->orderBy('g.name_ar')->orderBy('o.sort_order')->orderBy('o.id')
            ->get(['o.id', 'o.name_ar', 'o.name_en', 'g.name_ar as group_name']);

        foreach ($rows as $row) {
            $kind = self::GROUPS[$row->group_name];
            $out[$kind][] = [
                'id' => (int) $row->id,
                'name' => app()->getLocale() === 'en' ? ($row->name_en ?: $row->name_ar) : $row->name_ar,
            ];
        }

        return $out;
    }

    /** @return Collection<int,object{id:int,name_ar:string,kind:string}> */
    private function optionsFor(array $optionIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $optionIds)));

        $rows = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('o.id', $ids)->whereIn('g.name_ar', array_keys(self::GROUPS))
            ->get(['o.id', 'o.name_ar', 'g.name_ar as group_name'])
            ->map(fn ($r) => (object) ['id' => (int) $r->id, 'name_ar' => (string) $r->name_ar, 'kind' => self::GROUPS[$r->group_name]]);

        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages(['option_ids' => __('اختر من قائمة التحاليل والأشعة فقط.')]);
        }

        return $rows->sortBy(fn ($r) => array_search($r->id, $ids, true))->values();
    }

    // ───────────────────────── issue / request ─────────────────────────

    public function issue(User $doctor, User $patient, array $optionIds, ?string $notes): InvestigationOrder
    {
        $options = $this->optionsFor($optionIds);

        return DB::transaction(function () use ($doctor, $patient, $options, $notes) {
            $order = InvestigationOrder::create([
                'doctor_id' => (int) $doctor->id, 'patient_id' => (int) $patient->id,
                'status' => InvestigationOrder::STATUS_ISSUED, 'notes' => $notes, 'issued_at' => now(),
            ]);

            $this->createItems($order, $options);

            $this->notify('investigation_issued', (int) $patient->id, $order,
                'طلب فحوصات جديد', 'New investigation order',
                'طلب لك الطبيب فحوصات — اختر معملًا لإرسالها إليه.', 'Your doctor ordered tests for you — pick a centre to send them to.');

            return $order->load('items');
        });
    }

    /** The patient asks a centre directly, with no doctor — optionally with a photo of a paper request. */
    public function requestFromCenter(User $patient, User $center, array $optionIds, ?string $notes, ?UploadedFile $photo): InvestigationOrder
    {
        $this->assertCenter($center);
        $options = $this->optionsFor($optionIds);

        return DB::transaction(function () use ($patient, $center, $options, $notes, $photo) {
            $order = InvestigationOrder::create([
                'doctor_id' => null, 'patient_id' => (int) $patient->id,
                'status' => InvestigationOrder::STATUS_ISSUED, 'notes' => $notes, 'issued_at' => now(),
            ]);

            $this->createItems($order, $options);

            if ($photo) {
                $order->images()->create([
                    'image' => app(ImageUploadService::class)->storePrivate($photo),
                    'source' => Image::SOURCE_UPLOAD, 'purpose' => InvestigationOrder::PURPOSE_REQUEST,
                ]);
            }

            return $this->send($order, $center);
        });
    }

    /** @param Collection<int,object> $options */
    private function createItems(InvestigationOrder $order, Collection $options): void
    {
        foreach ($options->values() as $i => $o) {
            $order->items()->create(['option_id' => $o->id, 'kind' => $o->kind, 'name' => $o->name_ar, 'sort_order' => $i]);
        }
    }

    // ───────────────────────── centres and prices ─────────────────────────

    /** What this centre charges for one test or exam — the price it wrote in its own test list — or null when it does not do it. */
    public function priceAt(User $center, int $optionId): ?float
    {
        $price = BusinessServicePrice::query()
            ->where('business_id', (int) $center->id)->where('line_option_id', $optionId)->where('is_active', 1)
            ->where('price', '>', 0)->orderBy('price')->value('price');

        return $price !== null ? round((float) $price, 2) : null;
    }

    /**
     * The registered centres and what each would charge for this whole order — «سعر المجموعة عند كل معمل». Centres that
     * do none of it are left out; the rest are ordered by how much of the order they cover, then by price.
     *
     * @return list<array<string,mixed>>
     */
    public function centersFor(InvestigationOrder $order): array
    {
        $items = $order->items;

        $centers = User::query()->where('type', User::TYPE_BUSINESS)
            ->whereIn('category_child_id', InvestigationOrder::CENTER_CHILDREN)
            ->get(['id', 'name', 'name_en', 'category_child_id']);

        $rows = [];

        foreach ($centers as $center) {
            $priced = $items->map(fn (InvestigationOrderItem $i) => ['item' => $i, 'price' => $i->option_id ? $this->priceAt($center, (int) $i->option_id) : null]);
            $covered = $priced->filter(fn ($p) => $p['price'] !== null);

            if ($covered->isEmpty()) {
                continue;
            }

            $rows[] = [
                'id' => (int) $center->id,
                'name' => app()->getLocale() === 'en' ? ($center->name_en ?: $center->name) : $center->name,
                'covers' => $covered->count(),
                'of' => $items->count(),
                'total' => round((float) $covered->sum('price'), 2),
                'missing' => $priced->filter(fn ($p) => $p['price'] === null)->map(fn ($p) => $p['item']->name)->values()->all(),
            ];
        }

        usort($rows, fn ($a, $b) => [$b['covers'], $a['total']] <=> [$a['covers'], $b['total']]);

        return $rows;
    }

    /**
     * The tests and exams this centre does, with its price for each — what a patient picks from on the centre's page.
     * The same words as the doctor's lists, so an order from either door is priced the same way.
     *
     * @return list<array{option_id:int,kind:string,name:string,price:float}>
     */
    public function testsOf(User $center): array
    {
        $catalog = $this->catalog();
        $names = [];

        foreach (['lab', 'radiology'] as $kind) {
            foreach ($catalog[$kind] as $row) {
                $names[$row['id']] = [$kind, $row['name']];
            }
        }

        $prices = BusinessServicePrice::query()
            ->where('business_id', (int) $center->id)->whereIn('line_option_id', array_keys($names) ?: [0])
            ->where('is_active', 1)->where('price', '>', 0)->orderBy('price')->get(['line_option_id', 'price'])
            ->unique('line_option_id');

        $out = [];

        foreach ($prices as $row) {
            [$kind, $name] = $names[(int) $row->line_option_id];
            $out[] = ['option_id' => (int) $row->line_option_id, 'kind' => $kind, 'name' => $name, 'price' => round((float) $row->price, 2)];
        }

        return $out;
    }

    /**
     * The centre's own price list: every test and exam of the platform's lists with what THIS centre charges for it
     * (null = it does not do it). A centre prices its tests here, in the same screen that receives its orders — not in a
     * general «prices» screen of the services it sells.
     *
     * @return list<array{option_id:int,kind:string,name:string,price:?float}>
     */
    public function priceListOf(User $center): array
    {
        $catalog = $this->catalog();
        $have = collect($this->testsOf($center))->keyBy('option_id');
        $out = [];

        foreach (['lab', 'radiology'] as $kind) {
            foreach ($catalog[$kind] as $row) {
                $out[] = ['option_id' => $row['id'], 'kind' => $kind, 'name' => $row['name'], 'price' => $have[$row['id']]['price'] ?? null];
            }
        }

        return $out;
    }

    /**
     * Saves what the centre sent — `{optionId: price|null}`. A price writes (or changes) the row, null or 0 removes it.
     * Only words of the platform's lists are accepted; anything else is ignored.
     *
     * @param  array<int|string,mixed>  $prices
     */
    public function savePrices(User $center, array $prices): array
    {
        $allowed = collect($this->catalog())->flatten(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        DB::transaction(function () use ($center, $prices, $allowed, $serviceId) {
            foreach ($prices as $optionId => $price) {
                $optionId = (int) $optionId;

                if (! in_array($optionId, $allowed, true)) {
                    continue;
                }

                $keys = [
                    'business_id' => (int) $center->id, 'service_id' => $serviceId, 'child_id' => (int) $center->category_child_id,
                    'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE, 'line_option_id' => $optionId,
                ];

                if ($price === null || $price === '' || (float) $price <= 0) {
                    BusinessServicePrice::query()->where($keys)->delete();

                    continue;
                }

                BusinessServicePrice::query()->updateOrCreate($keys, ['price' => round((float) $price, 2), 'currency' => 'EGP', 'is_active' => 1]);
            }
        });

        return $this->priceListOf($center);
    }

    // ───────────────────────── the centre's side ─────────────────────────

    /** The patient shares the order with a centre; it is priced there and the centre is told. */
    public function send(InvestigationOrder $order, User $center): InvestigationOrder
    {
        $this->assertCenter($center);

        if ($order->status !== InvestigationOrder::STATUS_ISSUED) {
            throw ValidationException::withMessages(['status' => __('لا يمكن إرسال هذا الطلب الآن.')]);
        }

        return DB::transaction(function () use ($order, $center) {
            $total = 0.0;
            $covered = 0;

            foreach ($order->items as $item) {
                $price = $item->option_id ? $this->priceAt($center, (int) $item->option_id) : null;
                $item->update(['price' => $price]);

                if ($price !== null) {
                    $total += $price;
                    $covered++;
                }
            }

            if ($covered === 0) {
                throw ValidationException::withMessages(['center_id' => __('هذه الجهة لا تجري أيًّا من هذه الفحوصات.')]);
            }

            $order->update([
                'center_id' => (int) $center->id, 'status' => InvestigationOrder::STATUS_SENT,
                'total' => round($total, 2), 'sent_at' => now(),
            ]);

            $this->notify('investigation_received', (int) $center->id, $order,
                'طلب فحوصات جديد', 'New investigation order',
                'وصلك طلب فحوصات من مريض.', 'A patient sent you an investigation order.');

            return $order->fresh(['items']);
        });
    }

    public function accept(InvestigationOrder $order, ?\DateTimeInterface $at, ?string $note): InvestigationOrder
    {
        $this->assertStatus($order, [InvestigationOrder::STATUS_SENT]);

        $order->update(['status' => InvestigationOrder::STATUS_ACCEPTED, 'appointment_at' => $at, 'center_note' => $note, 'accepted_at' => now()]);

        $this->notify('investigation_accepted', (int) $order->patient_id, $order,
            'قبول طلب الفحوصات', 'Investigation order accepted',
            'قبلت الجهة طلبك' . ($at ? ' وحدّدت موعدًا.' : '.'), 'The centre accepted your order' . ($at ? ' and set a time.' : '.'));

        return $order->fresh(['items']);
    }

    /** A doctor's order goes back to the patient to send elsewhere; a patient's own request simply ends. */
    public function decline(InvestigationOrder $order, ?string $note): InvestigationOrder
    {
        $this->assertStatus($order, [InvestigationOrder::STATUS_SENT]);

        $back = $order->doctor_id !== null;

        $order->update([
            'status' => $back ? InvestigationOrder::STATUS_ISSUED : InvestigationOrder::STATUS_DECLINED,
            'center_id' => $back ? null : $order->center_id,
            'center_note' => $note, 'total' => null, 'sent_at' => $back ? null : $order->sent_at,
        ]);

        $this->notify('investigation_declined', (int) $order->patient_id, $order,
            'اعتذرت الجهة عن طلب الفحوصات', 'The centre declined your order',
            $back ? 'اعتذرت الجهة — أرسل الطلب إلى جهة أخرى.' : 'اعتذرت الجهة عن طلبك.',
            $back ? 'The centre declined — send the order to another one.' : 'The centre declined your request.');

        return $order->fresh(['items']);
    }

    /**
     * The results: TEXT beside each test it answers (the lab already has them as text — a few bytes, readable in the
     * app, copyable into the patient's file), and photos only for what is not text. The patient reads them; the doctor
     * who ordered the tests is told too and reads them from the order.
     *
     * @param  list<UploadedFile>  $files
     * @param  array<int|string,string|null>  $texts  item id => the result as written
     */
    public function attachResults(InvestigationOrder $order, array $files, ?string $note, array $texts = []): InvestigationOrder
    {
        $this->assertStatus($order, [InvestigationOrder::STATUS_ACCEPTED, InvestigationOrder::STATUS_READY]);

        $order->loadMissing('items');
        $byId = $order->items->keyBy('id');
        $written = [];
        foreach ($texts as $itemId => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            if (! $byId->has((int) $itemId)) {
                throw ValidationException::withMessages(['texts' => __('نتيجة لفحص ليس في هذا الطلب.')]);
            }
            $written[(int) $itemId] = $text;
        }

        if ($written === [] && $files === []) {
            throw ValidationException::withMessages(['texts' => __('اكتب نتيجة فحص واحد على الأقل أو أرفق صورة.')]);
        }

        DB::transaction(function () use ($order, $files, $note, $written) {
            foreach ($written as $itemId => $text) {
                $order->items->firstWhere('id', $itemId)?->update(['result_text' => $text]);
            }

            foreach ($files as $file) {
                $order->images()->create([
                    // results are photos of papers and films: shrunk, they are a few hundred KB, not megabytes
                    'image' => app(ImageUploadService::class)->storePrivateShrunk($file),
                    'source' => Image::SOURCE_UPLOAD, 'purpose' => InvestigationOrder::PURPOSE_RESULT,
                ]);
            }

            $wasReady = $order->status === InvestigationOrder::STATUS_READY;
            $order->update([
                'status' => InvestigationOrder::STATUS_READY, 'ready_at' => $order->ready_at ?? now(),
                'center_note' => $note !== null && $note !== '' ? $note : $order->center_note,
            ]);

            if (! $wasReady) {
                foreach (array_filter([(int) $order->patient_id, (int) $order->doctor_id]) as $userId) {
                    $this->notify('investigation_ready', $userId, $order,
                        'نتيجة الفحوصات جاهزة', 'Your results are ready',
                        'أضافت الجهة نتيجة الفحوصات.', 'The centre added the results.');
                }
            }
        });

        return $order->fresh(['items', 'images']);
    }

    /**
     * The patient says a copy of the result photos is on his phone. From now on the server's copies are only kept until
     * the ordering doctor has opened them (and a short grace), then deleted — see `purgeFiles()`.
     */
    public function markSaved(InvestigationOrder $order): InvestigationOrder
    {
        $this->assertStatus($order, [InvestigationOrder::STATUS_READY]);

        if ($order->patient_saved_at === null) {
            $order->update(['patient_saved_at' => now()]);
        }

        return $order->fresh(['items', 'images']);
    }

    /**
     * The retention sweep: delete the result PHOTOS (files and rows) of every order where the patient kept a copy and
     * the doctor — if there was one — has read them, once the grace has passed; and of an order nobody kept after the
     * retention window. Text results stay. An order about to expire unkept warns the patient first, once.
     *
     * @return array{purged:int,warned:int}
     */
    public function purgeFiles(?\DateTimeInterface $now = null): array
    {
        $now = \Illuminate\Support\Carbon::instance($now ?? now());
        $grace = $now->copy()->subDays(InvestigationOrder::FILE_GRACE_DAYS);
        $cap = $now->copy()->subDays(InvestigationOrder::FILE_RETENTION_DAYS);
        $warnFrom = $now->copy()->subDays(InvestigationOrder::FILE_RETENTION_DAYS - InvestigationOrder::FILE_WARN_DAYS);
        $purged = 0;
        $warned = 0;

        $orders = InvestigationOrder::query()
            ->whereNull('files_purged_at')
            ->whereHas('images', fn ($q) => $q->where('purpose', InvestigationOrder::PURPOSE_RESULT))
            ->get();

        foreach ($orders as $order) {
            $kept = $order->patient_saved_at !== null && $order->patient_saved_at <= $grace
                && ($order->doctor_id === null || ($order->doctor_seen_at !== null && $order->doctor_seen_at <= $grace));
            $expired = $order->ready_at !== null && $order->ready_at <= $cap;

            if ($kept || $expired) {
                $this->deleteResultFiles($order);
                $purged++;

                continue;
            }

            if ($order->patient_saved_at === null && $order->expiry_warned_at === null && $order->ready_at !== null && $order->ready_at <= $warnFrom) {
                $order->update(['expiry_warned_at' => $now]);
                $this->notify('investigation_files_expiring', (int) $order->patient_id, $order,
                    'احفظ نسخة من نتائجك', 'Save a copy of your results',
                    'ستُحذف صور نتائج فحوصاتك من السيرفر قريبًا — احفظها على هاتفك.', 'The photos of your test results will be deleted from the server soon — keep them on your phone.');
                $warned++;
            }
        }

        return ['purged' => $purged, 'warned' => $warned];
    }

    private function deleteResultFiles(InvestigationOrder $order): void
    {
        $uploads = app(ImageUploadService::class);

        foreach ($order->images()->where('purpose', InvestigationOrder::PURPOSE_RESULT)->get() as $image) {
            $uploads->delete($image->image);
            $image->delete();
        }

        $order->update(['files_purged_at' => now()]);
    }

    public function cancel(InvestigationOrder $order): InvestigationOrder
    {
        $this->assertStatus($order, [InvestigationOrder::STATUS_ISSUED, InvestigationOrder::STATUS_SENT]);

        $order->update(['status' => InvestigationOrder::STATUS_CANCELLED]);

        return $order->fresh(['items']);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function assertCenter(User $center): void
    {
        if (! InvestigationOrder::isCenter($center)) {
            throw ValidationException::withMessages(['center_id' => __('اختر معملًا أو مركز أشعة مسجّلًا.')]);
        }
    }

    /** @param list<string> $allowed */
    private function assertStatus(InvestigationOrder $order, array $allowed): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تنفيذ هذا الإجراء على الطلب في حالته الحالية.')]);
        }
    }

    private function notify(string $eventKey, int $userId, InvestigationOrder $order, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => InvestigationOrder::class, 'notifiable_id' => (int) $order->id,
                'source_type' => InvestigationOrder::class, 'source_id' => (int) $order->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
