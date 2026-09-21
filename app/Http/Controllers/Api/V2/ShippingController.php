<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrderResource;
use App\Services\Shipping\ShippingService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Governorate shipping. A merchant picks a shipping company for an order going
 * to another governorate; the company (a Shipping & Delivery account) keeps its
 * price list, sets the appointment and moves the order to shipped / delivered.
 */
final class ShippingController extends Controller
{
    public function __construct(private readonly ShippingService $shipping)
    {
    }

    // ── the company's price list ──

    /** GET /api/v2/business/shipping/rates - every governorate with this company's price (or null). */
    public function rates(Request $request)
    {
        $company = BusinessContext::business($request);
        $prices = $this->shipping->ratesFor((int) $company->id);

        $rows = DB::table('governorates')->orderBy('sort_order')->orderBy('id')->get(['id', 'name_ar', 'name_en'])
            ->filter(fn ($g) => (int) $g->id !== (int) $company->governorate_id)
            ->map(fn ($g) => [
                'governorate_id' => (int) $g->id,
                'name_ar' => $g->name_ar,
                'name_en' => $g->name_en,
                'price' => $prices[(int) $g->id]['price'] ?? null,
                'days' => $prices[(int) $g->id]['days'] ?? null,
            ])->values();

        return response()->json(['success' => true, 'data' => [
            'from_governorate_id' => $company->governorate_id ? (int) $company->governorate_id : null,
            'rates' => $rows,
        ]]);
    }

    /** PUT /api/v2/business/shipping/rates */
    public function updateRates(Request $request)
    {
        $data = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'rates.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'rates.*.days' => ['nullable', 'array'],
            'rates.*.days.*' => ['integer', 'between:0,6'],
        ]);

        $this->shipping->replaceRates(BusinessContext::business($request), $data['rates']);

        return $this->rates($request);
    }

    // ── the merchant ──

    /** GET /api/v2/business/orders/{order}/shipping-companies */
    public function companies(Request $request, int $order)
    {
        return response()->json([
            'success' => true,
            'data' => ['companies' => $this->shipping->companiesFor(BusinessContext::id($request), $order)],
        ]);
    }

    /** POST /api/v2/business/orders/{order}/shipping-company */
    public function assign(Request $request, int $order)
    {
        $data = $request->validate(['company_id' => ['required', 'integer']]);

        $model = $this->shipping->assignCompany(BusinessContext::id($request), $order, (int) $data['company_id']);

        return (new OrderResource($model->load(['shippingCompany:id,name', 'business:id,name,logo', 'user:id,name,phone'])))->additional(['success' => true]);
    }

    // ── the company ──

    /** GET /api/v2/business/shipping/orders */
    public function orders(Request $request)
    {
        $company = BusinessContext::business($request);
        abort_unless($company->isShippingCarrier(), 403, __('هذه الخدمة لحسابات «شحن وتوصيل» فقط.'));

        return OrderResource::collection($this->shipping->ordersOf((int) $company->id)->load('shippingCompany:id,name'))
            ->additional(['success' => true]);
    }

    /** POST /api/v2/business/shipping/orders/{order}/appointment */
    public function appointment(Request $request, int $order)
    {
        $data = $request->validate([
            'appointment_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $this->shipping->setAppointment(BusinessContext::id($request), $order, Carbon::parse($data['appointment_at']), $data['note'] ?? null);

        return $this->orderResponse($model);
    }

    /** POST /api/v2/business/shipping/orders/{order}/shipped */
    public function shipped(Request $request, int $order)
    {
        return $this->orderResponse($this->shipping->markShipped(BusinessContext::id($request), $order));
    }

    /** POST /api/v2/business/shipping/orders/{order}/delivered */
    public function delivered(Request $request, int $order)
    {
        return $this->orderResponse($this->shipping->markDelivered(BusinessContext::id($request), $order));
    }

    private function orderResponse($model)
    {
        return (new OrderResource($model->load(['shippingCompany:id,name', 'business:id,name,logo', 'user:id,name,phone'])))
            ->additional(['success' => true]);
    }
}
