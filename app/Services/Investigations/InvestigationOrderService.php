<?php

namespace App\Services\Investigations;

use App\Models\BusinessServicePrice;
use App\Models\Image;
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

    /** What this centre charges for one test or exam — the price it wrote in «أسعاري» — or null when it does not do it. */
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
     * The results, as photos of the papers or the films. The patient reads them; the doctor who ordered the tests
     * is told too and reads them from the order.
     *
     * @param  list<UploadedFile>  $files
     */
    public function attachResults(InvestigationOrder $order, array $files, ?string $note): InvestigationOrder
    {
        $this->assertStatus($order, [InvestigationOrder::STATUS_ACCEPTED, InvestigationOrder::STATUS_READY]);

        DB::transaction(function () use ($order, $files, $note) {
            foreach ($files as $file) {
                $order->images()->create([
                    'image' => app(ImageUploadService::class)->storePrivate($file),
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
