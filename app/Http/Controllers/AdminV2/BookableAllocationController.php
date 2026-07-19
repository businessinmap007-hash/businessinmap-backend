<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\CommercialOffer;
use App\Models\PlatformService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookableAllocationController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $allocationType = trim((string) $request->get('allocation_type', ''));
        $partnershipId = (int) $request->get('partnership_id', 0);
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $query = BookableAllocation::query()
            ->with([
                'partnership.ownerBusiness:id,name,type,logo',
                'partnership.partnerBusiness:id,name,type,logo',
                'ownerBusiness:id,name,type,logo',
                'partnerBusiness:id,name,type,logo',
                'bookableItem:id,business_id,service_id,title,code,item_type,price,quantity,is_active',
                'platformService:id,key,name_ar,name_en',
            ]);

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('bookable_item_id', (int) $q)
                        ->orWhere('partnership_id', (int) $q);
                }

                $w->orWhereHas('ownerBusiness', function (Builder $b) use ($q) {
                    $b->where('name', 'like', "%{$q}%");
                })->orWhereHas('partnerBusiness', function (Builder $b) use ($q) {
                    $b->where('name', 'like', "%{$q}%");
                })->orWhereHas('bookableItem', function (Builder $b) use ($q) {
                    $b->where('title', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            });
        }

        if ($partnershipId > 0) {
            $query->where('partnership_id', $partnershipId);
        }

        if ($status !== '' && array_key_exists($status, BookableAllocation::statuses())) {
            $query->where('status', $status);
        }

        if ($allocationType !== '' && array_key_exists($allocationType, BookableAllocation::allocationTypes())) {
            $query->where('allocation_type', $allocationType);
        }

        $rows = $query->latest('id')->paginate($perPage)->withQueryString();

        $totals = [
            'all' => BookableAllocation::query()->count(),
            'active' => BookableAllocation::query()->where('status', BookableAllocation::STATUS_ACTIVE)->count(),
            'paused' => BookableAllocation::query()->where('status', BookableAllocation::STATUS_PAUSED)->count(),
            'stopped' => BookableAllocation::query()->where('status', BookableAllocation::STATUS_STOPPED)->count(),
        ];

        $partnerships = BusinessPartnership::query()
            ->with(['ownerBusiness:id,name', 'partnerBusiness:id,name'])
            ->latest('id')
            ->limit(300)
            ->get();

        return view('admin-v2.bookable-allocations.index', compact(
            'rows',
            'q',
            'status',
            'allocationType',
            'partnershipId',
            'perPage',
            'totals',
            'partnerships'
        ));
    }

    public function create(Request $request)
    {
        $partnershipId = (int) $request->get('partnership_id', 0);
        $partnership = $partnershipId ? BusinessPartnership::query()->find($partnershipId) : null;

        $allocation = new BookableAllocation([
            'partnership_id' => $partnership?->id,
            'owner_business_id' => $partnership?->owner_business_id,
            'partner_business_id' => $partnership?->partner_business_id,
            'allocation_type' => BookableAllocation::TYPE_NON_GUARANTEED,
            'quantity_total' => 1,
            'quantity_sold' => 0,
            'quantity_reserved' => 0,
            'quantity_released' => 0,
            'release_days_before' => 0,
            'contract_price' => 0,
            'currency' => 'EGP',
            'markup_type' => 'none',
            'markup_value' => 0,
            'status' => BookableAllocation::STATUS_ACTIVE,
        ]);

        return view('admin-v2.bookable-allocations.create', $this->formData($allocation));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $allocation = DB::transaction(function () use ($data) {
            $allocation = BookableAllocation::create($data);
            $this->syncOffer($allocation);

            return $allocation;
        });

        return redirect()
            ->route('admin.bookable-allocations.edit', $allocation->id)
            ->with('success', __('تم إنشاء الحصة وتوليد العرض التجاري.'));
    }

    public function edit(BookableAllocation $bookableAllocation)
    {
        return view('admin-v2.bookable-allocations.edit', $this->formData($bookableAllocation));
    }

    public function update(Request $request, BookableAllocation $bookableAllocation)
    {
        $data = $this->validatedData($request, $bookableAllocation);

        DB::transaction(function () use ($bookableAllocation, $data) {
            $bookableAllocation->update($data);
            $this->syncOffer($bookableAllocation->refresh());
        });

        return redirect()
            ->route('admin.bookable-allocations.edit', $bookableAllocation->id)
            ->with('success', __('تم تحديث الحصة وتحديث العرض التجاري.'));
    }

    public function destroy(BookableAllocation $bookableAllocation)
    {
        DB::transaction(function () use ($bookableAllocation) {
            CommercialOffer::query()
                ->where('source_type', CommercialOffer::SOURCE_ALLOCATION)
                ->where('source_id', (int) $bookableAllocation->id)
                ->delete();

            $bookableAllocation->delete();
        });

        return redirect()
            ->route('admin.bookable-allocations.index')
            ->with('success', __('تم حذف الحصة والعرض المرتبط بها.'));
    }

    public function stop(BookableAllocation $bookableAllocation)
    {
        $bookableAllocation->update(['status' => BookableAllocation::STATUS_STOPPED]);
        $this->syncOffer($bookableAllocation->refresh());

        return back()->with('success', __('تم إيقاف الحصة.'));
    }

    public function activate(BookableAllocation $bookableAllocation)
    {
        $bookableAllocation->update(['status' => BookableAllocation::STATUS_ACTIVE]);
        $this->syncOffer($bookableAllocation->refresh());

        return back()->with('success', __('تم تفعيل الحصة.'));
    }

    private function validatedData(Request $request, ?BookableAllocation $allocation = null): array
    {
        $data = $request->validate([
            'partnership_id' => ['required', 'integer', 'exists:business_partnerships,id'],
            'bookable_item_id' => ['required', 'integer', 'exists:bookable_items,id'],
            'platform_service_id' => ['nullable', 'integer', 'exists:platform_services,id'],
            'allocation_type' => ['required', Rule::in(array_keys(BookableAllocation::allocationTypes()))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'quantity_total' => ['required', 'integer', 'min:0'],
            'quantity_sold' => ['nullable', 'integer', 'min:0'],
            'quantity_reserved' => ['nullable', 'integer', 'min:0'],
            'quantity_released' => ['nullable', 'integer', 'min:0'],
            'release_days_before' => ['nullable', 'integer', 'min:0'],
            'min_nights' => ['nullable', 'integer', 'min:1'],
            'max_nights' => ['nullable', 'integer', 'min:1'],
            'contract_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'markup_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'markup_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys(BookableAllocation::statuses()))],
            'meta_json' => ['nullable', 'string'],
        ]);

        $partnership = BusinessPartnership::query()->findOrFail((int) $data['partnership_id']);
        $bookable = BookableItem::query()->findOrFail((int) $data['bookable_item_id']);

        if ((int) $bookable->business_id !== (int) $partnership->owner_business_id) {
            abort(422, __('الوحدة المختارة يجب أن تكون مملوكة لصاحب الأصل في الشراكة.'));
        }

        $data['owner_business_id'] = (int) $partnership->owner_business_id;
        $data['partner_business_id'] = (int) $partnership->partner_business_id;
        $data['platform_service_id'] = ($data['platform_service_id'] ?? null) ?: (int) $bookable->service_id;
        $data['quantity_sold'] = (int) ($data['quantity_sold'] ?? 0);
        $data['quantity_reserved'] = (int) ($data['quantity_reserved'] ?? 0);
        $data['quantity_released'] = (int) ($data['quantity_released'] ?? 0);
        $data['release_days_before'] = (int) ($data['release_days_before'] ?? 0);
        $data['markup_type'] = $data['markup_type'] ?: 'none';
        $data['markup_value'] = (float) ($data['markup_value'] ?? 0);
        $data['meta'] = $this->decodeJson($data['meta_json'] ?? null);
        unset($data['meta_json']);

        return $data;
    }

    private function syncOffer(BookableAllocation $allocation): CommercialOffer
    {
        $allocation->loadMissing(['bookableItem', 'ownerBusiness', 'partnerBusiness']);

        $bookable = $allocation->bookableItem;
        $title = $bookable ? $bookable->display_name : ('Allocation #' . $allocation->id);
        $availableQuantity = $allocation->availableQuantity();
        $status = $allocation->status === BookableAllocation::STATUS_ACTIVE
            ? CommercialOffer::STATUS_ACTIVE
            : CommercialOffer::STATUS_PAUSED;

        return CommercialOffer::updateOrCreate(
            [
                'source_type' => CommercialOffer::SOURCE_ALLOCATION,
                'source_id' => (int) $allocation->id,
            ],
            [
                'offerable_type' => CommercialOffer::OFFERABLE_BOOKABLE_ITEM,
                'offerable_id' => (int) $allocation->bookable_item_id,
                'owner_business_id' => (int) $allocation->owner_business_id,
                'seller_business_id' => (int) $allocation->partner_business_id,
                'title_ar' => $title,
                'title_en' => $title,
                'base_price' => (float) $allocation->contract_price,
                'final_price' => $allocation->finalPrice(),
                'currency' => $allocation->currency ?: 'EGP',
                'discount_type' => null,
                'discount_value' => null,
                'availability_mode' => CommercialOffer::AVAILABILITY_LIMITED,
                'available_quantity' => $availableQuantity,
                'starts_at' => $allocation->starts_at,
                'ends_at' => $allocation->ends_at,
                'is_refundable' => (bool) data_get($allocation->meta, 'is_refundable', false),
                'payment_model' => data_get($allocation->meta, 'payment_model'),
                'ranking_score' => (float) data_get($allocation->meta, 'ranking_score', 0),
                'status' => $status,
                'meta' => [
                    'allocation_id' => (int) $allocation->id,
                    'partnership_id' => (int) $allocation->partnership_id,
                    'allocation_type' => (string) $allocation->allocation_type,
                    'release_days_before' => (int) $allocation->release_days_before,
                    'min_nights' => $allocation->min_nights,
                    'max_nights' => $allocation->max_nights,
                    'source' => 'bookable_allocation_admin',
                ],
            ]
        );
    }

    private function decodeJson(?string $json): ?array
    {
        $json = trim((string) $json);

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            abort(422, __('Meta JSON غير صالح.'));
        }

        return $decoded;
    }

    private function formData(BookableAllocation $allocation): array
    {
        $partnerships = BusinessPartnership::query()
            ->with(['ownerBusiness:id,name', 'partnerBusiness:id,name'])
            ->latest('id')
            ->limit(500)
            ->get();

        $ownerId = old('owner_business_id', $allocation->owner_business_id);

        if (! $ownerId && $allocation->partnership_id) {
            $partnership = $partnerships->firstWhere('id', (int) $allocation->partnership_id);
            $ownerId = $partnership ? $partnership->owner_business_id : null;
        }

        $bookables = BookableItem::query()
            ->with(['business:id,name', 'service:id,key,name_ar,name_en'])
            ->when($ownerId, fn (Builder $q) => $q->where('business_id', (int) $ownerId))
            ->latest('id')
            ->limit(500)
            ->get();

        $services = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        return [
            'allocation' => $allocation,
            'partnerships' => $partnerships,
            'bookables' => $bookables,
            'services' => $services,
        ];
    }
}
