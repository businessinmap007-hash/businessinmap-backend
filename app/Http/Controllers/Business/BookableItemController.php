<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\Image;
use App\Models\OfferingOption;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My bookable units" for the business owner.
 *
 * Pure inventory: the owner adds the physical units they actually own
 * (room 101, table 5) picking from the item types allowed for their own
 * category_child. Prices/deposits live in BusinessServicePrice (per type),
 * not here, per the services blueprint. Every query is scoped to the owner.
 */
class BookableItemController extends Controller
{
    use ResolvesOwnerCatalog;

    /** أكثر ما تنشئه دفعةٌ واحدة — حارسٌ من خطأٍ مطبعىٍّ فى «إلى». */
    private const BULK_LIMIT = 200;

    /** نفسُ حدِّ صنف المنيو: غرفةٌ لا تحتاج أكثر من عشر لقطات. */
    private const MAX_IMAGES = 10;

    private function scopedItem(int $id): BookableItem
    {
        return BookableItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $serviceId = (int) $request->get('service_id', 0);
        $q = trim((string) $request->get('q', ''));

        $services = $this->servicesForChild();

        $rows = BookableItem::query()
            ->with(['service:id,key,name_ar,name_en', 'lineOption:id,name_ar,name_en'])
            ->where('business_id', $this->businessId())
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(item_type) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.bookable-items.index', [
            'rows' => $rows,
            'services' => $services,
            'serviceId' => $serviceId,
            'q' => $q,
            'childId' => $this->childId(),
            'prices' => $this->pricesByKind($rows->getCollection()),
        ]);
    }

    /**
     * سعرُ كل نوعٍ فى هذه الصفحة — و`null` تعنى «لم يُسعَّر».
     *
     * وحدةٌ لا يُسعَّر نوعُها تُرفَض عند الحجز، ولم يكن فى «وحداتى» ما يقوله:
     * القائمةُ تعرض الكودَ والسعةَ والحالة، فيظنّ صاحبُها كلَّ شىءٍ جاهزًا حتى
     * يفشل حجزٌ حقيقىّ. العمودُ يقولها قبل ذلك.
     *
     * ويُحلّ مرّةً لكل نوع لا لكل وحدة: خمسون غرفةً على ثلاثة أنواعٍ ثلاثةُ
     * استعلامات.
     *
     * @param  \Illuminate\Support\Collection<int,BookableItem>  $items
     * @return array<string,float|null>
     */
    private function pricesByKind($items): array
    {
        $resolver = app(\App\Services\BusinessServicePriceResolver::class);
        $out = [];

        foreach ($items as $item) {
            $key = ((string) $item->item_type) . ':' . (int) ($item->line_option_id ?? 0);

            if (array_key_exists($key, $out)) {
                continue;
            }

            $row = $resolver->resolveForBookableItem($item);
            $out[$key] = $row ? round((float) $row->baseUnitPrice(), 2) : null;
        }

        return $out;
    }

    public function create(): View
    {
        $services = $this->servicesForChild();

        return view('business.bookable-items.create', [
            'row' => new BookableItem(['is_active' => 1, 'quantity' => 1]),
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'lineOptions' => $this->lineOptionsForUnits(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        BookableItem::create($data + [
            'business_id' => $this->businessId(),
        ]);

        return redirect()
            ->route('business.bookable-items.index', ['service_id' => $data['service_id']])
            ->with('success', 'تمت إضافة الوحدة بنجاح.');
    }

    /**
     * «٦ غرف فردى و١٠ مزدوجة و٥ أجنحة، من ١٠١ إلى ١٠٦ ومن ١٠٧ إلى ١١١».
     *
     * الشاشةُ القديمة تُنشئ وحدةً واحدة لكل حفظ، فواحدٌ وعشرون غرفةً واحدٌ
     * وعشرون نموذجًا لا يتغيّر بينها إلا رقم.
     */
    public function bulk(): View
    {
        $services = $this->servicesForChild();

        return view('business.bookable-items.bulk', [
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'lineOptions' => $this->lineOptionsForUnits(),
            'unitOptions' => $this->unitOptions(),
        ]);
    }

    /**
     * ينشئ المدى دفعةً واحدة، ويتخطّى ما هو موجود.
     *
     * التخطّى لا الفشل: من أضاف ١٠١–١٠٦ ثم عاد ليضيف ١٠١–١١٠ يقصد الأربعةَ
     * الناقصة، ورفضُ الدفعة كلِّها لأجل صفٍّ قائم يجعله يحسب المدى بنفسه —
     * وهذا هو العمل الذى جاء ليتخلّص منه.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'max:100'],
            'line_option_id' => ['nullable', 'integer'],
            'prefix' => ['nullable', 'string', 'max:40'],
            'from' => ['required', 'integer', 'min:0'],
            'to' => ['required', 'integer', 'min:0', 'gte:from'],
            'pad' => ['nullable', 'integer', 'min:0', 'max:6'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            // وصفٌ واحد للدفعة كلِّها: عشرُ غرفٍ مزدوجة تشترك فى وصفها، وما
            // يخصّ غرفةً بعينها يُكتب لها بعد ذلك من شاشتها.
            'description' => ['nullable', 'string', 'max:2000'],
            // «٦ غرف فردى سعرها ٦٠٠» — السعرُ يُكتب مع الدفعة لا بعدها فى
            // شاشةٍ أخرى. يُكتب على سطر سعر هذا النوع، وهو مصدرُ السعر الوحيد.
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
            // زيادةُ كل صفةٍ من صفات الوحدات: «إطلالة بحرية +١٠٠».
            'option_adjust' => ['nullable', 'array'],
            'option_adjust_type' => ['nullable', 'array'],
        ], [], [
            'service_id' => 'الخدمة',
            'item_type' => 'نوع العنصر',
            'from' => 'من',
            'to' => 'إلى',
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['item_type']);

        $this->assertAllowed($serviceId, $itemType);

        $from = (int) $data['from'];
        $to = (int) $data['to'];

        // حدٌّ أعلى للدفعة: خطأٌ مطبعىٌّ فى «إلى» لا يجوز أن يصنع ألف غرفة.
        if (($to - $from) >= self::BULK_LIMIT) {
            throw ValidationException::withMessages([
                'to' => __('المدى أكبر من :max وحدة فى الدفعة الواحدة.', ['max' => self::BULK_LIMIT]),
            ]);
        }

        $lineOptionId = $this->sanitizeLineOption($data['line_option_id'] ?? null);
        $modifiers = $this->sanitizeUnitOptions($data['option_ids'] ?? [], $lineOptionId);

        $prefix = trim((string) ($data['prefix'] ?? ''));
        $pad = (int) ($data['pad'] ?? 0);

        $existing = BookableItem::query()
            ->where('business_id', $this->businessId())
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->flip();

        $created = 0;
        $skipped = 0;

        for ($n = $from; $n <= $to; $n++) {
            $code = $prefix . ($pad > 0 ? str_pad((string) $n, $pad, '0', STR_PAD_LEFT) : (string) $n);

            if (isset($existing[$code])) {
                $skipped++;

                continue;
            }

            $item = BookableItem::create([
                'business_id' => $this->businessId(),
                'service_id' => $serviceId,
                'item_type' => $itemType,
                'line_option_id' => $lineOptionId,
                'code' => $code,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
                'quantity' => 1,
                'is_active' => 1,
            ]);

            // صفاتُ الوحدة نفسها — الإطلالة، البلكونة — تُسعِّرها لاحقًا بلا
            // أن يؤشّرها النزيل. ولا زيادةَ تُكتب هنا: السعرُ صفةُ سطرِ السعر.
            if ($modifiers !== []) {
                $item->syncOfferingOptions($lineOptionId, $modifiers);
            }

            $created++;
        }

        $priced = $this->writeBatchPricing(
            serviceId: $serviceId,
            itemType: $itemType,
            lineOptionId: $lineOptionId,
            price: $data['price'] ?? null,
            unitModifiers: $modifiers,
            adjustments: $this->readAdjustments($data, $modifiers)
        );

        $message = __('أُضيفت :created وحدة، وتُخطّيت :skipped موجودة.', [
            'created' => $created,
            'skipped' => $skipped,
        ]);

        if ($priced) {
            $message .= ' ' . __('وحُفظ سعرها.');
        }

        return redirect()
            ->route('business.bookable-items.index', ['service_id' => $serviceId])
            ->with('success', $message);
    }

    /**
     * سعرُ هذه الدفعة، وما يزيده عليها.
     *
     * السعرُ ليس على الوحدة بل على سطرِ سعرِ نوعها — وهذا هو مصدرُ السعر
     * الوحيد فى المنصّة — فكتابةُ ٦٠٠ هنا تعنى: أنشئ لهذا النوع سطرَ سعرٍ
     * إن لم يكن له، ثم ضع عليه ما اخترتَ من زيادات.
     *
     * والدمجُ لا الاستبدال: `syncOfferingOptions` تمسح ثم تكتب، فمن حفظ
     * دفعةً ثانية على نفس النوع كان يمحو زيادةً كتبها من شاشة الأسعار.
     * القراءةُ من `currentOfferingAdjustments()` قبل الكتابة تمنع ذلك.
     *
     * @param  array<int,int>  $unitModifiers  ما هو مثبَّتٌ على غرف هذه الدفعة
     * @param  array<int,array{type:string,value:float}>  $adjustments
     * @return bool  هل كُتب شىء؟
     */
    private function writeBatchPricing(
        int $serviceId,
        string $itemType,
        ?int $lineOptionId,
        mixed $price,
        array $unitModifiers,
        array $adjustments
    ): bool {
        $hasPrice = $price !== null && $price !== '';

        if (! $hasPrice && $adjustments === []) {
            return false;
        }

        $row = BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('service_id', $serviceId)
            ->where('bookable_item_type', $itemType)
            ->where('line_option_id', (int) $lineOptionId)
            ->first();

        if (! $row) {
            // بلا سعرٍ مكتوب لا يُنشأ سطرٌ فارغ: سطرٌ بصفرٍ يُقرأ «مجّانًا».
            if (! $hasPrice) {
                return false;
            }

            $row = BusinessServicePrice::create([
                'business_id' => $this->businessId(),
                'child_id' => $this->childId(),
                'service_id' => $serviceId,
                'bookable_item_type' => $itemType,
                'line_option_id' => (int) $lineOptionId,
                'price' => (float) $price,
                'currency' => BusinessServicePrice::DEFAULT_CURRENCY,
                'is_active' => 1,
            ]);
        } elseif ($hasPrice) {
            $row->update(['price' => (float) $price]);
        }

        $existingIds = $row->offeringOptions()
            ->where('role', OfferingOption::ROLE_MODIFIER)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        $merged = array_values(array_unique(array_merge($existingIds, $unitModifiers)));

        $row->syncOfferingOptions(
            $lineOptionId,
            $merged,
            $adjustments + $row->currentOfferingAdjustments()
        );

        return true;
    }

    /**
     * الزياداتُ المُرسَلة، لكلٍّ من القائمتين، مصفّاةً على ما قُبل منهما.
     *
     * الصفرُ يُكتب كما هو: «إفطار» بصفرٍ مُوصِّفٌ يُعرض ولا يُغيّر الرقم،
     * وهى حالٌ مقصودة لا نقصٌ فى الإدخال.
     *
     * @param  array<int,int>  $unitModifiers
     * @return array<int,array{type:string,value:float}>
     */
    private function readAdjustments(array $data, array $unitModifiers): array
    {
        $out = [];

        foreach ([
            ['option_adjust', 'option_adjust_type', $unitModifiers],
        ] as [$valueKey, $typeKey, $allowed]) {
            foreach ((array) ($data[$valueKey] ?? []) as $optionId => $value) {
                $optionId = (int) $optionId;

                if (! in_array($optionId, $allowed, true) || $value === null || $value === '') {
                    continue;
                }

                $type = (string) (($data[$typeKey] ?? [])[$optionId] ?? OfferingOption::ADJUST_AMOUNT);

                $out[$optionId] = [
                    'type' => in_array($type, OfferingOption::adjustTypes(), true)
                        ? $type
                        : OfferingOption::ADJUST_AMOUNT,
                    'value' => round((float) $value, 2),
                ];
            }
        }

        return $out;
    }

    /**
     * ما يصلح صفةً للوحدة.
     *
     * ما أعلنه التاجرُ «يزيد على سعر الوحدة» — الإطلالةُ ونحوها. ونظامُ
     * الوجبات ليس منه: يُعلَن `addon` فيسقط من هنا ويظهر فى شاشته.
     */
    private function unitOptions()
    {
        $vocabulary = app(\App\Services\MerchantOfferingVocabulary::class);

        return app(\App\Services\BookingVocabularyRoles::class)->only(
            $vocabulary->for($this->businessId(), $this->childId(), $this->rootId())['modifiers'],
            $this->businessId(),
            \App\Services\BookingVocabularyRoles::ROLE_UNIT,
            $vocabulary->everythingOffered($this->businessId(), $this->childId(), $this->rootId())
        );
    }

    /** @return array<int,int> */
    private function sanitizeUnitOptions(array $ids, ?int $lineOptionId): array
    {
        // ما عرضته الشاشةُ هو ما يُقبل — بما فيه مجموعةٌ أعلنها التاجرُ
        // «تزيد على سعر الوحدة» وهى وصفيّةٌ عند المنصّة.
        $allowed = $this->unitOptions()->flatten(1)
            ->map(fn ($o) => (int) $o->id)->unique()->values();

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $lineOptionId && $allowed->contains($id))
            ->unique()->values()->all();
    }

    public function edit(int $id): View
    {
        $row = $this->scopedItem($id);
        $row->load(['images', 'activeBlockedSlots', 'activePriceRules', 'offeringOptions']);
        $services = $this->servicesForChild();

        /*
         * وسعرُ نوعِ هذه الوحدة، على شاشتها.
         *
         * كان يُكتب فى الإضافة بالجملة وحدها — وهى شاشةُ إنشاء — فمن أراد رفعَ
         * السعر بعد ذلك لم يجد أين. والسطرُ سطرُ النوع لا الوحدة: تعديلُه هنا
         * يغيّر سعرَ كلِّ غرفةٍ فردية، وهو ما تقوله الشاشةُ صراحةً.
         */
        $price = $this->priceRowFor($row);

        return view('business.bookable-items.edit', [
            'row' => $row,
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'lineOptions' => $this->lineOptionsForUnits(),
            'priceRow' => $price,
            'unitOptions' => $this->unitOptions(),
            'unitModifierIds' => $row->modifierOptionIds(),
            'priceAdjustments' => $price ? $price->currentOfferingAdjustments() : [],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->scopedItem($id);

        $data = $this->validateData($request);

        $row->update($data);

        return back()->with('success', 'تم تحديث الوحدة بنجاح.');
    }

    /**
     * ما أعلنته وحداتُ هذا التاجر عن نفسها — إطلالةً أو بلكونة.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function declaredByUnits(): \Illuminate\Support\Collection
    {
        return OfferingOption::query()
            ->where('offering_type', (new BookableItem)->getMorphClass())
            ->whereIn('offering_id', BookableItem::query()
                ->where('business_id', $this->businessId())->select('id'))
            ->where('role', OfferingOption::ROLE_MODIFIER)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->unique();
    }

    /** سطرُ سعرِ نوعِ هذه الوحدة، إن وُجد. */
    private function priceRowFor(BookableItem $item): ?BusinessServicePrice
    {
        return BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('service_id', (int) $item->service_id)
            ->where('bookable_item_type', (string) $item->item_type)
            ->where('line_option_id', (int) ($item->line_option_id ?? 0))
            ->first();
    }

    /**
     * حفظُ سعرِ النوع وإضافاته من شاشة الوحدة.
     *
     * ── ولمَ لا تدمج هذه ──────────────────────────────────────────────────
     *
     * `writeBatchPricing` تدمج ما تجده مع ما أُرسل، لأن الإضافةَ بالجملة لا
     * ترى ما سبقها: من أضاف ست غرفٍ أخرى بعد شهر لا يُسأل عن الإفطار، فمحوُه
     * منه سرقةٌ صامتة. وهذه الشاشةُ ترى القائمةَ كاملةً ومؤشَّرةً، فما تركه
     * صاحبُها تركه قصدًا — والدمجُ هنا يجعل نزعَ العلامة بلا أثر.
     */
    public function storePricing(Request $request, int $id): RedirectResponse
    {
        $item = $this->scopedItem($id);

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
            'option_adjust' => ['nullable', 'array'],
            'option_adjust_type' => ['nullable', 'array'],
        ], [], [
            'price' => 'السعر',
        ]);

        $lineOptionId = (int) ($item->line_option_id ?? 0) ?: null;

        $modifiers = $this->sanitizeUnitOptions($data['option_ids'] ?? [], $lineOptionId);

        $row = $this->priceRowFor($item) ?: BusinessServicePrice::create([
            'business_id' => $this->businessId(),
            'child_id' => $this->childId(),
            'service_id' => (int) $item->service_id,
            'bookable_item_type' => (string) $item->item_type,
            'line_option_id' => (int) ($lineOptionId ?? 0),
            'currency' => BusinessServicePrice::DEFAULT_CURRENCY,
            'is_active' => 1,
        ]);

        $row->update(['price' => (float) $data['price']]);

        /*
         * وإضافاتُ الحجز تبقى كما هى.
         *
         * هذه الشاشةُ تدير صفاتِ هذه الغرفة، ولا تعرض «إفطارًا» ولا تُسأل عنه.
         * و`syncOfferingOptions` تمسح ثم تكتب — فلو كتبت ما عندها وحده لمحت
         * نظامَ الوجبات كلَّه من هذا النوع، صامتةً. وهو نفسُ الحرص المعكوس فى
         * `BookingAddOnController`: كلٌّ يحفظ نطاقَه ويترك نطاقَ الآخر.
         *
         * والفصلُ بينهما ليس علمًا ثانيًا: الصفةُ ما أعلنته وحدةٌ عن نفسها،
         * والإضافةُ ما لم تعلنه — وهو نفسُ الفصل الذى تقرأ به واجهةُ العميل.
         */
        $declared = $this->declaredByUnits();

        $addOns = collect($row->currentOfferingAdjustments())
            ->reject(fn ($adjust, $id) => $declared->contains((int) $id))
            ->all();

        $row->syncOfferingOptions(
            $lineOptionId,
            array_merge($modifiers, array_map('intval', array_keys($addOns))),
            $this->readAdjustments($data, $modifiers) + $addOns
        );

        /*
         * وصفاتُ الغرفة تُثبَّت عليها، لا على السطر وحده.
         *
         * هذا هو الفرقُ الذى تحمله الشاشة: ما على السطر يُعرض على النزيل
         * ليختار، وما على الغرفة أيضًا يُحسَب لها بلا أن يُسأل عنه.
         */
        $item->syncOfferingOptions($lineOptionId, $modifiers);

        return back()->with('success', __('تم حفظ سعر هذا النوع.'));
    }

    /**
     * صورُ الوحدة — الغرفةُ تُرى قبل أن تُحجَز.
     *
     * على شاشة التعديل وحدها: نموذجُ الإنشاء `POST` عادىٌّ بلا
     * `multipart`، فالملفاتُ المرفوعة فيه تسقط بلا صوت. نفسُ ترتيب صنف
     * المنيو، لأنها نفسُ الآلية.
     */
    public function storeImages(Request $request, int $id): RedirectResponse
    {
        $item = $this->scopedItem($id);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . self::MAX_IMAGES],
            'images.*' => ImageUploadService::validationRules(),
        ]);

        $room = self::MAX_IMAGES - $item->images()->count();

        if ($room <= 0) {
            return back()->withErrors(['images' => __('الحد الأقصى :max صور للوحدة الواحدة.', ['max' => self::MAX_IMAGES])]);
        }

        $uploads = app(ImageUploadService::class);

        foreach (array_slice($request->file('images'), 0, $room) as $file) {
            $item->images()->create([
                'image' => $uploads->store($file),
                'source' => Image::SOURCE_UPLOAD,
            ]);
        }

        return back()->with('success', __('تم رفع الصور.'));
    }

    /** الصفُّ والملفُّ معًا — راجع HasOwnedImages. */
    public function destroyImage(int $id, int $image): RedirectResponse
    {
        $item = $this->scopedItem($id);
        $row = $item->images()->findOrFail($image);

        app(ImageUploadService::class)->delete($row->image);
        $row->delete();

        return back()->with('success', __('تم حذف الصورة.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = $this->scopedItem($id);
        $row->delete();

        return redirect()
            ->route('business.bookable-items.index')
            ->with('success', 'تم حذف الوحدة بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'max:100'],
            'line_option_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
        ], [], [
            'service_id' => 'الخدمة',
            'item_type' => 'نوع العنصر',
            'code' => 'الكود',
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['item_type']);

        // Guard the posted select values against the owner's own catalog.
        $this->assertAllowed($serviceId, $itemType);

        return [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            // Which kind this unit is — «جناح» rather than just «حجز إقامة» —
            // and therefore which of the merchant's priced rows is its own.
            'line_option_id' => $this->sanitizeLineOption($data['line_option_id'] ?? null),
            'code' => trim((string) $data['code']),
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            // جمهوران لا جمهور: الوصفُ يخرج للنزيل، والملاحظةُ تبقى للمحل.
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
