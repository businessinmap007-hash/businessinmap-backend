<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * «امنع الطلب حتى يختار» — an order is placed only under a delivery/pickup method the store ticked in its
     * profile. A fixture business that a test orders from has "answered" the two ordinary ones (rolled back with
     * the test's transaction).
     *
     * @param  \App\Models\User|int  $business
     */
    protected function offerDelivery($business, array $names = ['توصيل طلبات', 'استلام من المكان']): void
    {
        $id = is_object($business) ? (int) $business->id : (int) $business;
        $group = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');

        foreach ($names as $name) {
            $option = (int) DB::table('options')->where('group_id', $group)->where('name_ar', $name)->value('id');
            DB::table('option_user')->insertOrIgnore(['user_id' => $id, 'option_id' => $option]);
        }
    }
}
