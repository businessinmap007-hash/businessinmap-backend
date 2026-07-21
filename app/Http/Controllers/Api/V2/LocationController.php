<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Http\Request;

/**
 * v2 geography — the pickers the address book cannot work without.
 *
 * Until this existed, POST /api/v2/addresses REQUIRED governorate_id and
 * city_id and v2 offered no way to discover a single valid id, so no address
 * could ever be created (the table had zero rows) and therefore no delivery
 * could be ordered.
 *
 * Source of truth: `countries` (249, full ISO 3166-1), `governorates` (27) and
 * `cities` (1,339). NOT the `locations` tree, which holds 71 country rows whose
 * name_ar AND name_en are empty for every single one, and no governorates or
 * cities at all — it was never populated. Everything written recently already
 * reads these tables: the v1 dropdown API, the scheduling service, and the
 * BIM-3.5 fee rules admin. See §14 of the engineering reference.
 *
 * Public on purpose: an address is picked during registration and checkout,
 * before there is anything to authenticate with.
 */
final class LocationController extends Controller
{
    /** GET /api/v2/locations/countries — optional ?q= over name/ISO. */
    public function countries(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $countries = Country::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")
                    ->orWhere('iso2', 'like', "%{$q}%")
                    ->orWhere('iso3', 'like', "%{$q}%");
            }))
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'iso2', 'iso3', 'phone_code', 'flag'])
            ->map(fn (Country $c) => [
                'id' => (int) $c->id,
                'name_ar' => $c->name_ar,
                'name_en' => $c->name_en,
                'iso2' => $c->iso2,
                'phone_code' => $c->phone_code,
                'flag' => $c->flag,
            ]);

        return response()->json(['success' => true, 'data' => ['countries' => $countries]]);
    }

    /**
     * GET /api/v2/locations/governorates?country_id=
     *
     * country_id is required rather than defaulting to Egypt: a silent default
     * returns a plausible-looking list for the wrong country, and the caller
     * cannot tell.
     */
    public function governorates(Request $request)
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        $governorates = Governorate::query()
            ->where('country_id', (int) $data['country_id'])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%");
            }))
            ->orderBy('name_ar')
            ->get(['id', 'country_id', 'name_ar', 'name_en', 'latitude', 'longitude'])
            ->map(fn (Governorate $g) => [
                'id' => (int) $g->id,
                'country_id' => (int) $g->country_id,
                'name_ar' => $g->name_ar,
                'name_en' => $g->name_en,
                'latitude' => $g->latitude !== null ? (float) $g->latitude : null,
                'longitude' => $g->longitude !== null ? (float) $g->longitude : null,
            ]);

        return response()->json(['success' => true, 'data' => ['governorates' => $governorates]]);
    }

    /**
     * GET /api/v2/locations/cities?governorate_id=&q=
     *
     * Capped: one governorate can hold hundreds of cities, and the picker is a
     * search box, not a scroll.
     */
    public function cities(Request $request)
    {
        $data = $request->validate([
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        $cities = City::query()
            ->where('governorate_id', (int) $data['governorate_id'])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%");
            }))
            ->orderBy('name_ar')
            ->limit(300)
            ->get(['id', 'governorate_id', 'name_ar', 'name_en', 'latitude', 'longitude'])
            ->map(fn (City $c) => [
                'id' => (int) $c->id,
                'governorate_id' => (int) $c->governorate_id,
                'name_ar' => $c->name_ar,
                'name_en' => $c->name_en,
                'latitude' => $c->latitude !== null ? (float) $c->latitude : null,
                'longitude' => $c->longitude !== null ? (float) $c->longitude : null,
            ]);

        return response()->json(['success' => true, 'data' => ['cities' => $cities]]);
    }

    /**
     * GET /api/v2/locations/nearest?lat=&lng=
     *
     * "Use my location": the device's GPS gives lat/lng, this resolves it to our
     * own city — no map provider or third-party geocoder is involved, the answer
     * comes entirely from the `cities` table.
     *
     * A bounding box (~55 km each way) pre-filters on the indexed lat/lng before
     * the exact Haversine sort, then a hard distance cap decides the match: a
     * point far out at sea or in a neighbouring country must return "no confident
     * match" rather than the least-distant city hundreds of km away. On no match
     * `data.match` is null and the app falls back to the manual pickers.
     */
    public function nearest(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        // Widest the answer is allowed to be from the pin before we call it
        // unconfident. A city index is coarse, so this is generous, not tight.
        $maxKm = (float) config('bim.location.nearest_max_km', 60);

        // Degree half-widths for the pre-filter box. Longitude degrees shrink
        // toward the poles, so scale by cos(lat); clamp near the poles so the
        // divisor never collapses to zero.
        $latPad = rad2deg($maxKm / 6371);
        $lngPad = rad2deg($maxKm / 6371 / max(0.01, cos(deg2rad($lat))));

        $city = City::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latPad, $lat + $latPad])
            ->whereBetween('longitude', [$lng - $lngPad, $lng + $lngPad])
            ->selectRaw(
                '*, (6371 * acos(LEAST(1, GREATEST(-1,'
                . ' cos(radians(?)) * cos(radians(latitude))'
                . ' * cos(radians(longitude) - radians(?))'
                . ' + sin(radians(?)) * sin(radians(latitude))'
                . ')))) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->orderBy('distance_km')
            ->with('governorate:id,country_id,name_ar,name_en')
            ->first();

        if (! $city || (float) $city->distance_km > $maxKm) {
            return response()->json(['success' => true, 'data' => ['match' => null]]);
        }

        $governorate = $city->governorate;

        return response()->json(['success' => true, 'data' => ['match' => [
            'city' => [
                'id' => (int) $city->id,
                'governorate_id' => (int) $city->governorate_id,
                'name_ar' => $city->name_ar,
                'name_en' => $city->name_en,
                'latitude' => (float) $city->latitude,
                'longitude' => (float) $city->longitude,
            ],
            'governorate' => $governorate ? [
                'id' => (int) $governorate->id,
                'country_id' => (int) $governorate->country_id,
                'name_ar' => $governorate->name_ar,
                'name_en' => $governorate->name_en,
            ] : null,
            'country_id' => $governorate ? (int) $governorate->country_id : null,
            'distance_km' => round((float) $city->distance_km, 2),
        ]]]);
    }

    /**
     * GET /api/v2/locations/cities/search?q=&governorate_id=
     *
     * Type-ahead across every governorate, for "I know the town, not the
     * governorate". Returns the governorate on each row so the form can fill in
     * the parent from the child.
     */
    public function searchCities(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
        ]);

        $q = trim($data['q']);

        $cities = City::query()
            ->with('governorate:id,country_id,name_ar,name_en')
            ->where(function ($w) use ($q) {
                $w->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%");
            })
            ->when(! empty($data['governorate_id']), fn ($query) => $query->where('governorate_id', (int) $data['governorate_id']))
            ->orderBy('name_ar')
            ->limit(30)
            ->get(['id', 'governorate_id', 'name_ar', 'name_en', 'latitude', 'longitude'])
            ->map(fn (City $c) => [
                'id' => (int) $c->id,
                'name_ar' => $c->name_ar,
                'name_en' => $c->name_en,
                'latitude' => $c->latitude !== null ? (float) $c->latitude : null,
                'longitude' => $c->longitude !== null ? (float) $c->longitude : null,
                'governorate' => $c->governorate ? [
                    'id' => (int) $c->governorate->id,
                    'country_id' => (int) $c->governorate->country_id,
                    'name_ar' => $c->governorate->name_ar,
                    'name_en' => $c->governorate->name_en,
                ] : null,
            ]);

        return response()->json(['success' => true, 'data' => ['cities' => $cities]]);
    }
}
