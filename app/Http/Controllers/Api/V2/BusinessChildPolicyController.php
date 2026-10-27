<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\ChildPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * «سياسة الأطفال»: the hotel writes its age bands («حتى 5 مجانًا، من 6 إلى 11 بنصف سعر الليلة»), the age from which a child takes a
 * place in the room, the age from which a guest is an adult, and whether a child's meals follow the same percentage.
 */
final class BusinessChildPolicyController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/child-policy — null until the hotel writes one */
    public function show()
    {
        return response()->json(['success' => true, 'data' => ['policy' => ChildPolicy::forBusiness($this->businessId())?->payload()]]);
    }

    /** PUT /api/v2/business/child-policy — `{adult_from_age, counts_from_age, meals_follow, bands: [{from, to, percent}]}` */
    public function update(Request $request)
    {
        $data = $request->validate([
            'adult_from_age' => ['required', 'integer', 'min:1', 'max:18'],
            'counts_from_age' => ['required', 'integer', 'min:0', 'max:18'],
            'meals_follow' => ['nullable', 'boolean'],
            'bands' => ['present', 'array', 'max:6'],
            'bands.*.from' => ['required', 'integer', 'min:0', 'max:17'],
            'bands.*.to' => ['required', 'integer', 'min:0', 'max:17'],
            'bands.*.percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $adult = (int) $data['adult_from_age'];
        $bands = collect($data['bands'])->map(fn ($b) => ['from' => (int) $b['from'], 'to' => (int) $b['to'], 'percent' => round((float) $b['percent'], 2)])
            ->sortBy('from')->values();

        // the bands are ages of children: each in order, none overlapping another, all under the adult age
        $last = -1;

        foreach ($bands as $b) {
            if ($b['to'] < $b['from'] || $b['from'] <= $last || $b['to'] >= $adult) {
                throw ValidationException::withMessages(['bands' => __('شرائح الأعمار متداخلة أو تتجاوز سن البالغ.')]);
            }

            $last = $b['to'];
        }

        $policy = ChildPolicy::query()->updateOrCreate(['business_id' => $this->businessId()], [
            'adult_from_age' => $adult,
            'counts_from_age' => min((int) $data['counts_from_age'], $adult),
            'meals_follow' => (bool) ($data['meals_follow'] ?? true),
            'bands' => $bands->all(),
        ]);

        return response()->json(['success' => true, 'data' => ['policy' => $policy->payload()]]);
    }
}
