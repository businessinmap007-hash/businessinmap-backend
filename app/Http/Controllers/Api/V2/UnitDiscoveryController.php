<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\BusinessServicePriceResolver;
use App\Services\ServiceExecutionEngine;
use Illuminate\Http\Request;

/**
 * The rooms a customer may actually book, and what each costs.
 *
 * 21 category children set `requires_bookable_item` — every hotel child, the
 * restaurants, the pitches, the halls, the pools, the coworking spaces. For
 * those the engine refuses a booking without a NAMED unit, and the client must
 * send `bookable_id`. Nothing told it what those ids were: there was no
 * customer-facing endpoint over `bookable_items` at all, so the booking flow
 * for those 21 could not be completed from the app whatever the business owned.
 *
 * Grouped by KIND rather than listed flat, because that is how the price works
 * and how a customer chooses: «جناح — 1000 — 4 متاحة» and then which suite.
 * Naming the kind only became possible once the unit could carry a line option.
 *
 * Public (no auth) — browsing rooms should not require signing in.
 */
final class UnitDiscoveryController extends Controller
{
    public function __construct(
        private readonly BusinessServicePriceResolver $prices,
        private readonly BookableAvailabilityService $availability,
        private readonly ServiceExecutionEngine $engine
    ) {
    }

    /** GET /api/v2/discovery/units/{business} */
    public function show(Request $request, int $business)
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $biz = User::query()->where('type', 'business')
            ->find($business, ['id', 'name', 'logo', 'category_id', 'category_child_id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        // Booking is the default because it is the service that demands a named
        // unit; any other is only honoured if the client asks for it.
        $serviceId = (int) ($data['service_id'] ?? 0)
            ?: (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $units = BookableItem::query()
            // الصورُ والمُوصِّفات محمَّلةٌ مقدَّمًا: عشرون غرفةً كانت عشرين
            // استعلامًا لكلٍّ منهما.
            ->with(['lineOption:id,name_ar,name_en', 'images', 'offeringOptions', 'service'])
            ->where('business_id', $biz->id)
            ->where('is_active', 1)
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when(! empty($data['item_type']), fn ($query) => $query->where('item_type', $data['item_type']))
            ->orderBy('item_type')
            ->orderBy('code')
            ->orderBy('id')
            ->get();

        // A window is optional: without it the answer is «what exists and what
        // it costs», with it «what is still free». Asking for a price is the
        // common first screen and must not require picking dates first.
        $window = (! empty($data['starts_at']) && ! empty($data['ends_at']))
            ? [$data['starts_at'], $data['ends_at']]
            : null;

        $groups = [];

        foreach ($units->groupBy(fn (BookableItem $unit) => (int) ($unit->line_option_id ?? 0)) as $kindId => $inKind) {
            /** @var \App\Models\BookableItem $first */
            $first = $inKind->first();
            $price = $this->prices->resolveForBookableItem($first);

            $rows = $inKind->map(fn (BookableItem $unit) => $this->unitPayload($unit, $window, $price));

            $groups[] = [
                'line_option_id' => $kindId ?: null,
                'name' => $this->kindName($first),
                'item_type' => (string) ($first->item_type ?? ''),
                'units_count' => $rows->count(),
                'available_count' => $window ? $rows->where('available', true)->count() : null,
                'price' => $price ? round((float) $price->baseUnitPrice(), 2) : null,
                'currency' => $price ? (string) ($price->currency ?: 'EGP') : null,
                // The row the price came from, so a client can send it back as
                // `offering_id` and be priced off exactly what it was shown.
                'offering_id' => $price ? (int) $price->id : null,
                // ما يُعرض على النزيل ليقرّره: «إفطار +٥٠»، «إقامة كاملة
                // +١٥٠». يُرسَل مع النوع لا مع الوحدة، لأنه سعرُ النوع.
                'choices' => $this->choicesOf($price, $inKind),
                'units' => $rows->values(),
            ];
        }

        // Priced kinds first, then by price: an unpriced kind cannot be sold and
        // is the one the business still has to finish, not the one to lead with.
        usort($groups, function (array $a, array $b) {
            return [$a['price'] === null, $a['price'] ?? 0] <=> [$b['price'] === null, $b['price'] ?? 0];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                ],
                'service_id' => $serviceId ?: null,
                'starts_at' => $window[0] ?? null,
                'ends_at' => $window[1] ?? null,
                'kinds' => $groups,
            ],
        ]);
    }

