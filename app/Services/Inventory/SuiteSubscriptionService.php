<?php

namespace App\Services\Inventory;

use App\Models\Inventory\SuiteSubscription;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The desktop suite's MONTHLY subscription, taken from the business's wallet as a platform fee (owner, 2026-10-29) and
 * priced in the control panel («رسوم الأحداث»: business_suite_month — off/free until he prices it).
 *
 * When the balance does not cover the month (his choice on the canvas): a reminder 3 days before → 7 full days of grace
 * with a retry every day → then READ-ONLY (reading and exporting) until he tops up. Nothing is ever deleted, and the
 * stock keeps following the app's sales the whole time so the balance stays true.
 */
final class SuiteSubscriptionService
{
    public const NONE = 'none';
    public const ACTIVE = 'active';
    public const GRACE = 'grace';
    public const READ_ONLY = 'read_only';

    public const WARN_DAYS = 3;
    public const GRACE_DAYS = 7;

    public function __construct(private readonly PlatformEventFeeService $fees) {}

    public function price(User $business): float
    {
        return (float) $this->fees->quote(PlatformEventFeeService::BUSINESS_SUITE_MONTH, ['business' => (int) $business->id])['total'];
    }

    public function writable(User $business): bool
    {
        return in_array($this->state($business)['status'], [self::ACTIVE, self::GRACE], true);
    }

    /** @return array{status:string,price:float,paid_until:?string,grace_until:?string,months_paid:int} */
    public function state(User $business): array
    {
        $sub = SuiteSubscription::query()->where('business_id', $business->id)->first();

        return [
            'status' => $sub ? (string) $sub->status : self::NONE,
            'price' => $this->price($business),
            'paid_until' => optional($sub?->paid_until)->toIso8601String(),
            'grace_until' => optional($sub?->grace_until)->toIso8601String(),
            'months_paid' => (int) ($sub?->months_paid ?? 0),
        ];
    }

    /**
     * Start, or pay the next month now (a top-up after read-only lifts it at once). Refused with the shortfall when the
     * wallet cannot cover the month.
     */
    public function subscribe(User $business): SuiteSubscription
    {
        $sub = SuiteSubscription::query()->firstOrCreate(['business_id' => $business->id], ['status' => self::NONE]);

        if ($sub->status === self::ACTIVE && $sub->paid_until && $sub->paid_until->isFuture()) {
            return $sub;
        }

        $quote = $this->fees->quote(PlatformEventFeeService::BUSINESS_SUITE_MONTH, ['business' => (int) $business->id]);
        $this->fees->assertCanPay($quote, (int) $business->id);

        return $this->payMonth($sub, $business);
    }

    /** Daily: remind, renew, move to grace, then to read-only. Returns what changed, for the console. */
    public function runDaily(?Carbon $now = null): array
    {
        $now ??= now();
        $done = ['warned' => 0, 'renewed' => 0, 'grace' => 0, 'read_only' => 0];

        foreach (SuiteSubscription::query()->whereIn('status', [self::ACTIVE, self::GRACE])->whereNotNull('paid_until')->get() as $sub) {
            $business = User::query()->find($sub->business_id);
            if (! $business) {
                continue;
            }

            // switched off by the owner: the paid month runs out and nothing more is taken
            if (! \App\Models\Inventory\Setting::query()->where('business_id', $business->id)->value('enabled')) {
                if ($sub->paid_until->lte($now)) {
                    $sub->update(['status' => self::NONE, 'grace_until' => null]);
                }

                continue;
            }

            if ($sub->status === self::ACTIVE && $sub->paid_until->isAfter($now)) {
                if ($sub->paid_until->copy()->subDays(self::WARN_DAYS)->lte($now)
                    && (! $sub->warned_for || ! $sub->warned_for->equalTo($sub->paid_until))
                    && $this->price($business) > 0) {
                    $sub->update(['warned_for' => $sub->paid_until]);
                    $this->tell($business, 'يتجدد اشتراك تطبيق الكمبيوتر يوم ' . $sub->paid_until->format('Y-m-d') . ' بقيمة ' . $this->price($business) . ' ج من محفظتك. تأكد أن الرصيد يكفي.',
                        'Your desktop suite subscription renews on ' . $sub->paid_until->format('Y-m-d') . '. Make sure your wallet covers it.');
                    $done['warned']++;
                }

                continue;
            }

            try {
                $quote = $this->fees->quote(PlatformEventFeeService::BUSINESS_SUITE_MONTH, ['business' => (int) $business->id]);
                $this->fees->assertCanPay($quote, (int) $business->id);
                $this->payMonth($sub, $business, $now);
                $done['renewed']++;

                continue;
            } catch (ValidationException) {
                // not enough balance: grace, then read-only
            }

            if ($sub->status === self::ACTIVE) {
                $sub->update(['status' => self::GRACE, 'grace_until' => $sub->paid_until->copy()->addDays(self::GRACE_DAYS)]);
                $this->tell($business, 'لم يكفِ رصيد المحفظة لتجديد اشتراك تطبيق الكمبيوتر. كل شيء يعمل ' . self::GRACE_DAYS . ' أيام سماح، ثم يصبح للقراءة فقط حتى الشحن. لا تُحذف أي بيانات.',
                    'Your wallet did not cover the desktop suite. Everything works for ' . self::GRACE_DAYS . ' days of grace, then it becomes read-only until you top up. No data is ever deleted.');
                $done['grace']++;
            } elseif ($sub->grace_until && $sub->grace_until->lte($now)) {
                $sub->update(['status' => self::READ_ONLY]);
                $this->tell($business, 'انتهت أيام السماح: تطبيق الكمبيوتر الآن للقراءة والتصدير فقط حتى تشحن المحفظة وتجدد. بياناتك محفوظة، وخصم المبيعات من المخزون مستمر.',
                    'The grace period ended: the desktop suite is read-only until you top up and renew. Your data is kept and sales still update the stock.');
                $done['read_only']++;
            }
        }

        return $done;
    }

    private function payMonth(SuiteSubscription $sub, User $business, ?Carbon $now = null): SuiteSubscription
    {
        $now ??= now();
        $occurrence = (string) ((int) $sub->months_paid + 1);

        $this->fees->charge(PlatformEventFeeService::BUSINESS_SUITE_MONTH, ['business' => (int) $business->id], 0.0,
            'business_suite', (int) $business->id, $occurrence, 'اشتراك تطبيق الكمبيوتر — شهر ' . $occurrence);

        $from = $sub->paid_until && $sub->paid_until->isAfter($now) ? $sub->paid_until : $now;
        $sub->update([
            'status' => self::ACTIVE,
            'months_paid' => (int) $sub->months_paid + 1,
            'paid_until' => $from->copy()->addMonth(),
            'grace_until' => null,
        ]);

        return $sub->fresh();
    }

    private function tell(User $business, string $ar, string $en): void
    {
        try {
            app(NotificationDispatcherService::class)->dispatch('business_suite_subscription', (int) $business->id, [
                'body_ar' => $ar, 'body_en' => $en, 'source_type' => 'business_suite', 'source_id' => $business->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
