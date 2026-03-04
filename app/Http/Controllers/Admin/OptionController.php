<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OptionController extends Controller
{
    public function index(Request $request)
    {
        $results = Option::orderByDesc('created_at')->get();
        return view('admin.options.index', compact('results'));
    }

    public function create()
    {
        return view('admin.options.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::beginTransaction();
        try {
            Option::create($data);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return returnedResponse(400,__('admin.options.error'), null);
        }

        return returnedResponse(200,__('admin.options.add'), null, route('options.index'));
    }

    public function edit($id)
    {
        $option = Option::findOrFail($id);
        return view('admin.options.edit', compact('option'));
    }

    public function update(Request $request, $id)
    {
        $option = Option::findOrFail($id);
        $data   = $this->validatedData($request, $option->id);

        DB::beginTransaction();
        try {
            $option->update($data);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return returnedResponse(400, __('admin.options.error'), null);
        }

        return returnedResponse(200, __('admin.options.updated'), null, route('options.index'), ['type' => 'update']);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $option = Option::findOrFail($id);
            $option->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return returnedResponse(400, __('admin.options.error'), null);
        }

        return response()->json([
            'status' => true,
            'data' => ['id' => (int) $id],
        ]);
    }

    /**
     * Validation for store/update.
     *
     * - Supports new fields: name_ar / name_en
     * - Backward compatible: if old form sends 'name', it will fill missing name_ar/name_en
     */
    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            // New structure after merge
            'name_ar' => ['nullable', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],

            // Old structure support (if your old form still uses a single input)
            'name'    => ['nullable', 'string', 'max:191'],

            // Keep it nullable for now (because you also have category_option pivot)
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        // Backward compatibility mapping:
        // If the form only sends "name", use it for both languages when missing.
        if (!empty($data['name'])) {
            if (empty($data['name_ar'])) $data['name_ar'] = $data['name'];
            if (empty($data['name_en'])) $data['name_en'] = $data['name'];
        }

        // Enforce that at least one of the language fields exists
        if (empty($data['name_ar']) && empty($data['name_en'])) {
            abort(422, 'name_ar or name_en is required');
        }

        unset($data['name']); // we don't store legacy field

        return $data;
    }
}
