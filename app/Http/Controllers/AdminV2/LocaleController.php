<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Switches the admin panel language. The choice is stored in the session and
 * applied on every later request by SetPanelLocale. Deliberately registered
 * outside the `admin.v2` ability-gated group (like login/logout): changing your
 * own display language needs no permission, and keeping it out of that group
 * spares it the AdminAbilityCoverageTest `can:` requirement.
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
