<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\AddressResource;
use App\Models\Address;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * v2 address book — the mobile app manages the user's saved delivery addresses.
 * This surface did not exist in ANY prior API (was web-only). Every row is
 * scoped to the authenticated user; exactly one address is primary at a time.
 */
final class AddressController extends Controller
{
    /** Eager-loaded so a list of addresses is one query, not one per row. */
    private const PLACE_RELATIONS = [
        'country:id,name_ar,name_en',
        'governorate:id,name_ar,name_en',
        'city:id,name_ar,name_en',
    ];

    /** GET /api/v2/addresses */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()
            ->with(self::PLACE_RELATIONS)
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

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address->load(self::PLACE_RELATIONS)),
        ], 201);
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

        return response()->json([
            'success' => true,
            'data' => new AddressResource($model->fresh()->load(self::PLACE_RELATIONS)),
        ]);
    }

    /** POST /api/v2/addresses/{address}/primary */
    public function setPrimary(Request $request, int $address)
    {
        $model = $this->ownedOrFail($request, $address);

        DB::transaction(function () use ($request, $model) {
            $request->user()->addresses()->update(['is_primary' => false]);
            $model->update(['is_primary' => true]);
        });

        return response()->json([
            'success' => true,
            'data' => new AddressResource($model->fresh()->load(self::PLACE_RELATIONS)),
        ]);
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
            abort(404, __('العنوان غير موجود.'));
        }

        return $model;
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        // These used to point at `locations`, which holds 71 country rows and no
        // governorates or cities at all — so governorate_id=1 (القاهرة) was
        // rejected outright while governorate_id=2 "passed" by matching a
        // COUNTRY row. No address could ever be created correctly, and the table
        // had zero rows to prove it. The live tables are the ISO `countries`,
        // `governorates` and `cities`, which is what the fee-rule admin, the
        // scheduling service and the v1 pickers have always read.
        $data = $request->validate([
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'governorate_id' => [$required, 'integer', 'exists:governorates,id'],
            'city_id' => [$required, 'integer', 'exists:cities,id'],
            'address_line' => [$required, 'string', 'min:5', 'max:191'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        return $this->withConsistentHierarchy($request, $data, $creating);
    }

    /**
     * Three ids that each exist are still not an address: nothing above stops
     * "القاهرة" paired with a city in أسوان, and a delivery driver would be sent
     * to a place the customer never chose.
     *
     * The city is the truth — it is what the user actually picked — so the
     * governorate and country are checked against it, and country_id is derived
     * rather than trusted when the caller leaves it out.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function withConsistentHierarchy(Request $request, array $data, bool $creating): array
    {
        $model = $creating ? null : $request->user()->addresses()->find($request->route('address'));

        $cityId = $data['city_id'] ?? $model?->city_id;
        $governorateId = $data['governorate_id'] ?? $model?->governorate_id;

        if (! $cityId || ! $governorateId) {
            return $data;
        }

        $city = City::query()->find($cityId);

        if ($city && (int) $city->governorate_id !== (int) $governorateId) {
            throw ValidationException::withMessages([
                'city_id' => __('المدينة المختارة لا تتبع المحافظة المختارة.'),
            ]);
        }

        $governorate = Governorate::query()->find($governorateId);

        if ($governorate) {
            $countryId = $data['country_id'] ?? $model?->country_id;

            if ($countryId !== null && (int) $governorate->country_id !== (int) $countryId) {
                throw ValidationException::withMessages([
                    'governorate_id' => __('المحافظة المختارة لا تتبع الدولة المختارة.'),
                ]);
            }

            // Derived, not asked for: the governorate already knows its country.
            $data['country_id'] = (int) $governorate->country_id;
        }

        return $data;
    }
}
