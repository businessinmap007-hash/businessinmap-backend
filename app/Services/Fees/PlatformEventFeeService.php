<?php

namespace App\Services\Fees;

use App\Models\PlatformEventCharge;
use App\Models\PlatformEventFee;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * THE core for a fee on an event that has no trade to hang it on (owner, 2026-10-27): the platform names an event, the control
 * panel prices it for each party, and the fee is taken from the wallets of the parties — only when every one of them has
 * the balance for it.
 *
 * It is the same rule delivery and bookings already follow (a fee per party, fixed or a percent, from the panel), for the
 * events that are not a booking: swapping numbers on an owner listing, changing a training or a nutrition program, and
 * whatever comes next — a new event is one entry in {@see self::EVENTS} and one call to {@see self::charge()}.
 */
final class PlatformEventFeeService
{
    public const OWNER_LISTING_CONTACT = 'owner_listing_contact';
    public const TRAINING_PROGRAM_CHANGE = 'training_program_change';
    public const NUTRITION_PROGRAM_CHANGE = 'nutrition_program_change';

    /** A deposit HELD for the life of an owner listing and handed back — priced here, never taken as a fee. */
    public const OWNER_LISTING_DEPOSIT = 'owner_listing_deposit';

    /**
     * event → [Arabic name, English name, what a percent is OF, the parties: role → [Arabic, English]].
     *
     * @var array<string,array{0:string,1:string,2:string,3:array<string,array{0:string,1:string}>}>
     */
    public const EVENTS = [
        self::OWNER_LISTING_CONTACT => ['تبادل الأرقام على إعلان من المالك (عند الاتفاق)', 'Owner listing: swapping numbers once they agree', 'سعر الإعلان', [
            'buyer' => ['المشتري / المستأجر', 'Buyer / renter'],
            'seller' => ['البائع / المؤجِّر', 'Seller / owner'],
        ]],
        self::OWNER_LISTING_DEPOSIT => ['عربون جدية الإعلان من المالك (يُحجز ويُردّ عند البيع أو الإنهاء — لمن لا يملك ضمانًا برونزيًا فأعلى)', 'Owner listing: seriousness deposit (held and handed back on sale / ending — for advertisers without a bronze+ guarantee)', 'لا شيء (مبلغ ثابت؛ المعطَّل = لا شرط)', [
            'owner' => ['المعلِن', 'Advertiser'],
        ]],
        self::TRAINING_PROGRAM_CHANGE => ['البرنامج التدريبي (عند المشاركة مع العميل وعند كل تجديد شهري)', 'Training program (when shared with a client and at every monthly renewal)', 'لا شيء (مبلغ ثابت)', [
            'trainer' => ['المدرب', 'Trainer'],
            'client' => ['المتدرب', 'Trainee'],
        ]],
        self::NUTRITION_PROGRAM_CHANGE => ['برنامج التغذية (عند المشاركة مع العميل وعند كل تجديد شهري)', 'Nutrition program (when shared with a client and at every monthly renewal)', 'لا شيء (مبلغ ثابت)', [
            'trainer' => ['المدرب / الأخصائي', 'Trainer / dietitian'],
            'client' => ['المتدرب', 'Trainee'],
        ]],
    ];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly PlatformTreasuryService $treasury
    ) {}

    /** @return list<string> */
    public static function events(): array
    {
        return array_keys(self::EVENTS);
    }

    /**
     * What each party would pay.
     *
     * @param  array<string,int>  $parties  role → user id
     * @return array{total:float,lines:array<string,array{user_id:int,amount:float}>}
     */
    public function quote(string $event, array $parties, float $base = 0.0): array
    {
        $rows = PlatformEventFee::query()->where('event_key', $event)->get()->keyBy('role');
        $lines = [];
        $total = 0.0;

        foreach ($parties as $role => $userId) {
            $amount = $rows->has($role) ? $rows[$role]->feeFor($base) : 0.0;
            $lines[$role] = ['user_id' => (int) $userId, 'amount' => $amount];
            $total += $amount;
        }

        return ['total' => round($total, 2), 'lines' => $lines];
    }

    /**
     * Every party who owes something must have it. The message names the shortfall for the person acting and only says
     * «the other side» for anyone else, so a balance is never disclosed to the other party.
     *
     * @param  array{total:float,lines:array<string,array{user_id:int,amount:float}>}  $quote
     */
    public function assertCanPay(array $quote, ?int $actingUserId = null, bool $onlyActing = false): void
    {
        foreach ($quote['lines'] as $line) {
            if ($line['amount'] <= 0 || ($onlyActing && $line['user_id'] !== $actingUserId)) {
                continue;
            }

            if ((float) $this->wallets->getOrCreateWallet($line['user_id'])->balance < $line['amount']) {
                $acting = $actingUserId !== null && $line['user_id'] === $actingUserId;

                throw ValidationException::withMessages(['balance' => $acting
                    ? __('رصيدك لا يكفي لرسوم المنصة (:fee جنيه). اشحن محفظتك أولًا.', ['fee' => $this->money($line['amount'])])
                    : __('رصيد الطرف الآخر لا يكفي لرسوم المنصة الآن.'), ]);
            }
        }
    }

    /**
     * Take the fee of every party, once. A second call for the same event, reference and occurrence takes nothing more.
     *
     * @param  array<string,int>  $parties
     * @return Collection<int,PlatformEventCharge>
     */
    public function charge(string $event, array $parties, float $base, string $referenceType, int $referenceId, string $occurrence = '1', ?string $note = null): Collection
    {
        $quote = $this->quote($event, $parties, $base);
        $this->assertCanPay($quote);

        $made = collect();

        DB::transaction(function () use ($quote, $event, $referenceType, $referenceId, $occurrence, $note, &$made) {
            foreach ($quote['lines'] as $role => $line) {
                if ($line['amount'] <= 0) {
                    continue;
                }

                $already = PlatformEventCharge::query()
                    ->where('event_key', $event)->where('reference_type', $referenceType)->where('reference_id', $referenceId)
                    ->where('occurrence', $occurrence)->where('role', $role)->first();

                if ($already) {
                    continue;
                }

                $key = "pec:{$event}:{$referenceType}:{$referenceId}:{$occurrence}:{$role}";

                $tx = $this->wallets->withdraw(
                    userId: $line['user_id'],
                    amount: $line['amount'],
                    note: $note ?? (self::EVENTS[$event][0] ?? $event),
                    referenceType: $event,
                    referenceId: (string) $referenceId,
                    idempotencyKey: $key,
                    meta: ['context' => 'platform_event_fee', 'event' => $event, 'role' => $role, 'reference_type' => $referenceType, 'reference_id' => $referenceId]
                );

                // the other half of the movement: without it the fee would leave the payer and reach nobody
                $this->treasury->credit(
                    amount: $line['amount'],
                    purpose: PlatformTreasuryService::PURPOSE_FEE,
                    referenceId: (string) $referenceId,
                    idempotencyKey: $key . ':treasury',
                    meta: ['context' => 'platform_event_fee', 'event' => $event, 'role' => $role]
                );

                $made->push(PlatformEventCharge::create([
                    'event_key' => $event, 'role' => $role, 'user_id' => $line['user_id'], 'reference_type' => $referenceType,
                    'reference_id' => $referenceId, 'occurrence' => $occurrence, 'amount' => $line['amount'],
                    'wallet_transaction_id' => (int) $tx->id,
                ]));
            }
        });

        return $made;
    }

    private function money(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
