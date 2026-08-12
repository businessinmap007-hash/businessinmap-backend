<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\Request;

/**
 * The shared medicine dictionary: doctors type a drug and pick from what has
 * been written before, and add a new one when it isn't there yet. Writing is
 * for doctors (clinic/business accounts) only; the dictionary is global so every
 * doctor sees every entry.
 */
class MedicineController extends Controller
{
    /** GET /api/v2/medicines?q= — typeahead over the dictionary (most-used first). */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        // One definition of the search, shared with the admin preview — see
        // Medicine::scopeSearch. Prefix-only used to be the rule, and it hid
        // most of a 25,000-row register: the name carries the strength and the
        // pack («AUGMENTIN 1 GM 14 F.C.TABS.»), so a doctor reaching for a dose
        // types from the middle of it.
        $rows = Medicine::query()
            ->search($q)
            ->limit((int) min(max((int) $request->get('limit', 20), 1), 50))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (Medicine $m) => $this->serialize($m))->values(),
        ]);
    }

    /** POST /api/v2/medicines — a doctor adds a new drug (name + strength). */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('إضافة الأدوية متاحة للأطباء فقط.'),
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'strength' => ['nullable', 'string', 'max:120'],
        ]);

        $medicine = Medicine::remember($data['name'], $data['strength'] ?? null, (int) $user->id);

        return response()->json([
            'success' => true,
            'message' => __('تمت إضافة الدواء.'),
            'data' => $this->serialize($medicine),
        ], 201);
    }

    /**
     * `name_ar` is deliberately absent.
     *
     * It is a phonetic transliteration the register ships as a search alias,
     * not a registered Arabic brand — so it may be MATCHED on and must never
     * reach a client that could render it as the drug's name or print it on a
     * prescription. Serialising it would be the first step to exactly that.
     */
    private function serialize(Medicine $m): array
    {
        return [
            'id' => (int) $m->id,
            'name' => $m->name,
            'strength' => $m->strength,
            // What the doctor was taught to think in, and what tells two
            // near-identical brand names apart in a list of twenty.
            'scientific_name' => $m->scientific_name,
            'manufacturer' => $m->manufacturer,
            'drug_class' => $m->drug_class,
            'route' => $m->route,
            // Dated, because the register's own disclaimer says prices change
            // constantly and an undated price is a claim nobody can check.
            'price_egp' => $m->price_egp !== null ? (float) $m->price_egp : null,
            'price_captured_at' => optional($m->price_captured_at)->toDateString(),
            'uses_count' => (int) $m->uses_count,
        ];
    }
}
