<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ThreadMessageAttachment;
use App\Services\Media\ThreadAttachmentStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a conversation evidence file to a PARTY of that conversation.
 *
 * The files live outside the web root, so this is the only way to fetch one,
 * and only a participant of the thread the attachment belongs to may. A
 * stranger gets 404 — never a hint that the file exists.
 */
class ThreadAttachmentController extends Controller
{
    public function show(Request $request, ThreadMessageAttachment $attachment, ThreadAttachmentStorage $storage): BinaryFileResponse
    {
        $attachment->loadMissing('message.thread.participants');

        $thread = $attachment->message?->thread;

        abort_if($thread === null || $thread->participantFor((int) $request->user()->id) === null, 404);

        $full = $storage->absolute($attachment->path);

        abort_if($full === null, 404);

        return response()->file($full, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
        ]);
    }
}
