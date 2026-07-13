<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The web entry point for a shared (group) cart. A share link/QR points here;
 * the page is a self-contained standalone shell (its own minimal RTL layout,
 * not the legacy customer shell) that drives the v2 sanctum API over fetch:
 * POST /api/v2/cart/join/{token} both joins and returns the presented cart, so
 * one call renders everything. Auth is a sanctum token the page reads from
 * localStorage. See SharedCartController + CustomerCartService.
 */
class SharedCartWebController extends Controller
{
    /** Render the join/view page for a share token (no server auth needed). */
    public function join(string $token): View
    {
        return view('cart.shared-join', ['token' => $token]);
    }
}
