<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\WalletTransactionResource;
use App\Models\WalletPin;
use App\Models\WalletTransaction;
use App\Services\AccountDeletionService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 wallet — the authenticated user's balance, ledger, money movements, and
 * PIN (replaces the legacy v1 WalletController + WalletPinController). All
 * balance changes go through the mature WalletService (lockForUpdate + ledger
 * + idempotency). Withdraw and transfer are PIN-gated.
 */
final class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly AccountDeletionService $deletion,
    ) {
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

    // ─────────────────────────── Money movements ───────────────────────────

    /** POST /api/v2/wallet/deposit — top up own wallet. */
    public function deposit(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $tx = $this->wallet->deposit(
            (int) $request->user()->id,
            $data['amount'],
            $data['note'] ?? null,
            'manual',
            null,
            $request->header('Idempotency-Key')
        );

        return response()->json(['success' => true, 'data' => new WalletTransactionResource($tx)], 201);
    }

    /** POST /api/v2/wallet/withdraw — PIN-gated withdrawal from own wallet. */
    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'pin' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->assertPin((int) $request->user()->id, $data['pin']);

        $tx = $this->wallet->withdraw(
            (int) $request->user()->id,
            $data['amount'],
            $data['note'] ?? null,
            'manual',
            null,
            $request->header('Idempotency-Key')
        );

        return response()->json(['success' => true, 'data' => new WalletTransactionResource($tx)], 201);
    }

    /** POST /api/v2/wallet/transfer — PIN-gated transfer to another user. */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'pin' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $fromId = (int) $request->user()->id;
        if ((int) $data['to_user_id'] === $fromId) {
            throw ValidationException::withMessages(['to_user_id' => [__('لا يمكنك التحويل إلى نفسك.')]]);
        }

        $this->assertPin($fromId, $data['pin']);

        // A transfer out is how money leaves an account for good, so it waits
        // for the cooldown after the last operation or dispute — otherwise a
        // user could trade, drain the wallet and vanish before the other side
        // notices anything was wrong.
        $gate = $this->deletion->balanceTransferGate($request->user());
        if (! $gate['allowed']) {
            throw ValidationException::withMessages(['amount' => [$gate['reason']]]);
        }

        $result = $this->wallet->transfer(
            $fromId,
            (int) $data['to_user_id'],
            $data['amount'],
            'transfer',
            (string) $fromId . '-' . Str::uuid()->toString(),
            $data['note'] ?? null,
            $request->header('Idempotency-Key')
        );

        return response()->json(['success' => true, 'data' => [
            'out' => new WalletTransactionResource($result['out']),
            'in' => isset($result['in']) && $result['in'] ? new WalletTransactionResource($result['in']) : null,
        ]], 201);
    }

    // ─────────────────────────── PIN ───────────────────────────

    /** GET /api/v2/wallet/pin — whether a wallet PIN is set. */
    public function pinStatus(Request $request)
    {
        return response()->json(['success' => true, 'data' => [
            'is_set' => WalletPin::query()->where('user_id', (int) $request->user()->id)->exists(),
        ]]);
    }

    /**
     * POST /api/v2/wallet/pin — set or change the wallet PIN. Changing an
     * existing PIN requires the current one.
     */
    public function setPin(Request $request)
    {
        $userId = (int) $request->user()->id;

        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
            'current_pin' => ['nullable', 'string'],
        ]);

        if (WalletPin::query()->where('user_id', $userId)->exists()) {
            if (empty($data['current_pin']) || ! $this->wallet->verifyPin($userId, $data['current_pin'])) {
                throw ValidationException::withMessages(['current_pin' => [__('الرمز الحالي غير صحيح.')]]);
            }
        }

        $this->wallet->setPin($userId, $data['pin']);

        return response()->json(['success' => true]);
    }

    /** POST /api/v2/wallet/pin/verify — check a PIN without spending. */
    public function verifyPin(Request $request)
    {
        $data = $request->validate(['pin' => ['required', 'string']]);
        $valid = $this->wallet->verifyPin((int) $request->user()->id, $data['pin']);

        return response()->json(['success' => true, 'data' => ['valid' => $valid]]);
    }

    /** Verify the PIN for a spending action, or 422. */
    private function assertPin(int $userId, string $pin): void
    {
        if (! $this->wallet->verifyPin($userId, $pin)) {
            throw ValidationException::withMessages(['pin' => [__('رمز المحفظة غير صحيح.')]]);
        }
    }
}
