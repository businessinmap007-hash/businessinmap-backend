<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin panel's chosen language, held in the session.
 *
 * The panel lives under `/admin`, not under an `/ar`|`/en` URL segment, so
 * AppServiceProvider::configureLocale can't derive its locale from the path —
 * it always lands on the default. This middleware lets a signed-in admin pick a
 * language (via LocaleController), remembered in the session across requests.
 *
 * Sits in the `web` group after StartSession (the session must be booted). It
 * only acts when `panel_locale` is set and valid, so it is a no-op for every
 * other web surface — nothing else writes that key.
 */
class SetPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('panel_locale');

        if (is_string($locale) && in_array($locale, config('app.supported_locales', ['ar', 'en']), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
