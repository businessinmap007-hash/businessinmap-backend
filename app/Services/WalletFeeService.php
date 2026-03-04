<?php

namespace App\Services;

use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletFeeService
{
    public function chargeBothSidesToApp(int $clientId, int $businessId, float $fee, string $context, string $refType, int $refId): void
    {
        $fee = round((float)$fee, 2);
        if ($fee <= 0) return;

        $appUserId = (int) env('WALLET_APP_USER_ID', 1);
        if ($appUserId <= 0) {
            throw ValidationException::withMessages(['wallet' => 'WALLET_APP_USER_ID is not set']);
        }

        DB::transaction(function () use ($clientId, $businessId, $appUserId, $fee) {

            $clientWallet   = Wallet::query()->where('user_id', $clientId)->lockForUpdate()->first();
            $businessWallet = Wallet::query()->where('user_id', $businessId)->lockForUpdate()->first();
            $appWallet      = Wallet::query()->where('user_id', $appUserId)->lockForUpdate()->first();

            if (!$clientWallet || !$businessWallet || !$appWallet) {
                throw ValidationException::withMessages(['wallet' => 'Missing wallet(s) for client/business/app']);
            }

            if ((float)$clientWallet->balance < $fee) {
                throw ValidationException::withMessages(['wallet' => 'Client has insufficient balance for fee']);
            }
            if ((float)$businessWallet->balance < $fee) {
                throw ValidationException::withMessages(['wallet' => 'Business has insufficient balance for fee']);
            }

            // debit client
            $clientWallet->balance = number_format(((float)$clientWallet->balance) - $fee, 2, '.', '');
            $clientWallet->total_out = number_format(((float)$clientWallet->total_out) + $fee, 2, '.', '');
            $clientWallet->last_activity_at = now();
            $clientWallet->save();

            // debit business
            $businessWallet->balance = number_format(((float)$businessWallet->balance) - $fee, 2, '.', '');
            $businessWallet->total_out = number_format(((float)$businessWallet->total_out) + $fee, 2, '.', '');
            $businessWallet->last_activity_at = now();
            $businessWallet->save();

            // credit app (fee*2)
            $sum = $fee * 2;
            $appWallet->balance = number_format(((float)$appWallet->balance) + $sum, 2, '.', '');
            $appWallet->total_in = number_format(((float)$appWallet->total_in) + $sum, 2, '.', '');
            $appWallet->last_activity_at = now();
            $appWallet->save();
        });
    }

    public function chargeSplitToApp(
        int $clientId,
        int $businessId,
        float $clientFee,
        float $businessFee,
        string $referenceType,
        string $referenceId,
        ?string $note = null
    ): array {
        $clientFee   = round((float)$clientFee, 2);
        $businessFee = round((float)$businessFee, 2);

        if ($clientFee <= 0 && $businessFee <= 0) {
            return ['client' => null, 'business' => null];
        }

        $appUserId = (int) env('WALLET_APP_USER_ID', 1);
        if ($appUserId <= 0) {
            throw ValidationException::withMessages(['wallet' => 'WALLET_APP_USER_ID is not set']);
        }

        /** @var \App\Services\WalletService $walletService */
        $walletService = app(\App\Services\WalletService::class);

        return DB::transaction(function () use (
            $walletService,
            $clientId, $businessId, $appUserId,
            $clientFee, $businessFee,
            $referenceType, $referenceId, $note
        ) {
            $out = ['client' => null, 'business' => null];

            // خصم من العميل -> app
            if ($clientFee > 0) {
                $out['client'] = $walletService->transfer(
                    $clientId,
                    $appUserId,
                    $clientFee,
                    $referenceType,
                    $referenceId,
                    $note ?: 'Booking fee (client)',
                    "fee:{$referenceType}:{$referenceId}:client",
                    ['fee_side' => 'client']
                );
            }

            // خصم من البزنس -> app
            if ($businessFee > 0) {
                $out['business'] = $walletService->transfer(
                    $businessId,
                    $appUserId,
                    $businessFee,
                    $referenceType,
                    $referenceId,
                    $note ?: 'Booking fee (business)',
                    "fee:{$referenceType}:{$referenceId}:business",
                    ['fee_side' => 'business']
                );
            }

            return $out;
        });
    }
}