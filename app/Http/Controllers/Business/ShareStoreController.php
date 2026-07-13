<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Share your store" for the business owner (BIM-13.4) — shows the permanent
 * storefront QR + shareable link for the owner's own business, ready to print.
 */
class ShareStoreController extends Controller
{
    public function show(): View
    {
        return view('business.share-store', ['businessId' => (int) Auth::id()]);
    }
}
