<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Switches the business owner panel's language. The choice is stored in the
 * session (`panel_locale`) and applied on every later request by SetPanelLocale
 * — the same key/middleware the admin panel uses, so no new plumbing is needed.
 * Changing your own display language needs no permission.
 */
class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        if (in_array($locale, config('app.supported_locales', ['ar', 'en']), true)) {
            $request->session()->put('panel_locale', $locale);
        }

        return redirect()->back();
    }
}
