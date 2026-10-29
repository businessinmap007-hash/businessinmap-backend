<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Table `business_suite_subscriptions` — see the inventory suite migration (2026_11_23). */
class SuiteSubscription extends Model
{
    protected $table = 'business_suite_subscriptions';

    protected $guarded = ['id'];

    protected $casts = ['paid_until' => 'datetime', 'grace_until' => 'datetime', 'warned_for' => 'datetime'];
}
