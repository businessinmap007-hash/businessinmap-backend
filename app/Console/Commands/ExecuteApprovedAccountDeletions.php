<?php

namespace App\Console\Commands;

use App\Services\Moderation\AccountDeletionPanelService;
use Illuminate\Console\Command;

/** Runs the account deletions the moderators' panel approved and whose grace period (for an objection) is over. */
class ExecuteApprovedAccountDeletions extends Command
{
    protected $signature = 'moderation:execute-deletions';

    protected $description = 'Delete the accounts whose deletion the moderator panel approved and whose grace period passed';

    public function handle(AccountDeletionPanelService $panel): int
    {
        $n = $panel->executeDue();
        $this->info("Executed {$n} approved account deletion(s).");

        return self::SUCCESS;
    }
}
