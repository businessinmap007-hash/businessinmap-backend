<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The project is deployed on a server outside Egypt: its MySQL may sit on UTC. The connection must still run on the
 * platform's clock, or NOW() and the CURRENT_TIMESTAMP defaults land three hours away from what Laravel writes.
 */
class DatabaseClockTest extends TestCase
{
    public function test_the_database_session_runs_on_the_platform_clock(): void
    {
        $row = DB::selectOne('SELECT NOW() AS db_now, @@session.time_zone AS zone');

        // the session is pinned to the platform zone's CURRENT offset (+03:00 in summer, +02:00 in winter)
        $this->assertSame(Carbon::now(config('app.timezone'))->format('P'), $row->zone);

        // so the database's own NOW() agrees with PHP's now()
        $this->assertEqualsWithDelta(now()->timestamp, Carbon::parse($row->db_now)->timestamp, 5);
    }
}
