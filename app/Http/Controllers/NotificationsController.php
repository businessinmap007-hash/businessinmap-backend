<?php

namespace App\Http\Controllers;

use App\Libraries\Main;
use App\Models\Notification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationsController extends Controller
{
    // جميع الإشعارات
    public function index(Request $request)
    {
        $items = Notification::where('user_id', $request->user()->id)
            ->orderBy('id', 'DESC')
            ->paginate(20);

        return NotificationResource::collection($items);
    }

    // الإشعارات غير المقروءة
    public function unread(Request $request)
    {
        $items = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->orderBy('id', 'DESC')
            ->get();

        return NotificationResource::collection($items);
    }

    // عرض إشعار واحد
    public function show($id)
    {
        $item = Notification::findOrFail($id);
        return new NotificationResource($item);
    }

    // إنشاء إشعار
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'title'           => 'required|string',
            'body'            => 'required|string',
            'notifiable_id'   => 'nullable|integer',
            'notifiable_type' => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();

        $notification = Notification::create($data);

        return new NotificationResource($notification);
    }

    // وضع علامة مقروء
    public function markAsRead($id)
    {
        $item = Notification::findOrFail($id);
        $item->update(['read_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'تم وضع علامة مقروء',
        ]);
    }

    // حذف إشعار
    public function destroy($id)
    {
        Notification::where('id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الإشعار',
        ]);
    }
}
