<?php

namespace App\Support\Maintenance;

use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * One-off: the row timestamps written before the platform moved onto Cairo's clock.
 *
 * Until 2026-10-12 `app.timezone` was UTC, so every `created_at` / `updated_at` / `deleted_at` Laravel wrote is UTC
 * digits; from then on they are Cairo digits, and the old ones read three (or two, in winter) hours early. This moves
 * exactly those three columns — the framework's own row timestamps, which are ALWAYS instants — by Cairo's offset at the
 * moment each row was written (Egypt observes DST again since 2023, so the offset is looked up from the tz database, not
 * assumed). Every other column is left alone on purpose: starts_at, scheduled_at, date, time… were always the shop's
 * wall-clock digits, and the instants some tables keep elsewhere cannot be told apart by name.
 *
 * Run once, by migration 2026_10_17_100000. The cutoff is the moment the Cairo code started serving (UTC digits): a
 * row at or before it was written in UTC; any row after it was written in Cairo and is not touched.
 */
final class PreCairoTimestamps
{
    /** UTC digits of the moment the Cairo clock went live (the platform-clock commit was served at 03:55:38+03:00). */
    public const CUTOFF = '2026-10-12 00:56:00';

    public const COLUMNS = ['created_at', 'updated_at', 'deleted_at'];

    /** Rows older than this get the winter offset: no data predates it. */
    private const HISTORY_FROM = '2019-01-01 00:00:00';

    /**
     * Cairo's UTC offset (seconds) for a moment given as UTC digits.
     */
    public static function offsetSeconds(string $utcDigits): int
    {
        $ts = (new \DateTimeImmutable($utcDigits, new DateTimeZone('UTC')))->getTimestamp();
        $zone = new DateTimeZone('Africa/Cairo');

        return $zone->getOffset(new \DateTimeImmutable('@' . $ts));
    }

    /**
     * A SQL expression giving the offset (seconds) for the value of `$column`, as a CASE over the exact UTC instants
     * at which Cairo's offset changed. The default is the winter offset.
     */
    public static function caseSql(string $column): string
    {
        $zone = new DateTimeZone('Africa/Cairo');
        $from = (new \DateTimeImmutable(self::HISTORY_FROM, new DateTimeZone('UTC')))->getTimestamp();
        $to = (new \DateTimeImmutable(self::CUTOFF, new DateTimeZone('UTC')))->getTimestamp();

        $transitions = $zone->getTransitions($from, $to);
        $base = $transitions[0]['offset'] ?? 7200;

        $whens = [];
        $count = count($transitions);

        for ($i = 1; $i < $count; $i++) {
            $start = gmdate('Y-m-d H:i:s', $transitions[$i]['ts']);
            $end = $i + 1 < $count ? gmdate('Y-m-d H:i:s', $transitions[$i + 1]['ts']) : null;
            $offset = (int) $transitions[$i]['offset'];

            if ($offset === (int) $base) {
                continue;
            }

            $whens[] = $end
                ? sprintf("WHEN `%s` >= '%s' AND `%s` < '%s' THEN %d", $column, $start, $column, $end, $offset)
                : sprintf("WHEN `%s` >= '%s' THEN %d", $column, $start, $offset);
        }

        return 'CASE ' . implode(' ', $whens) . ' ELSE ' . (int) $base . ' END';
    }

    /**
     * Every base table that has any of the three row-timestamp columns.
     *
     * @return array<string,list<string>> table => columns
     */
    public static function tables(): array
    {
        $rows = DB::select(
            "SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS col
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES tb ON tb.TABLE_SCHEMA = c.TABLE_SCHEMA AND tb.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = DATABASE()
                AND tb.TABLE_TYPE = 'BASE TABLE'
                AND c.COLUMN_NAME IN ('created_at', 'updated_at', 'deleted_at')
                AND c.DATA_TYPE IN ('timestamp', 'datetime')
              ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION"
        );

        $out = [];
        foreach ($rows as $row) {
            $out[$row->t][] = $row->col;
        }

        return $out;
    }

    /**
     * How many rows each table would move — read-only.
     *
     * @return array<string,int>
     */
    public static function plan(): array
    {
        $plan = [];

        foreach (self::tables() as $table => $columns) {
            $where = implode(' OR ', array_map(fn ($c) => "`{$c}` <= ?", $columns));
            $count = (int) DB::selectOne(
                "SELECT COUNT(*) AS n FROM `{$table}` WHERE {$where}",
                array_fill(0, count($columns), self::CUTOFF)
            )->n;

            if ($count > 0) {
                $plan[$table] = $count;
            }
        }

        return $plan;
    }

    /**
     * Move the rows. Each table in one UPDATE that names every timestamp column, so no `ON UPDATE CURRENT_TIMESTAMP`
     * can rewrite `updated_at` behind it.
     *
     * @return array<string,int> table => rows touched
     */
    public static function apply(): array
    {
        $done = [];

        foreach (self::tables() as $table => $columns) {
            $sets = [];
            $bindings = [];

            foreach ($columns as $c) {
                $sets[] = sprintf(
                    '`%1$s` = IF(`%1$s` IS NOT NULL AND `%1$s` <= ?, `%1$s` + INTERVAL (%2$s) SECOND, `%1$s`)',
                    $c,
                    self::caseSql($c)
                );
                $bindings[] = self::CUTOFF;
            }

            $where = implode(' OR ', array_map(fn ($c) => "`{$c}` <= ?", $columns));
            foreach ($columns as $unused) {
                $bindings[] = self::CUTOFF;
            }

            $touched = DB::update("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where}", $bindings);

            if ($touched > 0) {
                $done[$table] = $touched;
            }
        }

        return $done;
    }
}
