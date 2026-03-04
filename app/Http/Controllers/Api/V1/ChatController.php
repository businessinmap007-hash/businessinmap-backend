<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function __construct()
    {
        $language = request()->headers->get('lang') ?: 'ar';
        app()->setLocale($language);
    }

    /**
     * قائمة المحادثات
     */
    public function conversations(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo'])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'status' => 200,
            'message' => 'Conversations list',
            'data' => $conversations,
        ]);
    }

    /**
     * بدء محادثة
     */
    public function startConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $receiverId = (int)$request->receiver_id;

        if ($userId == $receiverId) {
            return response()->json(['status' => 400, 'message' => 'Cannot start conversation with yourself'], 400);
        }

        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $userId,
                'user_two_id' => $receiverId,
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Conversation ready',
            'data' => $conversation,
        ]);
    }

    /**
     * إرسال رسالة
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['status' => 403, 'message' => 'Unauthorized'], 403);
        }

        $receiverId = $conversation->user_one_id == $user->id
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'receiver_id'     => $receiverId,
            'body'            => $request->body,
        ]);

        // 🔔 إرسال الإشعار
        send_notification(
            $receiverId,
            "لديك رسالة جديدة من " . $user->name,
            "new_message",
            [
                "conversation_id" => $conversation->id,
                "message_id" => $message->id,
                "sender_id" => $user->id
            ]
        );

        $conversation->update([
            'last_message'    => $message->body,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'status'  => 200,
            'message' => 'Message sent',
            'data'    => $message,
        ]);
    }

    /**
     * عرض رسائل المحادثة
     */
    public function messages(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['status' => 403, 'message' => 'Unauthorized'], 403);
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 200,
            'message' => 'Messages list',
            'data' => $messages,
        ]);
    }

    /**
     * تعليم الرسائل كمقروءة
     */
    public function markAsRead(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['status' => 403, 'message' => 'Unauthorized'], 403);
        }

        Message::where('conversation_id', $conversation->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 200,
            'message' => 'Messages marked as read',
        ]);
    }

    /**
     * حذف محادثة
     */
    public function deleteConversation(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasUser($user->id)) {
            return response()->json(['status' => 403, 'message' => 'Unauthorized'], 403);
        }

        $conversation->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Conversation deleted',
        ]);
    }
}
