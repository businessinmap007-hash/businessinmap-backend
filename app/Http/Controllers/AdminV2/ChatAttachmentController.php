<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ThreadMessageAttachment;
use App\Services\Media\ThreadAttachmentStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a conversation evidence file to the admin/judge moderation screen.
 *
 * The route is gated on the DISPUTES (judge) ability, so — like the chats hub
 * itself — only someone trusted to rule on a case can open the evidence. The
 * files are private (storage/, not public/), so this authenticated route is
 * the only way in.
 */
class ChatAttachmentController extends Controller
{
    public function show(ThreadMessageAttachment $attachment, ThreadAttachmentStorage $storage): BinaryFileResponse
    {
        $full = $storage->absolute($attachment->path);

        abort_if($full === null, 404);

        return response()->file($full, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
        ]);
    }
}
