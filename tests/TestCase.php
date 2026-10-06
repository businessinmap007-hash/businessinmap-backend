<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(\Tests\Concerns\AnswersDelivery::class, class_uses_recursive($this), true)) {
            $this->everyBusinessHasAnswered();
        }
    }

    /** One statement: every business ticks the two ordinary delivery/pickup options (see Tests\Concerns\AnswersDelivery). */
    protected function everyBusinessHasAnswered(): void
    {
        $group = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');

        foreach (['توصيل طلبات', 'استلام من المكان'] as $name) {
            $option = (int) DB::table('options')->where('group_id', $group)->where('name_ar', $name)->value('id');
            if ($option > 0) {
                DB::statement('INSERT IGNORE INTO option_user (user_id, option_id) SELECT id, ? FROM users WHERE type = ?', [$option, 'business']);
            }
        }
    }

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
