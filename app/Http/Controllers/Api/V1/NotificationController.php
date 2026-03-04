<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function __construct()
    {
        // اللغة من الهيدر
        $language = request()->header('lang', 'ar');
        app()->setLocale($language);
    }

    /**
     * جميع الإشعارات
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('id', 'DESC')
            ->paginate(20);

        return NotificationResource::collection($notifications)->additional([
            'status' => 200,
            'message' => 'Notifications list'
        ]);
    }

    /**
     * الإشعارات غير المقروءة
     */
    public function unread(Request $request)
    {
        $user = $request->user();

        $data = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->orderBy('id', 'DESC')
            ->paginate(20);

        return NotificationResource::collection($data)->additional([
            'status' => 200,
            'message' => 'Unread notifications'
        ]);
    }

    /**
     * عرض إشعار واحد
     */
    public function show($id, Request $request)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => 404,
                'message' => 'Notification not found'
            ]);
        }

        return (new NotificationResource($notification))->additional([
            'status' => 200,
            'message' => 'Notification details'
        ]);
    }

    /**
     * تعليم إشعار كمقروء
     */
    public function markAsRead($id, Request $request)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => 404,
                'message' => 'Notification not found'
            ]);
        }

        $notification->update([
            'read_at' => Carbon::now()
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Marked as read'
        ]);
    }

    /**
     * حذف إشعار
     */
    public function destroy($id, Request $request)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => 404,
                'message' => 'Notification not found'
            ]);
        }

        $notification->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * إنشاء إشعار يدوي (API)
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'title'    => 'required|string',
            'body'     => 'required|string',
            'type'     => 'nullable|string',
            'data'     => 'nullable|array'
        ]);

        $notification = Notification::create([
            'user_id' => $request->user_id,
            'title'   => $request->title,
            'body'    => $request->body,
            'type'    => $request->type,
            'data'    => json_encode($request->data),
            'read_at' => null
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Notification created',
            'data' => new NotificationResource($notification)
        ]);
    }

    /**
     * عداد غير المقروء
     */
    public function countForUser(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'status' => 200,
            'count'  => $count
        ]);
    }
}
