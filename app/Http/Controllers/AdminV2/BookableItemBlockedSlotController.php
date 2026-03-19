<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookableItemBlockedSlotController extends Controller
{
    public function index(BookableItem $bookableItem)
    {
        $bookableItem->loadMissing([
            'service:id,key,name_ar,name_en',
            'business:id,name,type',
        ]);

        $slots = $bookableItem->blockedSlots()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin-v2.bookable-items.blocked-slots.index', [
            'item' => $bookableItem,
            'slots' => $slots,
        ]);
    }

    public function create(BookableItem $bookableItem)
    {
        $bookableItem->loadMissing([
            'service:id,key,name_ar,name_en',
            'business:id,name,type',
        ]);

        return view('admin-v2.bookable-items.blocked-slots.create', [
            'item' => $bookableItem,
        ]);
    }

    public function store(Request $request, BookableItem $bookableItem)
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'block_type' => [
                'required',
                'string',
                'max:50',
                Rule::in(['manual', 'maintenance', 'holiday', 'admin']),
            ],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable'],
        ], [], [
            'starts_at' => 'بداية الغلق',
            'ends_at' => 'نهاية الغلق',
            'reason' => 'السبب',
            'block_type' => 'نوع الغلق',
            'notes' => 'الملاحظات',
            'is_active' => 'الحالة',
        ]);

        $overlapExists = BookableItemBlockedSlot::query()
            ->where('bookable_item_id', $bookableItem->id)
            ->where('is_active', true)
            ->where(function ($query) use ($data) {
                $query
                    ->whereBetween('starts_at', [$data['starts_at'], $data['ends_at']])
                    ->orWhereBetween('ends_at', [$data['starts_at'], $data['ends_at']])
                    ->orWhere(function ($sub) use ($data) {
                        $sub->where('starts_at', '<=', $data['starts_at'])
                            ->where('ends_at', '>=', $data['ends_at']);
                    });
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'starts_at' => 'يوجد بالفعل غلق نشط متداخل مع هذه الفترة.',
            ]);
        }

        $data['bookable_item_id'] = $bookableItem->id;
        $data['created_by'] = auth()->id();
        $data['is_active'] = $request->boolean('is_active', true);

        BookableItemBlockedSlot::create($data);

        return redirect()
            ->route('admin.bookable-items.blocked-slots.index', $bookableItem)
            ->with('success', 'تم إضافة فترة الغلق بنجاح.');
    }

    public function destroy(BookableItem $bookableItem, BookableItemBlockedSlot $slot)
    {
        if ((int) $slot->bookable_item_id !== (int) $bookableItem->id) {
            throw ValidationException::withMessages([
                'slot' => 'فترة الغلق لا تتبع هذا العنصر.',
            ]);
        }

        $slot->delete();

        return back()->with('success', 'تم حذف فترة الغلق بنجاح.');
    }
 public function edit(BookableItem $bookableItem, BookableItemBlockedSlot $slot)
    {
        abort_unless((int) $slot->bookable_item_id === (int) $bookableItem->id, 404);

        return view('admin-v2.bookable-items.blocked-slots.edit', compact('bookableItem', 'slot'));
    }

    public function update(Request $request, BookableItem $bookableItem, BookableItemBlockedSlot $slot)
    {
        abort_unless((int) $slot->bookable_item_id === (int) $bookableItem->id, 404);

        $data = $request->validate([
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after_or_equal:starts_at'],
            'block_type' => ['required', 'string', 'max:50'],
            'reason'     => ['nullable', 'string', 'max:255'],
            'notes'      => ['nullable', 'string'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $slot->update([
            'block_type' => $data['block_type'],
            'starts_at'  => $data['starts_at'],
            'ends_at'    => $data['ends_at'],
            'reason'     => $data['reason'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'is_active'  => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id])
            ->with('success', 'تم تحديث فترة الغلق بنجاح');
    }
}