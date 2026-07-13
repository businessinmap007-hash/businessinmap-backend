<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\QrSvg;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public storefront landing (BIM-13.4). A permanent business-profile QR (sticker,
 * card, flyer) points at /b/{business}; scanning it opens the business's public
 * page. Public data, so it is server-rendered directly — no auth/token flow.
 */
class BusinessProfileWebController extends Controller
{
    /** GET /b/{business} — the public storefront page. */
    public function show(int $business): View
    {
        $biz = User::query()->where('type', 'business')->findOrFail($business);

        return view('business-profile', ['biz' => $biz]);
    }

    /** GET /b/{business}/qr — SVG QR encoding the storefront URL. */
    public function qr(Request $request, int $business): Response
    {
        abort_unless(
            User::query()->where('type', 'business')->whereKey($business)->exists(),
            404
        );

        return QrSvg::response(QrSvg::absolute($request, route('storefront.show', ['business' => $business], false)));
    }
}
