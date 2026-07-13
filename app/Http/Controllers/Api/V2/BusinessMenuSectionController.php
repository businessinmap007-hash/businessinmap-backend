<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\MenuSectionResource;
use App\Models\MenuSection;
use Illuminate\Http\Request;

/**
 * v2 business menu-section management (mirrors the web Business\MenuSection
 * controller). Sections group menu items; scoped to business_id = auth user.
 */
final class BusinessMenuSectionController extends Controller
{
    /** GET /api/v2/business/menu/sections */
    public function index(Request $request)
    {
        $sections = MenuSection::query()
            ->where('business_id', $this->businessId($request))
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get();

        return MenuSectionResource::collection($sections)->additional(['success' => true]);
    }

    /** POST /api/v2/business/menu/sections */
    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $section = MenuSection::create($this->validated($request) + ['business_id' => $businessId]);

        return (new MenuSectionResource($section))->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/menu/sections/{section} */
    public function update(Request $request, int $section)
    {
        $model = $this->owned($request, $section);
        $model->update($this->validated($request));

        return (new MenuSectionResource($model->fresh()))->additional(['success' => true]);
    }

    /** DELETE /api/v2/business/menu/sections/{section} */
    public function destroy(Request $request, int $section)
    {
        $this->owned($request, $section)->delete();

        return response()->json(['success' => true]);
    }

    private function businessId(Request $request): int
    {
        $user = $request->user();
        if (! $user->isBusiness()) {
            abort(403, 'إدارة المنيو متاحة لحسابات الأعمال فقط.');
        }

        return (int) $user->id;
    }

    private function owned(Request $request, int $sectionId): MenuSection
    {
        return MenuSection::query()
            ->where('business_id', $this->businessId($request))
            ->findOrFail($sectionId);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
