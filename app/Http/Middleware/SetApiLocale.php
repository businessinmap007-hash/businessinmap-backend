<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language for the API from the client's request.
 *
 * The web app derives its locale from a `/ar` or `/en` URL segment
 * (AppServiceProvider::configureLocale), but mobile clients hit `/api/...`
 * with no such segment, so every API response used to fall back to Arabic
 * regardless of the device language. This middleware gives the API its own
 * signal. It runs after the service provider's default is set and overrides it.
 *
 * Precedence, most explicit first:
 *   1. An explicit override — `?lang=` / `?locale=` query param, or `X-Locale`
 *      header. Lets a client force a language for one call or ignore the device.
 *   2. The standard `Accept-Language` header, honouring its q-weights.
 *   3. The configured default (first entry of app.supported_locales, 'ar').
 *
 * The chosen locale is echoed in the `Content-Language` response header so the
 * client (and our tests) can confirm what the server actually served.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolve(Request $request): string
    {
        $supported = config('app.supported_locales', ['ar', 'en']);
        $default = $supported[0] ?? 'ar';

        // 1. Explicit override.
        $explicit = $request->query('lang')
            ?? $request->query('locale')
            ?? $request->header('X-Locale');

        if (is_string($explicit)) {
            $explicit = strtolower(substr($explicit, 0, 2));
            if (in_array($explicit, $supported, true)) {
                return $explicit;
            }
        }

        // 2. Accept-Language. getPreferredLanguage() returns the best-weighted
        //    match, or the first supported locale when nothing matches — which
        //    is already our default, so an unmatched header lands on the default
        //    without a special case. Only consult it when the header is present.
        if ($request->headers->has('Accept-Language')) {
            $preferred = $request->getPreferredLanguage($supported);
            if (is_string($preferred) && in_array($preferred, $supported, true)) {
                return $preferred;
            }
        }

        // 3. Default.
        return $default;
    }
}
