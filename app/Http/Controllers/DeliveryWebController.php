<?php

namespace App\Http\Controllers;

use App\Support\QrSvg;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Web scan pages for the delivery loop. /dp/{token} = the driver confirms pickup
 * from the restaurant (stage 1) then shows the delivery QR; /dd/{token} = the
 * customer confirms receipt (stage 2). Both drive the v2 API over fetch with a
 * sanctum token from localStorage. See DeliveryDispatchService.
 */
class DeliveryWebController extends Controller
{
    /** GET /dp/{token} — driver's pickup-confirmation page. */
    public function pickup(string $token): View
    {
        return view('delivery.pickup', ['token' => $token]);
    }

    /** GET /dd/{token} — customer's delivery-confirmation page. */
    public function deliver(string $token): View
    {
        return view('delivery.deliver', ['token' => $token]);
    }

    /** GET /dp/{token}/qr — QR the restaurant shows the driver. */
    public function pickupQr(Request $request, string $token): Response
    {
        return QrSvg::response(QrSvg::absolute($request, route('delivery.pickup.web', ['token' => $token], false)));
    }

    /** GET /dd/{token}/qr — QR the driver shows the customer. */
    public function deliverQr(Request $request, string $token): Response
    {
        return QrSvg::response(QrSvg::absolute($request, route('delivery.deliver.web', ['token' => $token], false)));
    }
}
