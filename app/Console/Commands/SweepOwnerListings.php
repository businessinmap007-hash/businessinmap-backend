<?php

namespace App\Console\Commands;

use App\Services\OwnerListings\OwnerListingService;
use Illuminate\Console\Command;

/**
 * Owner listings past their days become expired, and a finished or expired one whose three days are over is deleted with
 * its photos and its chats.
 */
final class SweepOwnerListings extends Command
{
    protected $signature = 'owner-listings:sweep';

    protected $description = 'Expire owner listings past their days and delete the finished ones three days after they ended.';

    public function handle(OwnerListingService $listings): int
    {
        $result = $listings->sweep();
        $this->info("expired {$result['expired']} · deleted {$result['purged']} · announced {$result['announced']}");

        return self::SUCCESS;
    }
}
