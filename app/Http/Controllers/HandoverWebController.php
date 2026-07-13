<?php

namespace App\Http\Controllers;

use App\Support\QrSvg;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Order-handover QR (BIM-13.5). The ready order's QR points at /h/{token}; the
 * other party opens it to confirm the handover via the v2 API over fetch (auth
 * is a sanctum token read from localStorage). See OrderHandoverController.
 */
class HandoverWebController extends Controller
{
    /** GET /h/{token} — scan-to-confirm landing. */
    public function scan(string $token): View
    {
        return view('cart.handover', ['token' => $token]);
    }

    /** GET /h/{token}/qr — SVG QR encoding the handover URL. */
    public function qr(Request $request, string $token): Response
    {
        return QrSvg::response(QrSvg::absolute($request, route('handover.scan.web', ['token' => $token], false)));
    }
}
