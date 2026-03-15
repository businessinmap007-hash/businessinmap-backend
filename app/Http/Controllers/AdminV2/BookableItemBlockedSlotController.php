<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use Illuminate\Http\Request;

class BookableItemBlockedSlotController extends Controller
{

    public function index(BookableItem $bookableItem)
    {
        $slots = $bookableItem
            ->blockedSlots()
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('admin-v2.bookable-items.blocked-slots.index',[
            'item'=>$bookableItem,
            'slots'=>$slots
        ]);
    }

    public function create(BookableItem $bookableItem)
    {
        return view('admin-v2.bookable-items.blocked-slots.create',[
            'item'=>$bookableItem
        ]);
    }

    public function store(Request $request,BookableItem $bookableItem)
    {
        $data=$request->validate([
            'starts_at'=>'required|date',
            'ends_at'=>'required|date|after:starts_at',
            'reason'=>'nullable|string|max:255',
            'block_type'=>'nullable|string'
        ]);

        $data['bookable_item_id']=$bookableItem->id;
        $data['created_by']=auth()->id();

        BookableItemBlockedSlot::create($data);

        return redirect()
            ->route('admin.bookable-items.blocked-slots.index',$bookableItem)
            ->with('success','تم إضافة فترة الغلق');
    }

    public function destroy(BookableItem $bookableItem,BookableItemBlockedSlot $slot)
    {
        $slot->delete();

        return back()->with('success','تم حذف الغلق');
    }

}
