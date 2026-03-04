<?php

use App\Models\Notification;

if (! function_exists('send_notification')) {

    function send_notification($userId, $title, $type = null, $data = [])
    {
        return Notification::create([
            'user_id'    => $userId,
            'title'      => $title,
            'body'       => isset($data['body']) ? $data['body'] : null,
            'type'       => $type,
            'data'       => $data ? json_encode($data) : null,
            'read_at'    => null,
        ]);
    }
}
