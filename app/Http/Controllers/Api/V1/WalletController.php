<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function balance(Request $request)
    {
        $userId = auth()->id();
        $wallet = $this->walletService->getOrCreateWallet($userId);

        return response()->json([
            'balance' => (string)$wallet->balance,
            'locked_balance' => (string)$wallet->locked_balance,
            'total_in' => (string)$wallet->total_in,
            'total_out' => (string)$wallet->total_out,
            'status' => $wallet->status,
        ]);
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:1000',
        ]);

        $tx = $this->walletService->deposit(
            auth()->id(),
            $request->amount,
            $request->note,
            'manual',
            null,
            $request->header('Idempotency-Key') // optional
        );

        return response()->json(['transaction' => $tx]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'pin' => 'required|string',
            'note' => 'nullable|string|max:1000',
        ]);

        $ok = $this->walletService->verifyPin(auth()->id(), $request->pin);
        if (!$ok) {
            return response()->json(['message' => 'Invalid PIN'], 422);
        }

        $tx = $this->walletService->withdraw(
            auth()->id(),
            $request->amount,
            $request->note,
            'manual',
            null,
            $request->header('Idempotency-Key') // optional
        );

        return response()->json(['transaction' => $tx]);
    }

    public function setPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $this->walletService->setPin(auth()->id(), $request->pin);
        return response()->json(['message' => 'PIN set successfully']);
    }

    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $ok = $this->walletService->verifyPin(auth()->id(), $request->pin);
        return response()->json(['valid' => $ok]);
    }
}
