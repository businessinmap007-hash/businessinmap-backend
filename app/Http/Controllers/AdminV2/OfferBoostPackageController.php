<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\OfferBoostPackage;
use App\Models\OfferBoostPurchase;
use App\Services\Commercial\OfferBoostService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OfferBoostPackageController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));

        $query = OfferBoostPackage::query()->withCount('purchases');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('key', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $packages = $query->orderBy('price')->orderBy('duration_days')->paginate(30)->withQueryString();

        $purchases = OfferBoostPurchase::query()
            ->with(['package:id,key,name_ar,name_en', 'offer:id,title_ar,title_en,seller_business_id', 'business:id,name,type,logo'])
            ->latest('id')
            ->limit(20)
            ->get();

        $totals = [
            'packages' => OfferBoostPackage::query()->count(),
            'active_packages' => OfferBoostPackage::query()->where('is_active', 1)->count(),
            'purchases' => OfferBoostPurchase::query()->count(),
            'active_purchases' => OfferBoostPurchase::query()->where('status', OfferBoostPurchase::STATUS_ACTIVE)->count(),
            'revenue' => round((float) OfferBoostPurchase::query()->sum('price'), 2),
        ];

        return view('admin-v2.offer-boost-packages.index', compact('packages', 'purchases', 'totals', 'q', 'status'));
    }

    public function create()
    {
        return view('admin-v2.offer-boost-packages.create', [
            'package' => new OfferBoostPackage([
                'currency' => 'EGP',
                'duration_days' => 7,
                'boost_score' => 10,
                'is_featured' => true,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        OfferBoostPackage::query()->create($data);

        return redirect()->route('admin.offer-boost-packages.index')->with('success', 'تم إنشاء باقة التمييز.');
    }

    public function edit(OfferBoostPackage $offerBoostPackage)
    {
        return view('admin-v2.offer-boost-packages.edit', ['package' => $offerBoostPackage]);
    }

    public function update(Request $request, OfferBoostPackage $offerBoostPackage)
    {
        $data = $this->validatedData($request, $offerBoostPackage->id);
        $offerBoostPackage->update($data);

        return redirect()->route('admin.offer-boost-packages.edit', $offerBoostPackage->id)->with('success', 'تم تحديث باقة التمييز.');
    }

    public function toggle(OfferBoostPackage $offerBoostPackage)
    {
        $offerBoostPackage->update(['is_active' => ! (bool) $offerBoostPackage->is_active]);

        return back()->with('success', 'تم تغيير حالة الباقة.');
    }

    public function boostForm(Request $request)
    {
        $offerId = (int) $request->get('offer_id', 0);
        $offer = $offerId > 0 ? CommercialOffer::query()->with('sellerBusiness:id,name,type,logo')->find($offerId) : null;

        return view('admin-v2.offer-boost-packages.boost-form', [
            'offer' => $offer,
            'offerId' => $offerId,
            'offers' => CommercialOffer::query()->with('sellerBusiness:id,name,type')->latest('id')->limit(300)->get(),
            'packages' => OfferBoostPackage::query()->where('is_active', 1)->orderBy('price')->get(),
        ]);
    }

    public function activateBoost(Request $request, OfferBoostService $boostService)
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer', 'exists:commercial_offers,id'],
            'package_id' => ['required', 'integer', 'exists:offer_boost_packages,id'],
        ]);

        $purchase = $boostService->activate(
            offerId: (int) $data['offer_id'],
            packageId: (int) $data['package_id'],
            adminId: auth()->id()
        );

        return redirect()
            ->route('admin.offer-boost-packages.boost-form', ['offer_id' => $purchase->offer_id])
            ->with('success', 'تم تفعيل Boost للعرض وخصم قيمة الباقة من المحفظة.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:100', Rule::unique('offer_boost_packages', 'key')->ignore($ignoreId)],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'boost_score' => ['required', 'numeric', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'rules_json' => ['nullable', 'string'],
            'meta_json' => ['nullable', 'string'],
        ]);

        $data['key'] = $data['key'] ?: Str::slug($data['name_en'] ?: $data['name_ar'], '_');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['rules'] = $this->jsonOrNull($data['rules_json'] ?? null, 'Rules JSON غير صالح.');
        $data['meta'] = $this->jsonOrNull($data['meta_json'] ?? null, 'Meta JSON غير صالح.');
        unset($data['rules_json'], $data['meta_json']);

        return $data;
    }

    private function jsonOrNull(?string $json, string $message): ?array
    {
        $json = trim((string) $json);
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            abort(422, $message);
        }

        return $decoded;
    }
}
