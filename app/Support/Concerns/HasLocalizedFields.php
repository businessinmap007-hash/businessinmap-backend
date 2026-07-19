<?php

namespace App\Support\Concerns;

/**
 * Picks the value of a translated column for the active locale.
 *
 * Localized content lives in sibling columns — `name_ar`/`name_en`,
 * `description_ar`/`description_en`, … — and the API used to hand the client
 * both and let it choose. With the locale now resolved per request (slice B),
 * a model can hand back a single value in the caller's language instead.
 *
 * `loc('name')` returns `name_{locale}`, falling back to Arabic then English so
 * a half-filled row never yields an empty string. The raw `_ar`/`_en` columns
 * stay on the model untouched — edit screens still need both.
 */
trait HasLocalizedFields
{
    public function loc(string $base): ?string
    {
        $locale = app()->getLocale();

        $primary = $this->{$base.'_'.$locale} ?? null;
        if ($primary !== null && $primary !== '') {
            return $primary;
        }

        return ($this->{$base.'_ar'} ?? null) ?: ($this->{$base.'_en'} ?? null) ?: null;
    }
}
