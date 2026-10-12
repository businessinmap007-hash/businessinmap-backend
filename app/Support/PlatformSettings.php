<?php

namespace App\Support;

/**
 * Platform-wide settings an admin may change without a redeploy — today, the platform's timezone.
 *
 * They live in a small JSON file (storage/app/platform-settings.json), not in the database, because they are read at
 * the very start of a request — before the database connection exists and before its session clock is pinned (the
 * session's offset is derived FROM the timezone). The file is not in git, so a deploy never overwrites a value an admin
 * chose; without one the config/env baseline applies.
 */
final class PlatformSettings
{
    private static ?array $cache = null;

    public static function path(): string
    {
        return storage_path('app/platform-settings.json');
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$cache === null) {
            $path = self::path();
            $raw = is_file($path) ? @file_get_contents($path) : false;
            $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : [];

            self::$cache = is_array($data) ? $data : [];
        }

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** @param  array<string,mixed>  $values */
    public static function put(array $values): void
    {
        $merged = array_merge(self::all(), $values);
        $dir = dirname(self::path());

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents(self::path(), json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        self::$cache = $merged;
    }

    /** Forget what was read (tests, and after the file changed under us). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** The timezone an admin chose, when it is a real one; null = use the config baseline. */
    public static function timezone(): ?string
    {
        $zone = self::get('timezone');

        return is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : null;
    }

    /**
     * Make the chosen timezone the platform's: PHP's default, `app.timezone`, and the zone a business without one of its
     * own is judged in. Called once, early in boot.
     */
    public static function applyTimezone(): void
    {
        $zone = self::timezone();

        if ($zone === null) {
            return;
        }

        config(['app.timezone' => $zone, 'app.business_timezone' => $zone]);
        date_default_timezone_set($zone);
    }
}
