<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 read oversight of restaurant tables (business_tables / table QR,
 * BIM-13.3). Tables are created/managed by business owners in their own panel;
 * this is a platform-wide read view.
 */
class BusinessTableAdminController extends Controller
{
    /** GET admin/business-tables */
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $tables = BusinessTable::query()
            ->with('business:id,name')
            ->withCount('orders')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('label', 'like', "%{$q}%")
                    ->orWhereHas('business', fn ($b) => $b->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.tables.index', ['tables' => $tables, 'q' => $q]);
    }
}
