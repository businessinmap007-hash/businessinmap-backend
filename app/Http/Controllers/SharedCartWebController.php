<?php

namespace App\Http\Controllers;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The web surface for a shared (group) cart. Share links/QRs point here; the
 * pages are self-contained standalone shells (their own minimal RTL layout, not
 * the legacy customer shell) that drive the v2 sanctum API over fetch. Auth is a
 * sanctum token the pages read from localStorage. See SharedCartController +
 * CustomerCartService.
 */
class SharedCartWebController extends Controller
{
    /**
     * Join/view page for a share token. POST /api/v2/cart/join/{token} both
     * joins (idempotent) and returns the presented cart, so one call renders it.
     */
    public function join(string $token): View
    {
        return view('cart.shared-join', ['token' => $token]);
    }

    /**
     * Host share page for a business: opens the caller's cart for sharing
     * (POST /api/v2/cart/{business}/share) and shows the join link + its QR.
     */
    public function share(int $business): View
    {
        return view('cart.shared-host', ['businessId' => $business]);
    }

    /**
     * The QR image (SVG) for a share token — encodes the absolute join URL, so
     * nothing sensitive rides in a query string. Rendered server-side and reused
     * as the join_url/QR for BIM-13.2.
     */
    public function qr(Request $request, string $token): Response
    {
        // Encode the join URL on the SAME origin the caller is on — the QR image
        // is loaded from this page, so the request host matches what the host is
        // browsing. Building from the request (not route()/APP_URL) keeps the QR
        // correct even when APP_URL is misconfigured. See the relative-URL rule.
        $url = $request->getSchemeAndHttpHost() . route('cart.shared.join', ['token' => $token], false);

        $result = (new Builder(writer: new SvgWriter(), data: $url, size: 260, margin: 12))->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
