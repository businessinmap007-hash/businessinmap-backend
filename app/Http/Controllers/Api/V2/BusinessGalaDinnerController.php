<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\GalaDinner;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * «العشاء الإلزامي»: the hotel names its gala nights (رأس السنة، الكريسماس…) with a price per adult, a child's percentage and the age under
 * which a child is free; every stay over such a night pays it (GalaDinners).
 */
final class BusinessGalaDinnerController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/gala-dinners — the hotel's gala nights from today on */
    public function index()
    {
        return response()->json(['success' => true, 'data' => ['gala_dinners' => app(\App\Services\Hotels\GalaDinners::class)->upcoming($this->businessId())]]);
    }

    /** POST /api/v2/business/gala-dinners — `{date, name, adult_price, child_percent, child_free_under}` */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertFreeDate($data['date']);

        $gala = GalaDinner::create($data + ['business_id' => $this->businessId()]);

        return response()->json(['success' => true, 'data' => ['gala_dinner' => $gala->payload()]], 201);
    }

    /** PUT /api/v2/business/gala-dinners/{id} */
    public function update(Request $request, int $id)
    {
        $gala = $this->mine($id);
        $data = $this->validated($request);
        $this->assertFreeDate($data['date'], $gala->id);

        $gala->update($data);

        return response()->json(['success' => true, 'data' => ['gala_dinner' => $gala->fresh()->payload()]]);
    }

    /** DELETE /api/v2/business/gala-dinners/{id} */
    public function destroy(int $id)
    {
        $this->mine($id)->delete();

        return response()->json(['success' => true]);
    }

    private function mine(int $id): GalaDinner
    {
        return GalaDinner::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'name' => ['required', 'string', 'max:120'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'child_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'child_free_under' => ['nullable', 'integer', 'min:0', 'max:17'],
        ]);

        return [
            'date' => $data['date'],
            'name' => trim($data['name']),
            'adult_price' => round((float) $data['adult_price'], 2),
            'child_percent' => round((float) ($data['child_percent'] ?? 50), 2),
            'child_free_under' => (int) ($data['child_free_under'] ?? 6),
        ];
    }

    private function assertFreeDate(string $date, ?int $except = null): void
    {
        $taken = GalaDinner::query()->where('business_id', $this->businessId())->whereDate('date', $date)
            ->when($except, fn ($q) => $q->whereKeyNot($except))->exists();

        if ($taken) {
            throw ValidationException::withMessages(['date' => __('يوجد عشاء إلزامي فى هذا اليوم بالفعل.')]);
        }
    }
}
