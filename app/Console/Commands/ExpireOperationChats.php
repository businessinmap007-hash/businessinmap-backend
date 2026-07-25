<?php

namespace App\Console\Commands;

use App\Services\OperationChatService;
use Illuminate\Console\Command;

/**
 * Advances the retention state of every operation chat: starts the 7-day clock
 * on a chat whose operation has completed, and locks a chat whose window has
 * passed (making it read-only and listed for deletion in the panel).
 *
 * Retention starts when completion is OBSERVED here rather than from a stored
 * completed_at the operations do not carry; running daily keeps that within a
 * day of the real completion, which is precise enough for a keep-or-delete
 * decision.
 */
class ExpireOperationChats extends Command
{
    protected $signature = 'operation-chats:expire';

    protected $description = 'Start the retention clock on completed operations\' chats and lock expired ones.';

    public function handle(OperationChatService $chats): int
    {
        $result = $chats->sweep();

        $this->info("Operation chats swept: {$result['stamped']} entered retention, {$result['locked']} locked, {$result['purged']} auto-deleted.");

        return self::SUCCESS;
    }
}
