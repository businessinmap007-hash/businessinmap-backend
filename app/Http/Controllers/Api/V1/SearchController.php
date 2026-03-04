<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Helpers\ArabicNormalizer;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search locations (cities / governorates)
     */
    public function locations(Request $request)
    {
        $request->validate([
            'q'    => 'required|string|min:1',
            'type' => 'nullable|in:city,governorate',
        ]);

        $query = ArabicNormalizer::normalize($request->q);
        $type  = $request->get('type', 'city'); // افتراضي: مدينة
        $lang  = $request->get('lang', 'ar');

        $nameColumn = $lang === 'en' ? 'name_en' : 'name_ar';

        $results = Location::where('type', $type)
            ->whereRaw(
                "REPLACE(REPLACE({$nameColumn},'ة','ه'),'ى','ي') LIKE ?",
                ["%{$query}%"]
            )
            ->select('id', "{$nameColumn} as name")
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $results,
        ]);
    }
}
