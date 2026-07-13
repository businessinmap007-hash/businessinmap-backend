<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\WalletTransactionResource;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * v2 wallet — read access to the authenticated user's balance and ledger
 * (replaces the legacy v1 WalletController read surface). Money-moving
 * operations (deposit/withdraw/transfer/hold) stay in WalletService and are
 * not exposed here; this is the app's balance + history view.
 */
final class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /** GET /api/v2/wallet — balance summary. */
    public function show(Request $request)
    {
        $wallet = $this->wallet->getOrCreateWallet((int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'balance' => (float) $wallet->balance,
            'locked_balance' => (float) $wallet->locked_balance,
            'available_balance' => (float) $wallet->balance,
            'total_in' => (float) $wallet->total_in,
            'total_out' => (float) $wallet->total_out,
            'status' => (string) $wallet->status,
            'last_activity_at' => optional($wallet->last_activity_at)->toIso8601String(),
        ]]);
    }

    /** GET /api/v2/wallet/transactions — paginated ledger, newest first. */
    public function transactions(Request $request)
    {
        $data = $request->validate([
            'direction' => ['nullable', Rule::in([WalletTransaction::DIRECTION_IN, WalletTransaction::DIRECTION_OUT])],
            'type' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $wallet = $this->wallet->getOrCreateWallet((int) $request->user()->id);

        $rows = $wallet->transactions()
            ->when($data['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($data['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return WalletTransactionResource::collection($rows)->additional(['success' => true]);
    }
}
