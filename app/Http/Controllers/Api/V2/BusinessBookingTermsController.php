<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessDepositPolicy as Policy;
use App\Services\BookingDepositCalculator;
use App\Services\BookingDepositPolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «شروط الحجز» — how a business secures its bookings, said in plain terms and written onto the ONE deposit-policy row
 * the booking engine already reads (`business_deposit_policies`, scope `business_global`). The merchant never sees the
 * policy's dozen switches:
 *
 *   security_mode  deposit_freeze   — a deposit frozen in the wallet of BOTH parties (the preferred way)
 *                  guarantee_freeze — the customer's guarantee coverage stands for it (cash hold only if it cannot)
 *                  external_transfer— paid by direct transfer outside the app, the business confirms it arrived
 *                                     (the platform carries no responsibility for the transfer)
 *   deposit_percent, deposit_base   — a share of the day's value or of the whole booking
 *   business_counter_percent        — what the business freezes against the customer's hold
 *   guarantee_multiple              — the guarantee may be asked to stand for N times the day's value
 *   forfeit_to_business             — declared up front: a broken booking gives the whole deposit to the business
 *
 * Every booking still waits for the business's approval (accept/reject) — that is not a setting here.
 */
final class BusinessBookingTermsController extends Controller
{
    use ResolvesOwnerCatalog;

    public const MODE_DEPOSIT = 'deposit_freeze';
    public const MODE_GUARANTEE = 'guarantee_freeze';
    public const MODE_EXTERNAL = 'external_transfer';

    /** The booking value the preview uses — one day at 800, the example the owner gave. */
    private const EXAMPLE_VALUE = 800.0;

    /** GET /api/v2/business/booking-terms */
    public function show()
    {
        return response()->json(['success' => true, 'data' => $this->payload($this->row())]);
    }

    /** PUT /api/v2/business/booking-terms */
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'security_mode' => ['required', Rule::in([self::MODE_DEPOSIT, self::MODE_GUARANTEE, self::MODE_EXTERNAL])],
            'deposit_percent' => ['required_if:enabled,1', 'nullable', 'numeric', 'min:1', 'max:' . BookingDepositCalculator::SYSTEM_MAX_PERCENT],
            'deposit_base' => ['nullable', Rule::in([Policy::BASE_FIRST_DAY, Policy::BASE_TOTAL])],
            'business_counter_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'guarantee_multiple' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'forfeit_to_business' => ['nullable', 'boolean'],
            'accept_deposit_as_payment' => ['nullable', 'boolean'],
        ]);

        $mode = $data['security_mode'];
        $percent = (float) ($data['deposit_percent'] ?? 20);

        $row = $this->row() ?? new Policy([
            'business_id' => $this->businessId(),
            'scope_key' => Policy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 100,
            'currency' => 'EGP',
        ]);

        $row->fill([
            'is_enabled' => (bool) $data['enabled'],
            'deposit_type' => Policy::TYPE_PERCENT,
            'deposit_value' => $percent,
            'max_deposit_percent' => max($percent, 20),
            'calculation_base' => $data['deposit_base'] ?? Policy::BASE_FIRST_DAY,

            'deposit_mode' => $mode === self::MODE_EXTERNAL ? Policy::MODE_EXTERNAL_VERIFICATION : Policy::MODE_WALLET_HOLD,
            'wallet_hold_enabled' => $mode !== self::MODE_EXTERNAL,
            'external_verification_enabled' => $mode === self::MODE_EXTERNAL,

            // The business freezes its own share against the customer's — «ديبوزت بزنس وعميل». Not asked when the
            // money moves outside the app.
            'business_counter_hold_enabled' => $mode !== self::MODE_EXTERNAL && (float) ($data['business_counter_percent'] ?? 50) > 0,
            'business_counter_hold_percent' => $mode === self::MODE_EXTERNAL ? 0 : (float) ($data['business_counter_percent'] ?? 50),

            'client_guarantee_strategy' => $mode === self::MODE_GUARANTEE ? Policy::GUARANTEE_GENERAL : Policy::GUARANTEE_PER_OPERATION_HOLD,
            'guarantee_multiple' => $mode === self::MODE_GUARANTEE ? (float) ($data['guarantee_multiple'] ?? 0) : 0,
            'forfeit_to_business' => (bool) ($data['forfeit_to_business'] ?? false),
            // taking the frozen deposit as a payment only exists where a deposit is frozen in the wallet
            'accept_deposit_as_payment' => $mode === self::MODE_DEPOSIT && (bool) ($data['accept_deposit_as_payment'] ?? false),
        ])->save();

        return response()->json(['success' => true, 'data' => $this->payload($row->fresh())]);
    }

    private function row(): ?Policy
    {
        return Policy::query()
            ->where('business_id', $this->businessId())
            ->where('scope_key', Policy::SCOPE_BUSINESS_GLOBAL)
            ->first();
    }

    /** @return array<string,mixed> */
    private function payload(?Policy $row): array
    {
        $mode = self::MODE_DEPOSIT;
        if ($row) {
            if ($row->deposit_mode === Policy::MODE_EXTERNAL_VERIFICATION) {
                $mode = self::MODE_EXTERNAL;
            } elseif (in_array($row->client_guarantee_strategy, [Policy::GUARANTEE_GENERAL, Policy::GUARANTEE_HYBRID], true)) {
                $mode = self::MODE_GUARANTEE;
            }
        }

        $terms = [
            'enabled' => (bool) ($row?->is_enabled),
            'security_mode' => $mode,
            'deposit_percent' => $row ? (float) $row->deposit_value : 20.0,
            'deposit_base' => $row?->calculation_base === Policy::BASE_TOTAL ? Policy::BASE_TOTAL : Policy::BASE_FIRST_DAY,
            'business_counter_percent' => $row ? (float) $row->business_counter_hold_percent : 50.0,
            'guarantee_multiple' => $row ? (float) $row->guarantee_multiple : 0.0,
            'forfeit_to_business' => (bool) ($row?->forfeit_to_business),
            'accept_deposit_as_payment' => (bool) ($row?->accept_deposit_as_payment),
            // every booking waits for the business to accept it — shown, never switched
            'confirmation' => 'merchant_approval',
            'max_percent' => BookingDepositCalculator::SYSTEM_MAX_PERCENT,
            // a more specific row (per service or specialty) would win over this one in the engine
            'has_specific_policies' => Policy::query()
                ->where('business_id', $this->businessId())
                ->where('scope_key', '!=', Policy::SCOPE_BUSINESS_GLOBAL)
                ->where('is_enabled', true)
                ->exists(),
        ];

        $terms['example'] = $row ? $this->example($row) : null;

        return $terms;
    }

    /** What these terms mean for a booking of one day at the example value — the preview the merchant checks. */
    private function example(Policy $row): array
    {
        $policy = app(BookingDepositPolicyResolver::class)->fromBusinessPolicy($row);
        $out = app(BookingDepositCalculator::class)->calculate($policy, [
            'total_amount' => self::EXAMPLE_VALUE,
            'first_day_amount' => self::EXAMPLE_VALUE,
        ]);

        return [
            'booking_value' => self::EXAMPLE_VALUE,
            'deposit' => (float) $out['amount'],
            'customer_hold' => (float) $out['wallet_hold_amount'],
            'business_hold' => (float) $out['business_counter_hold_amount'],
            'external_amount' => (float) $out['external_deposit_amount'],
            'guarantee_required' => (float) $out['client_guarantee_required_amount'],
        ];
    }
}
