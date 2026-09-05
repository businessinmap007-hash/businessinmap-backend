<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sponsor;
use Carbon\Carbon;

class DeleteExpiredSponsors extends Command
{
    protected $signature = 'sponsors:delete-expired';
    protected $description = 'Delete expired sponsors permanently';

    public function handle()
    {
        $count = Sponsor::whereNotNull('expire_at')
            ->where('expire_at', '<', Carbon::now())
            ->delete();

        $this->info("Deleted {$count} expired sponsors.");

        return Command::SUCCESS;
    }
}
