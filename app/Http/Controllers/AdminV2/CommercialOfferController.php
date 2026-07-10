<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferTarget;
use App\Models\User;
use App\Services\Commercial\BusinessOffersSubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommercialOfferController extends Controller
{
    public function index(Request $request, BusinessOffersSubscriptionService $subscriptionService)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $offerableType = trim((string) $request->get('offerable_type', ''));
        $sourceType = trim((string) $request->get('source_type', ''));
        $sellerId = (int) $request->get('seller_business_id', 0);
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $query = CommercialOffer::query()
            ->with(['ownerBusiness:id,name,type,logo', 'sellerBusiness:id,name,type,logo']);

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('offerable_id', (int) $q)
                        ->orWhere('seller_business_id', (int) $q);
                }

                $w->orWhere('title_ar', 'like', "%{$q}%")
                    ->orWhere('title_en', 'like', "%{$q}%")
                    ->orWhereHas('sellerBusiness', function (Builder $b) use ($q) {
                        $b->where('name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('ownerBusiness', function (Builder $b) use ($q) {
                        $b->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if ($status !== '' && in_array($status, $this->statuses(), true)) {
            $query->where('status', $status);
        }

        if ($offerableType !== '' && in_array($offerableType, $this->offerableTypes(), true)) {
            $query->where('offerable_type', $offerableType);
        }

        if ($sourceType !== '' && in_array($sourceType, $this->sourceTypes(), true)) {
            $query->where('source_type', $sourceType);
        }

        if ($sellerId > 0) {
            $query->where('seller_business_id', $sellerId);
        }

        $rows = $query->latest('id')->paginate($perPage)->withQueryString();

        $totals = [
            'all' => CommercialOffer::query()->count(),
            'active' => CommercialOffer::query()->where('status', CommercialOffer::STATUS_ACTIVE)->count(),
            'paused' => CommercialOffer::query()->where('status', CommercialOffer::STATUS_PAUSED)->count(),
            'promotions' => CommercialOffer::query()->where('source_type', CommercialOffer::SOURCE_PROMOTION)->count(),
        ];

        $subscriptionUsage = $sellerId > 0 ? $subscriptionService->usage($sellerId) : null;

        return view('admin-v2.commercial-offers.index', [
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'offerableType' => $offerableType,
            'sourceType' => $sourceType,
            'sellerId' => $sellerId,
            'perPage' => $perPage,
            'totals' => $totals,
            'businesses' => $this->businessOptions(),
            'offerableTypes' => $this->offerableTypes(),
            'sourceTypes' => $this->sourceTypes(),
            'audienceTypes' => CommercialOffer::audienceTypes(),
            'statuses' => $this->statuses(),
            'subscriptionUsage' => $subscriptionUsage,
        ]);
    }

    public function create(BusinessOffersSubscriptionService $subscriptionService)
    {
        return view('admin-v2.commercial-offers.create', [
            'offer' => new CommercialOffer([
                'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
                'offerable_id' => 0,
                'source_type' => CommercialOffer::SOURCE_PROMOTION,
                'audience_type' => CommercialOffer::AUDIENCE_BOTH,
                'base_price' => 0,
                'final_price' => 0,
                'currency' => 'EGP',
                'availability_mode' => CommercialOffer::AVAILABILITY_INSTANT,
                'available_quantity' => null,
                'is_refundable' => false,
                'ranking_score' => 0,
                'status' => CommercialOffer::STATUS_ACTIVE,
            ]),
            'offerableTypes' => $this->offerableTypes(),
            'sourceTypes' => $this->sourceTypes(),
            'audienceTypes' => CommercialOffer::audienceTypes(),
            'availabilityModes' => $this->availabilityModes(),
            'statuses' => $this->statuses(),
            'subscriptionUsage' => null,
            'offersRules' => $subscriptionService->rules(),
            'selectedTargetCategories' => [],
            'selectedTargetChildren' => [],
        ] + $this->taxonomyData());
    }

    public function store(Request $request, BusinessOffersSubscriptionService $subscriptionService)
    {
        $data = $this->validatedData($request);
        [$categoryIds, $childIds] = $this->validatedTargets($request);
        $subscriptionService->ensureCanSaveOffer((int) $data['seller_business_id'], $data);

        $offer = DB::transaction(function () use ($data, $categoryIds, $childIds) {
            $offer = CommercialOffer::create($data);
            $this->syncTargets($offer, $categoryIds, $childIds);

            return $offer;
        });

        return redirect()
            ->route('admin.commercial-offers.edit', $offer->id)
            ->with('success', 'تم إنشاء العرض التجاري بنجاح.');
    }

    public function edit(CommercialOffer $commercialOffer, BusinessOffersSubscriptionService $subscriptionService)
    {
        $commercialOffer->loadMissing(['ownerBusiness:id,name', 'sellerBusiness:id,name', 'targets']);

        return view('admin-v2.commercial-offers.edit', [
            'offer' => $commercialOffer,
            'offerableTypes' => $this->offerableTypes(),
            'sourceTypes' => $this->sourceTypes(),
            'audienceTypes' => CommercialOffer::audienceTypes(),
            'availabilityModes' => $this->availabilityModes(),
            'statuses' => $this->statuses(),
            'subscriptionUsage' => $subscriptionService->usage((int) $commercialOffer->seller_business_id),
            'offersRules' => $subscriptionService->rules(),
            'selectedTargetCategories' => $this->targetIdsOfType($commercialOffer, CommercialOfferTarget::TARGET_CATEGORY),
            'selectedTargetChildren' => $this->targetIdsOfType($commercialOffer, CommercialOfferTarget::TARGET_CATEGORY_CHILD),
        ] + $this->taxonomyData());
    }

    public function update(Request $request, CommercialOffer $commercialOffer, BusinessOffersSubscriptionService $subscriptionService)
    {
        $data = $this->validatedData($request);
        [$categoryIds, $childIds] = $this->validatedTargets($request);
        $subscriptionService->ensureCanSaveOffer((int) $data['seller_business_id'], $data, (int) $commercialOffer->id);

        DB::transaction(function () use ($commercialOffer, $data, $categoryIds, $childIds) {
            $commercialOffer->update($data);
            $this->syncTargets($commercialOffer, $categoryIds, $childIds);
        });

        return redirect()
            ->route('admin.commercial-offers.edit', $commercialOffer->id)
            ->with('success', 'تم تحديث العرض التجاري بنجاح.');
    }

    public function destroy(CommercialOffer $commercialOffer)
    {
        $commercialOffer->delete();

        return redirect()
            ->route('admin.commercial-offers.index')
            ->with('success', 'تم حذف العرض التجاري.');
    }

    public function toggle(CommercialOffer $commercialOffer, BusinessOffersSubscriptionService $subscriptionService)
    {
        $newStatus = $commercialOffer->status === CommercialOffer::STATUS_ACTIVE
            ? CommercialOffer::STATUS_PAUSED
            : CommercialOffer::STATUS_ACTIVE;

        $data = $commercialOffer->toArray();
        $data['status'] = $newStatus;

        $subscriptionService->ensureCanSaveOffer((int) $commercialOffer->seller_business_id, $data, (int) $commercialOffer->id);

        $commercialOffer->update([
            'status' => $newStatus,
        ]);

        return back()->with('success', 'تم تغيير حالة العرض.');
    }

    /** Root categories (21) + category children (304) for the B2B targeting pickers. */
    private function taxonomyData(): array
    {
        return [
            'rootCategories' => Category::query()->where('parent_id', 0)
                ->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
            'categoryChildren' => CategoryChild::query()
                ->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
        ];
    }

    private function targetIdsOfType(CommercialOffer $offer, string $type): array
    {
        return $offer->targets
            ->where('target_type', $type)
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array{0: int[], 1: int[]} [categoryIds, childIds] */
    private function validatedTargets(Request $request): array
    {
        $data = $request->validate([
            'target_categories' => ['nullable', 'array'],
            'target_categories.*' => ['integer', 'exists:categories,id'],
            'target_children' => ['nullable', 'array'],
            'target_children.*' => ['integer', 'exists:category_children_master,id'],
        ], [], [
            'target_categories.*' => 'التصنيف',
            'target_children.*' => 'القسم الفرعي',
        ]);

        $clean = fn ($ids) => array_values(array_unique(array_filter(array_map('intval', (array) ($ids ?? [])))));

        return [$clean($data['target_categories'] ?? []), $clean($data['target_children'] ?? [])];
    }

    /** Replace an offer's targets with the given category + category-child ids. */
    private function syncTargets(CommercialOffer $offer, array $categoryIds, array $childIds): void
    {
        $offer->targets()->delete();

        foreach ($categoryIds as $categoryId) {
            $offer->targets()->create([
                'target_type' => CommercialOfferTarget::TARGET_CATEGORY,
                'target_id' => $categoryId,
            ]);
        }

        foreach ($childIds as $childId) {
            $offer->targets()->create([
                'target_type' => CommercialOfferTarget::TARGET_CATEGORY_CHILD,
                'target_id' => $childId,
            ]);
        }
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'offerable_type' => ['required', Rule::in($this->offerableTypes())],
            'offerable_id' => ['nullable', 'integer', 'min:0'],
            'owner_business_id' => ['required', 'integer', 'exists:users,id'],
            'seller_business_id' => ['required', 'integer', 'exists:users,id'],
            'source_type' => ['required', Rule::in($this->sourceTypes())],
            'audience_type' => ['required', Rule::in(CommercialOffer::audienceTypes())],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'final_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'availability_mode' => ['required', Rule::in($this->availabilityModes())],
            'available_quantity' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_refundable' => ['nullable', 'boolean'],
            'payment_model' => ['nullable', 'string', 'max:50'],
            'ranking_score' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in($this->statuses())],
            'meta_json' => ['nullable', 'string'],
        ]);

        $owner = User::query()->where('id', (int) $data['owner_business_id'])->where('type', User::TYPE_BUSINESS)->exists();
        $seller = User::query()->where('id', (int) $data['seller_business_id'])->where('type', User::TYPE_BUSINESS)->exists();

        if (! $owner || ! $seller) {
            abort(422, 'Owner و Seller يجب أن يكونا من نوع business.');
        }

        $data['offerable_id'] = (int) ($data['offerable_id'] ?? 0);
        $data['source_id'] = $data['source_id'] ?? null;
        $data['audience_type'] = $data['audience_type'] ?: CommercialOffer::AUDIENCE_BOTH;
        $data['is_refundable'] = $request->boolean('is_refundable');
        $data['ranking_score'] = (float) ($data['ranking_score'] ?? 0);
        $data['meta'] = $this->decodeJson($data['meta_json'] ?? null);
        unset($data['meta_json']);

        return $data;
    }

    private function decodeJson(?string $json): ?array
    {
        $json = trim((string) $json);

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            abort(422, 'Meta JSON غير صالح.');
        }

        return $decoded;
    }

    private function businessOptions()
    {
        return User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'category_id', 'category_child_id']);
    }

    private function offerableTypes(): array
    {
        return [
            CommercialOffer::OFFERABLE_BOOKABLE_ITEM,
            CommercialOffer::OFFERABLE_PRODUCT,
            CommercialOffer::OFFERABLE_SERVICE,
            CommercialOffer::OFFERABLE_PACKAGE,
        ];
    }

    private function sourceTypes(): array
    {
        return [
            CommercialOffer::SOURCE_DIRECT,
            CommercialOffer::SOURCE_ALLOCATION,
            CommercialOffer::SOURCE_RESELLER,
            CommercialOffer::SOURCE_PROMOTION,
            CommercialOffer::SOURCE_MARKETPLACE,
        ];
    }

    private function availabilityModes(): array
    {
        return [
            CommercialOffer::AVAILABILITY_INSTANT,
            CommercialOffer::AVAILABILITY_REQUEST,
            CommercialOffer::AVAILABILITY_LIMITED,
        ];
    }

    private function statuses(): array
    {
        return [
            CommercialOffer::STATUS_ACTIVE,
            CommercialOffer::STATUS_PAUSED,
            CommercialOffer::STATUS_EXPIRED,
            CommercialOffer::STATUS_CANCELLED,
        ];
    }
}
