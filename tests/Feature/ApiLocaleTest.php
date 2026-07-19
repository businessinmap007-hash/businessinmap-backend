<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Slice B: the API decides its response language from the request, not from a
 * URL segment it never has. Cover for App\Http\Middleware\SetApiLocale.
 *
 * Asserted through the `Content-Language` response header, which the middleware
 * sets to whatever locale it resolved and handed to app()->setLocale(). Any
 * public /api/v2 GET works — the header is attached on the way out regardless
 * of the body.
 */
class ApiLocaleTest extends TestCase
{
    private const ENDPOINT = '/api/v2/locations/countries';

    public function test_it_defaults_to_arabic_when_no_preference_is_expressed(): void
    {
        // Symfony's Request::create() — which the test client uses — always
        // injects a default `Accept-Language: en-us,en;q=0.5`, so a truly
        // header-less request (the real "no preference" case, which resolves to
        // the default via has()==false) cannot be reproduced here. An explicitly
        // empty Accept-Language exercises the same fallback value: no match, so
        // getPreferredLanguage() returns the first supported locale, 'ar'.
        $this->withHeaders(['Accept-Language' => ''])
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertHeader('Content-Language', 'ar');
    }

    public function test_accept_language_english_is_honoured(): void
    {
        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->getJson(self::ENDPOINT)
            ->assertHeader('Content-Language', 'en');
    }

    public function test_accept_language_arabic_dialect_maps_to_arabic(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar-EG,ar;q=0.9,en;q=0.8'])
            ->getJson(self::ENDPOINT)
            ->assertHeader('Content-Language', 'ar');
    }

    public function test_an_unsupported_language_falls_back_to_the_default(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->getJson(self::ENDPOINT)
            ->assertHeader('Content-Language', 'ar');
    }

    public function test_explicit_lang_query_param_overrides_accept_language(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson(self::ENDPOINT.'?lang=en')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_locale_query_param_is_also_accepted(): void
    {
        $this->getJson(self::ENDPOINT.'?locale=en')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_x_locale_header_overrides_accept_language(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar', 'X-Locale' => 'en'])
            ->getJson(self::ENDPOINT)
            ->assertHeader('Content-Language', 'en');
    }

    public function test_a_garbage_override_is_ignored_and_falls_through(): void
    {
        // ?lang=zz is not supported; the English Accept-Language should still win.
        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson(self::ENDPOINT.'?lang=zz')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_the_locale_is_actually_set_on_the_app_during_the_request(): void
    {
        // Prove the middleware drives app()->getLocale(), not just the header.
        $captured = null;
        $this->app['router']->get('/api/v2/__locale_probe', function () use (&$captured) {
            $captured = app()->getLocale();
            return response()->json(['locale' => $captured]);
        })->middleware('api');

        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v2/__locale_probe')
            ->assertOk()
            ->assertJson(['locale' => 'en']);

        $this->assertSame('en', $captured);
    }
}
