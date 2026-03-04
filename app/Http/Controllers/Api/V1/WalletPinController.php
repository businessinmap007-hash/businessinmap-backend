<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletPinController extends Controller
{
    public function setPin(Request $request, WalletService $wallet)
    {
        $request->validate([
            'pin_code' => 'required|digits:6',
        ]);

        $wallet->updatePin($request->user(), $request->pin_code);

        return response()->json([
            'status' => 200,
            'message' => 'PIN updated successfully',
        ]);
    }

    public function verifyPin(Request $request, WalletService $wallet)
    {
        $request->validate([
            'pin_code' => 'required|digits:6',
        ]);

        if (!$wallet->verifyPin($request->user(), $request->pin_code)) {
            return response()->json(['status' => 400, 'message' => 'Invalid PIN']);
        }

        return response()->json(['status' => 200, 'message' => 'PIN verified']);
    }
}
