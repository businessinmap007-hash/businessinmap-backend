<?php

namespace Tests\Feature;

use App\Support\Maintenance\PreCairoTimestamps;
use Tests\TestCase;

/**
 * The one-off that moves the row timestamps written on UTC onto Cairo's clock: the offset is Cairo's AT THE MOMENT the
 * row was written (Egypt observes daylight saving again since 2023), never a flat three hours.
 */
class PreCairoTimestampsTest extends TestCase
{
    public function test_the_offset_follows_egypts_daylight_saving_by_the_instant(): void
    {
        // no DST in 2020–2022: +2 all year
        $this->assertSame(7200, PreCairoTimestamps::offsetSeconds('2020-07-15 12:00:00'));
        $this->assertSame(7200, PreCairoTimestamps::offsetSeconds('2022-07-15 12:00:00'));

        // reinstated in 2023: from the last Friday of April to the last Thursday of October
        $this->assertSame(7200, PreCairoTimestamps::offsetSeconds('2023-04-27 21:59:59'));
        $this->assertSame(10800, PreCairoTimestamps::offsetSeconds('2023-04-27 22:00:00'));
        $this->assertSame(10800, PreCairoTimestamps::offsetSeconds('2023-10-26 20:59:59'));
        $this->assertSame(7200, PreCairoTimestamps::offsetSeconds('2023-10-26 21:00:00'));

        // 2026: summer time from 24 Apr; still on in October when the platform switched
        $this->assertSame(7200, PreCairoTimestamps::offsetSeconds('2026-01-10 12:00:00'));
        $this->assertSame(10800, PreCairoTimestamps::offsetSeconds('2026-07-17 00:43:02'));
        $this->assertSame(10800, PreCairoTimestamps::offsetSeconds(PreCairoTimestamps::CUTOFF));
    }

    public function test_the_sql_case_carries_the_same_boundaries(): void
    {
        $sql = PreCairoTimestamps::caseSql('c');

        // winter is the default; each summer is a window between the exact UTC instants Cairo's offset changed
        $this->assertStringContainsString('ELSE 7200 END', $sql);
        $this->assertStringContainsString("WHEN `c` >= '2023-04-27 22:00:00' AND `c` < '2023-10-26 21:00:00' THEN 10800", $sql);
        $this->assertStringContainsString("WHEN `c` >= '2026-04-23 22:00:00' THEN 10800", $sql);

        // nothing before 2023 is summer time
        $this->assertStringNotContainsString("'2022-", $sql);
    }

    public function test_only_the_three_row_timestamp_columns_are_ever_named(): void
    {
        $this->assertSame(['created_at', 'updated_at', 'deleted_at'], PreCairoTimestamps::COLUMNS);

        $tables = PreCairoTimestamps::tables();
        $this->assertArrayHasKey('users', $tables);

        foreach ($tables as $columns) {
            $this->assertSame([], array_diff($columns, PreCairoTimestamps::COLUMNS));
        }

        // wall-clock tables are not moved by their wall-clock columns
        $this->assertNotContains('starts_at', $tables['bookings'] ?? []);
    }
}
