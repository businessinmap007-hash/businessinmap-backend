<?php

namespace App\Support;

use Closure;

/**
 * A memory for ONE request: what a page asks many times over (the running offers of a hotel, the kind a room belongs to) is read once.
 *
 * It is safe because it forgets: every entry belongs to a family (`promotions`, `item-types`…), a model that writes to that family calls
 * `bump`, and an entry also dies when the minute turns (offers start and end by the clock). A page of forty room types used to ask the
 * database for the same offers forty times.
 */
final class RequestMemo
{
    /** @var array<string,int> */
    private static array $revisions = [];

    /** @var array<string,mixed> */
    private static array $store = [];

    public static function remember(string $family, string $key, Closure $read): mixed
    {
        $full = $family . ':' . (self::$revisions[$family] ?? 0) . ':' . now()->format('YmdHi') . ':' . $key;

        if (! array_key_exists($full, self::$store)) {
            if (count(self::$store) > 2000) {
                self::$store = [];
            }

            self::$store[$full] = $read();
        }

        return self::$store[$full];
    }

    /** Something in this family changed: every entry of it is stale. */
    public static function bump(string $family): void
    {
        self::$revisions[$family] = (self::$revisions[$family] ?? 0) + 1;
    }

    /** Keep a family honest: any save or delete of the model drops its entries. */
    public static function watch(string $modelClass, string $family): void
    {
        $modelClass::saved(fn () => self::bump($family));
        $modelClass::deleted(fn () => self::bump($family));
    }
}
