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
        return $this->svgQr($this->absolute($request, route('cart.shared.join', ['token' => $token], false)));
    }

    /**
     * Restaurant-table landing (BIM-13.3): a sticker QR opens this page, which
     * scans the table (POST /api/v2/table/{token}/scan) and hands off to the
     * shared-cart management page for the table's open/created dine-in cart.
     */
    public function table(string $token): View
    {
        return view('cart.table', ['token' => $token]);
    }

    /** The QR image (SVG) for a table token — encodes the absolute table URL. */
    public function tableQr(Request $request, string $token): Response
    {
        return $this->svgQr($this->absolute($request, route('table.scan.web', ['token' => $token], false)));
    }

    /**
     * Absolute URL on the caller's OWN origin (request host + a relative path),
     * so a QR always points at the host being browsed even when APP_URL is
     * misconfigured. See the relative-URL rule.
     */
    private function absolute(Request $request, string $relativePath): string
    {
        return $request->getSchemeAndHttpHost() . $relativePath;
    }

    private function svgQr(string $data): Response
    {
        $result = (new Builder(writer: new SvgWriter(), data: $data, size: 260, margin: 12))->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