    /**
     * غرفةٌ كما تُعرض فى قائمة: صورةٌ ووصفٌ وسعرُها هى.
     *
     * كانت رقمًا وسعة — لا صورةَ ولا كلمة — فقائمةُ الغرف لا تشبه المنيو فى
     * شىء. والسعرُ هنا سعرُ هذه الغرفة بعينها لا سعرُ نوعها: «إطلالة بحرية»
     * مكتوبةٌ على الغرفة نفسها، وزيادتُها على سطر السعر تُضاف قبل أن تُعرض،
     * فتُقرأ ١٠١ بسبعمئة و١٠٢ بستّمئة من سطرٍ واحد.
     *
     * و`notes` لا يخرج من هنا أبدًا: هو ما يكتبه صاحبُ المحل لموظّفيه.
     *
     * @param  array{0:string,1:string}|null  $window
     */
    private function unitPayload(BookableItem $unit, ?array $window, ?BusinessServicePrice $price = null): array
    {
        $payload = [
            'id' => (int) $unit->id,
            'code' => (string) ($unit->code ?? ''),
            'title' => $unit->title,
            'label' => $unit->displayLabel(),
            'description' => $unit->description,
            'capacity' => $unit->capacity !== null ? (int) $unit->capacity : null,
            'images' => $unit->imagePayload(),
        ];

        $payload += $this->pricingOf($unit, $price, $window[0] ?? null);

        if (! $window) {
            return $payload;
        }

        // Reuses the service the engine itself checks with, so a unit shown as
        // free here cannot be refused at booking for a reason this never saw.
        $check = $this->availability->check($unit, $window[0], $window[1]);

        $payload['available'] = (bool) $check['available'];
        $payload['reason'] = $check['reason'];

        return $payload;
    }

    /**
     * ما تعلنه الوحدةُ عن نفسها، وما يكلّفه.
     *
     * المُوصِّفاتُ المسعَّرة تُقرأ بنفس الحساب الذى يحسب به المحرّك الفاتورة
     * — `resolvePriceBreakdown` نفسها — فما يُعرض هنا هو ما سيُحاسَب عليه
     * النزيل، لا تقديرًا موازيًا له.
     *
     * وسطرُ سعرٍ ناقص يعنى نوعًا لم يُسعَّر بعد: تُعرض الوحدةُ بلا سعر بدل
     * أن تُخفى، فصاحبُ المحل يرى ما ينقصه.
     *
     * @return array<string,mixed>
     */
    private function pricingOf(BookableItem $unit, ?BusinessServicePrice $price, ?string $on = null): array
    {
        if (! $price) {
            return ['price' => null, 'modifiers' => []];
        }

        $ownOptions = $unit->relationLoaded('offeringOptions')
            ? $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')->all()
            : $unit->modifierOptionIds()->all();

        $breakdown = $this->engine->resolvePriceBreakdown(
            service: $unit->service ?: new PlatformService(),
            businessPrice: $price,
            bookable: $unit,
            quantity: 1,
            // بتاريخ الوصول حين يُعطى: قاعدةُ «الجمعة أغلى» تُقرأ فى
            // القائمة كما ستُقرأ فى الفاتورة، لا بعدها.
            pricingDate: $on,
            optionIds: array_map('intval', $ownOptions)
        );

        return [
            'price' => (float) $breakdown['unit_price'],
            'modifiers' => $breakdown['modifiers'],
        ];
    }

    /**
     * ما يُعرض على النزيل مع هذا النوع، وسعرُ كلٍّ منه.
     *
     * «غرفة فردى ٦٠٠» ثم «إفطار +٥٠» و«إقامة كاملة +١٥٠»، كما تُقرأ فى أىِّ
     * موقع حجز. وهى مُوصِّفاتُ سطر السعر — إلا ما أعلنته الغرفةُ عن نفسها
     * أصلًا: «إطلالة بحرية» محسوبةٌ فى سعرها المعروض، فعرضُها ثانيةً كخيارٍ
     * يُحصِّل ثمنَها مرتين.
     *
     * @param  \Illuminate\Support\Collection<int,BookableItem>  $inKind
     * @return array<int,array<string,mixed>>
     */
    private function choicesOf(?BusinessServicePrice $price, $inKind): array
    {
        if (! $price) {
            return [];
        }

        $declared = $inKind->flatMap(
            fn (BookableItem $unit) => $unit->relationLoaded('offeringOptions')
                ? $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')
                : $unit->modifierOptionIds()
        )->map(fn ($id) => (int) $id)->unique();

        $rows = $price->offeringOptions()
            ->where('role', OfferingOption::ROLE_MODIFIER)
            // مُوصِّفٌ بقيمة صفر يوصِّف ولا يُسعِّر، فلا شأن لشاشة الاختيار به.
            ->where('adjust_value', '!=', 0)
            ->whereNotIn('option_id', $declared->all() ?: [0])
            ->with('option:id,name_ar,name_en')
            ->get();

        return $rows->map(fn (OfferingOption $row) => [
            'option_id' => (int) $row->option_id,
            'name' => $this->say($row->option),
            'adjust_type' => (string) $row->adjust_type,
            'adjust_value' => (float) $row->adjust_value,
            // ما ستدفعه فعلًا إن اخترتَه، محسوبًا على سعر هذا النوع.
            'amount' => $row->appliedTo(round((float) $price->baseUnitPrice(), 2)),
        ])->values()->all();
    }

    private function say(?object $option): ?string
    {
        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;

        return ($primary !== null && $primary !== '') ? $primary : (($option->name_ar ?: $option->name_en) ?: null);
    }

    private function kindName(BookableItem $unit): ?string
    {
        $option = $unit->lineOption;

        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;

        return ($primary !== null && $primary !== '')
            ? $primary
            : (($option->name_ar ?: $option->name_en) ?: null);
    }
}
