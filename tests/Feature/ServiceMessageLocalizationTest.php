<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * app/Services strings: the user-facing ones are wrapped in __() and carry a
 * translation; the STORED ones deliberately are not.
 *
 * That split is the whole point. A validation message is rendered immediately,
 * in the caller's language, so it must be translated. A notification body or a
 * wallet ledger note is written to a column and read later by someone else —
 * wrapping it would freeze it in the locale of whoever triggered the write,
 * which is the bug BilingualNotificationTest guards from the other side.
 */
class ServiceMessageLocalizationTest extends TestCase
{
    /** Every __() key used in app/Services resolves in BOTH languages. */
    public function test_every_service_key_is_translated_in_both_languages(): void
    {
        $keys = $this->serviceTranslationKeys();

        $this->assertNotEmpty($keys, 'Expected app/Services to use __() keys.');

        $untranslated = [];
        foreach ($keys as $key) {
            // An unmapped key resolves to itself; for an Arabic source that
            // means English readers would silently be served Arabic.
            if (trans($key, [], 'en') === $key) {
                $untranslated[] = $key;
            }
        }

        $this->assertSame([], $untranslated, 'Service keys with no English translation.');
    }

    /**
     * The Arabic side must exist too. fallback_locale is 'en', so a key missing
     * from ar.json resolves — under locale 'ar' — to its ENGLISH value.
     */
    public function test_arabic_never_falls_back_to_english_for_service_keys(): void
    {
        $leaked = [];
        foreach ($this->serviceTranslationKeys() as $key) {
            if (trans($key, [], 'ar') !== $key) {
                $leaked[] = $key;
            }
        }

        $this->assertSame([], $leaked, 'Arabic service keys leaking through the fallback locale.');
    }

    /**
     * Stored content must stay unwrapped. These are the two shapes that end up
     * in a column: notification title/body, and ledger notes.
     */
    public function test_stored_service_strings_are_not_wrapped(): void
    {
        $offenders = [];

        foreach ($this->serviceFiles() as $file) {
            foreach (file($file) as $i => $line) {
                $isStored = preg_match("/'(title|body)_(ar|en)'\s*=>/", $line)
                    || preg_match("/'note'\s*=>/", $line)
                    || preg_match('/\bnote:\s/', $line);

                if ($isStored && str_contains($line, '__(')) {
                    $offenders[] = basename($file) . ':' . ($i + 1) . ' ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, 'Stored strings must not be wrapped in __().');
    }

    /** @return list<string> */
    private function serviceTranslationKeys(): array
    {
        $keys = [];

        foreach ($this->serviceFiles() as $file) {
            preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                // Only Arabic-source keys — those are the ones needing a mapping.
                if (preg_match('/[\x{0600}-\x{06FF}]/u', $key)) {
                    $keys[] = stripcslashes($key);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /** @return list<string> */
    private function serviceFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Services'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
