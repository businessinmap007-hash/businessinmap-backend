<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function items(Request $request)
    {
        $q = Item::query()
            ->with(['variants' => fn($x) => $x->where('is_active', 1)->orderBy('sort_order'),
                   'extras'   => fn($x) => $x->where('is_active', 1)->orderBy('sort_order')])
            ->where('is_active', 1)
            ->orderBy('sort_order');

        // فلاتر اختيارية (لو موجودة)
        if ($request->filled('business_id')) $q->where('business_id', $request->business_id);
        if ($request->filled('category_id')) $q->where('category_id', $request->category_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($w) use ($s) {
                $w->where('name_ar', 'like', "%$s%")
                  ->orWhere('name_en', 'like', "%$s%");
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $q->paginate(20),
        ]);
    }

    public function show($id)
    {
        $item = Item::with(['variants', 'extras'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }
}
