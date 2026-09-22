<?php

namespace App\Support;

/**
 * What ONE party of a booking may see of ServiceExecutionEngine::financialPreview().
 *
 * The engine's preview is the whole two-sided picture — both wallets' balances,
 * both guarantee checks, both parties' required amounts. That is right for the
 * engine and for staff; it is not something to hand the other side of the deal:
 * a customer must not be able to read a business's wallet balance, nor the
 * business a customer's. Each party gets its OWN money, plus a single yes/no on
 * whether the counterparty is ready (needed to know why a booking is waiting).
 */
final class BookingFinancialView
{
    public const CLIENT = 'client';

    public const BUSINESS = 'business';

    /**
     * @param  array<string,mixed>  $preview  the engine's raw preview
     * @return array<string,mixed>
     */
    public static function forParty(array $preview, string $side): array
    {
        $other = $side === self::CLIENT ? self::BUSINESS : self::CLIENT;
        $isClient = $side === self::CLIENT;

        $deposit = (array) ($preview['deposit'] ?? []);
        $fees = (array) ($preview['fees'] ?? []);
        $mine = (array) ($preview[$side] ?? []);
        $theirs = (array) ($preview[$other] ?? []);
        $wallet = (array) ($mine['wallet'] ?? []);

        $myLines = collect((array) ($fees['lines'] ?? []))
            ->filter(fn ($line) => ($line['payer'] ?? null) === $side)
            ->map(fn ($line) => [
                'amount' => round((float) ($line['amount'] ?? 0), 2),
                'amount_before_promotion' => $line['amount_before_promotion'] ?? null,
                'currency' => $line['currency'] ?? null,
            ])
            ->values()
            ->all();

        $promotions = collect((array) ($fees['active_promotions'] ?? []))
            ->filter(fn ($p) => ($p['payer'] ?? null) === $side)
            ->map(fn ($p) => ['name' => $p['name'] ?? null, 'message' => $p['message'] ?? null])
            ->values()
            ->all();

        return [
            'side' => $side,
            'ok' => (bool) ($preview['ok'] ?? false),
            'booking_id' => $preview['booking_id'] ?? null,
            'status' => $preview['status'] ?? null,

            'deposit' => [
                'required' => (bool) ($deposit['required'] ?? false),
                'already_frozen' => (bool) ($deposit['already_frozen'] ?? false),
                'my_required' => (float) ($deposit[$side . '_required'] ?? 0),
                'my_wallet_required' => (float) ($deposit[$side . '_wallet_required'] ?? 0),
                'covered_by_guarantee' => (bool) ($deposit[$side . '_guarantee_covered'] ?? false),
                // Only the client's own deposit is set against friend guarantors.
                'guarantee_applied' => $isClient ? (float) ($deposit['guarantee_applied'] ?? 0) : 0.0,
            ],

            'fees' => [
                'my_required' => (float) ($fees[$side . '_required'] ?? 0),
                'lines' => $myLines,
                'promotions' => $promotions,
                'non_refundable_after_in_progress' => (bool) ($fees['non_refundable_after_in_progress'] ?? true),
            ],

            'me' => [
                'balance' => (float) ($wallet['balance'] ?? 0),
                'wallet_active' => (bool) ($wallet['active'] ?? false),
                'required_total' => (float) ($mine['required_total'] ?? 0),
                'ready' => (bool) ($mine['ready'] ?? false),
                // How to close a shortfall — the client's only.
                'options' => $isClient ? ($mine['options'] ?? []) : [],
            ],

            'counterpart_ready' => (bool) ($theirs['ready'] ?? false),
            'messages' => array_values((array) ($preview['messages_by_side'][$side] ?? [])),
        ];
    }
}
