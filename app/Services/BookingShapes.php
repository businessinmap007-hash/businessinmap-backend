<?php

namespace App\Services;

use App\Models\BookingShape;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which booking shape a business is drawn with — the shape of its TRADE (child). A trade nobody has assigned a shape
 * to has none: the app then draws what it always drew, so nothing breaks while the admin is still arranging.
 */
final class BookingShapes
{
    public function forChild(int $childId): ?BookingShape
    {
        if ($childId <= 0) {
            return null;
        }

        $shapeId = DB::table('booking_shape_children')->where('child_id', $childId)->value('booking_shape_id');

        return $shapeId ? BookingShape::query()->where('is_active', true)->find((int) $shapeId) : null;
    }

    public function forBusiness(int $businessId): ?BookingShape
    {
        $child = (int) User::query()->where('id', $businessId)->value('category_child_id');

        return $this->forChild($child);
    }

    /** What a client needs to draw the page — the closed settings plus the shape identity. */
    public function payload(?BookingShape $shape): ?array
    {
        if (! $shape) {
            return null;
        }

        return [
            'code' => $shape->code,
            'name' => app()->getLocale() === 'en' ? ($shape->name_en ?: $shape->name_ar) : $shape->name_ar,
            'pattern' => $shape->pattern,
            'settings' => $shape->resolvedSettings(),
        ];
    }
}
