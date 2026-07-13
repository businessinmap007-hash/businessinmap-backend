<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * v2 address book — the mobile app manages the user's saved delivery addresses.
 * This surface did not exist in ANY prior API (was web-only). Every row is
 * scoped to the authenticated user; exactly one address is primary at a time.
 */
final class AddressController extends Controller
{
    /** GET /api/v2/addresses */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_primary')->orderByDesc('id')->get();

        return response()->json(['success' => true, 'data' => AddressResource::collection($addresses)]);
    }

    /** POST /api/v2/addresses */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $userId = (int) $request->user()->id;
        $hasAny = $request->user()->addresses()->exists();
        // First address is always primary; otherwise honour the flag.
        $makePrimary = ! $hasAny || ! empty($data['is_primary']);

        $address = DB::transaction(function () use ($request, $data, $userId, $makePrimary) {
            if ($makePrimary) {
                $request->user()->addresses()->update(['is_primary' => false]);
            }

            return $request->user()->addresses()->create([
                'country_id' => $data['country_id'] ?? null,
                'governorate_id' => $data['governorate_id'],
                'city_id' => $data['city_id'],
                'zip_code' => $data['zip_code'] ?? null,
                'address_line' => $data['address_line'],
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'is_primary' => $makePrimary,
            ]);
        });

        return response()->json(['success' => true, 'data' => new AddressResource($address)], 201);
    }

    /** PATCH /api/v2/addresses/{address} */
    public function update(Request $request, int $address)
    {
        $model = $this->ownedOrFail($request, $address);
        $data = $this->validated($request, false);

        DB::transaction(function () use ($request, $model, $data) {
            if (! empty($data['is_primary'])) {
                $request->user()->addresses()->where('id', '!=', $model->id)->update(['is_primary' => false]);
            }
            $model->fill($data)->save();
        });

        return response()->json(['success' => true, 'data' => new AddressResource($model->fresh())]);
    }

    /** POST /api/v2/addresses/{address}/primary */
    public function setPrimary(Request $request, int $address)
    {
        $model = $this->ownedOrFail($request, $address);

        DB::transaction(function () use ($request, $model) {
            $request->user()->addresses()->update(['is_primary' => false]);
            $model->update(['is_primary' => true]);
        });

        return response()->json(['success' => true, 'data' => new AddressResource($model->fresh())]);
    }

    /** DELETE /api/v2/addresses/{address} */
    public function destroy(Request $request, int $address)
    {
        $model = $this->ownedOrFail($request, $address);
        $wasPrimary = (bool) $model->is_primary;
        $model->delete();

        // Promote the newest remaining address if we removed the primary one.
        if ($wasPrimary) {
            $next = $request->user()->addresses()->orderByDesc('id')->first();
            $next?->update(['is_primary' => true]);
        }

        return response()->json(['success' => true]);
    }

    private function ownedOrFail(Request $request, int $addressId): Address
    {
        $model = $request->user()->addresses()->find($addressId);
        if (! $model) {
            abort(404, 'العنوان غير موجود.');
        }

        return $model;
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'country_id' => ['nullable', 'integer', 'exists:locations,id'],
            'governorate_id' => [$required, 'integer', 'exists:locations,id'],
            'city_id' => [$required, 'integer', 'exists:locations,id'],
            'address_line' => [$required, 'string', 'min:5', 'max:191'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
    }
}
