<?php

namespace App\Console\Commands;

use App\Services\Inventory\SuiteSubscriptionService;
use Illuminate\Console\Command;

class RenewBusinessSuiteSubscriptions extends Command
{
    protected $signature = 'suite:renew-subscriptions';

    protected $description = 'The desktop suite month: remind 3 days before, renew from the wallet, then 7 grace days, then read-only.';

    public function handle(SuiteSubscriptionService $subscriptions): int
    {
        foreach ($subscriptions->runDaily() as $what => $count) {
            $this->info("{$what}: {$count}");
        }

        return self::SUCCESS;
    }
}
