<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\SetPinRequest;
use App\Http\Requests\Wallet\CheckPinRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected WalletService $wallet;

    public function __construct(WalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * 🔐 تحديد أو تحديث PIN
     */
    public function setPin(SetPinRequest $request)
    {
        $user = $request->user();

        $this->wallet->updatePin($user, $request->pin);

        return response()->json([
            'status'  => 200,
            'message' => 'Wallet PIN updated successfully'
        ]);
    }

    /**
     * 🔐 التحقق من PIN
     */
    public function checkPin(CheckPinRequest $request)
    {
        $user = $request->user();

        if (!$user->pin_code) {
            return response()->json([
                'status'  => 400,
                'message' => 'PIN is not set'
            ], 400);
        }

        if ($this->wallet->verifyPin($user, $request->pin)) {
            return response()->json([
                'status'  => 200,
                'message' => 'PIN is valid'
            ]);
        }

        return response()->json([
            'status'  => 401,
            'message' => 'Invalid PIN'
        ], 401);
    }
}
