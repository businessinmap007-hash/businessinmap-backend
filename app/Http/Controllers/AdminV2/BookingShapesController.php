<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookingShape;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «أشكال الحجز» — the sibling of «أشكال المنيو». A shape is how a booking trade page is drawn for the guest:
 * rooms grouped under their kind with photo and price (like menu sections), what the guest is asked and in what order.
 * The settings are tried live on a phone, saved on the shape, and every trade that stands on it follows.
 *
 * The booking PATTERN (stay / duration / table…) says how one booking works and is never touched here — a shape only
 * decides how it is shown.
 */
class BookingShapesController extends Controller
{
    public function index(Request $request)
    {
        $shapes = BookingShape::query()->orderBy('sort_order')->orderBy('id')->get();
        $current = $shapes->firstWhere('code', (string) $request->get('shape', '')) ?: $shapes->first();

        $trades = $this->bookingTrades();
        $assignment = DB::table('booking_shape_children')->pluck('booking_shape_id', 'child_id');
        $shapeById = $shapes->keyBy('id');

        $assigned = $current
            ? $trades->filter(fn ($t) => (int) ($assignment[$t->id] ?? 0) === (int) $current->id)->values()
            : collect();
        $others = $current
            ? $trades->filter(fn ($t) => (int) ($assignment[$t->id] ?? 0) !== (int) $current->id)->values()
            : collect();

        return view('admin-v2.booking-shapes.index', [
            'shapes' => $shapes,
            'current' => $current,
            'settings' => $current ? $current->resolvedSettings() : BookingShape::defaults(),
            'definitions' => BookingShape::SETTINGS,
            'assigned' => $assigned,
            'others' => $others,
            'assignment' => $assignment,
            'shapeById' => $shapeById,
            'counts' => $assignment->countBy(),
        ]);
    }

    /** POST booking-shapes/{shape}/settings — the closed list of settings, and the shape's own words. */
    public function saveSettings(Request $request, BookingShape $shape): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $shape->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'settings' => BookingShape::sanitize((array) $request->input('settings', [])),
        ]);

        return redirect()->route('admin.booking-shapes.index', ['shape' => $shape->code])->with('success', __('تم حفظ الشكل.'));
    }

    /** POST booking-shapes/{shape}/children — put trades on this shape (each trade stands on exactly one). */
    public function assign(Request $request, BookingShape $shape): RedirectResponse
    {
        $data = $request->validate([
            'child_ids' => ['required', 'array', 'min:1'],
            'child_ids.*' => ['integer'],
        ]);

        $valid = $this->bookingTrades()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (array_unique(array_map('intval', $data['child_ids'])) as $childId) {
            if (! in_array($childId, $valid, true)) {
                continue;
            }

            DB::table('booking_shape_children')->updateOrInsert(
                ['child_id' => $childId],
                ['booking_shape_id' => $shape->id, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return redirect()->route('admin.booking-shapes.index', ['shape' => $shape->code])->with('success', __('تم ربط الأنشطة بالشكل.'));
    }

    /** DELETE booking-shapes/children/{child} — a trade with no shape is drawn the way it always was. */
    public function unassign(int $child): RedirectResponse
    {
        $shapeId = DB::table('booking_shape_children')->where('child_id', $child)->value('booking_shape_id');
        $code = $shapeId ? BookingShape::query()->whereKey($shapeId)->value('code') : null;

        DB::table('booking_shape_children')->where('child_id', $child)->delete();

        return redirect()->route('admin.booking-shapes.index', array_filter(['shape' => $code]))->with('success', __('أُزيل الربط.'));
    }

    /**
     * The trades that are booked at all — the ones the pattern map names — with their name.
     *
     * @return \Illuminate\Support\Collection<int,object{id:int,name_ar:string,pattern:string}>
     */
    private function bookingTrades()
    {
        $patterns = require database_path('seeders/data/booking_patterns.php');
        $names = DB::table('category_children_master')->whereIn('id', array_keys($patterns))->pluck('name_ar', 'id');

        return collect($patterns)->map(fn ($list, $id) => (object) [
            'id' => (int) $id,
            'name_ar' => (string) ($names[$id] ?? ('#' . $id)),
            'pattern' => (string) ($list[0] ?? ''),
        ])->filter(fn ($t) => isset($names[$t->id]))->sortBy('name_ar')->values();
    }
}
