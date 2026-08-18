<?php

namespace App\Services;

use App\Models\Post;
use App\Models\TalentView;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The two moments a scout pays, and the one row that remembers both.
 *
 * Owner, 2026-08-18: «يجب ان يكون كشاف ويكون هو المستهدف المباشر والاساسى…
 * واذا شاهده اكثر من مرة تحسب مرة واحدة فقط… والكشاف ايضا سيدفع مقابل الفيديو
 * اذا قام بالتواصل او طلب البيانات لان بيانات الناشئ سوف تكون مخفية».
 *
 * ── The player never pays ────────────────────────────────────────────────────
 *
 * The first shape discussed charged the BOY per scout view. It was inverted
 * deliberately: a fee that grows with how many scouts open your video is a tax
 * on being good at football, billed to a minor with no wallet, and the best
 * players would be the first to delete the card. The scout has the budget and
 * is the party the owner named as the target.
 *
 * ── Only a scout, and only a real one ────────────────────────────────────────
 *
 * The viewer must be a business standing on «مستكشف لاعبين» #550. That is not
 * decoration: the whole design hides a minor's identity behind a paywall, and a
 * paywall anyone may walk through protects nobody. `scout_child_id` is config
 * so the id survives a taxonomy move.
 *
 * ── Charged once, remembered forever ─────────────────────────────────────────
 *
 * One row per (player, scout), enforced by a unique index. Re-opening the card
 * bumps `view_count` and costs nothing. The idempotency key carries the pair
 * and the event, so a double-tapped button cannot double-charge even if two
 * requests land at once.
 *
 * ── Money out, money in ──────────────────────────────────────────────────────
 *
 * Where a platform treasury is configured the fee TRANSFERS to it, so the money
 * is visible on both sides of the ledger; where it is not, it is withdrawn and
 * simply not credited anywhere. That is the same fallback every other fee on
 * this platform uses — a charge is never blocked on a missing config, and money
 * is never invented.
 */
class TalentScoutingService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /**
     * A scout opens a talent card.
     *
     * First time: charge the view fee and record the pair. Every time after:
     * count it and charge nothing.
     */
    public function recordView(Post $talent, User $scout): TalentView
    {
        $this->assertTalent($talent);
        $this->assertScout($scout);

        $existing = TalentView::where('talent_post_id', $talent->id)
            ->where('scout_id', $scout->id)
            ->first();

        if ($existing) {
            $existing->increment('view_count');

            return $existing->refresh();
        }

        return DB::transaction(function () use ($talent, $scout) {
            $fee = (float) config('bim.talent.view_fee', 0);

            $tx = $this->charge($scout, $fee, $talent, 'view');

            /*
             * insertOrIgnore-style guard: two requests can reach the check above
             * before either inserts. The unique index is the referee — if the
             * other one won, its row is returned and this charge is refunded
             * rather than left as a debit with nothing to show for it.
             */
            $view = TalentView::firstOrNew([
                'talent_post_id' => $talent->id,
                'scout_id' => $scout->id,
            ]);

            if ($view->exists) {
                $this->refund($tx, $scout, 'talent-view-race');
                $view->increment('view_count');

                return $view->refresh();
            }

            $view->fill([
                'first_seen_at' => now(),
                'view_count' => 1,
                'view_fee' => $fee,
                'view_transaction_id' => $tx?->id,
            ])->save();

            return $view;
        });
    }

    /**
     * A scout asks for the player's identity and contact details.
     *
     * The card shows a first name, a position and a video; the name, the phone
     * and the club are behind this call. Charged once — a scout who has already
     * paid keeps the details.
     */
    public function revealContact(Post $talent, User $scout): TalentView
    {
        $view = $this->recordView($talent, $scout);

        if ($view->isRevealed()) {
            return $view;
        }

        return DB::transaction(function () use ($view, $talent, $scout) {
            $fee = (float) config('bim.talent.reveal_fee', 0);

            $tx = $this->charge($scout, $fee, $talent, 'reveal');

            $view->forceFill([
                'revealed_at' => now(),
                'reveal_fee' => $fee,
                'reveal_transaction_id' => $tx?->id,
            ])->save();

            return $view;
        });
    }

    /** Has this scout paid to see who the boy is? */
    public function hasRevealed(Post $talent, User $scout): bool
    {
        return TalentView::where('talent_post_id', $talent->id)
            ->where('scout_id', $scout->id)
            ->whereNotNull('revealed_at')
            ->exists();
    }

    private function charge(User $scout, float $fee, Post $talent, string $event)
    {
        if ($fee <= 0) {
            return null;
        }

        $key = "talent:{$event}:{$talent->id}:{$scout->id}";
        $note = $event === 'reveal'
            ? 'كشف بيانات ناشئ'
            : 'مشاهدة بطاقة ناشئ';

        $meta = ['talent_post_id' => $talent->id, 'event' => $event];

        $treasury = (int) config('bim.platform_wallet_user_id');

        if ($treasury > 0 && $treasury !== $scout->id) {
            $result = $this->wallet->transfer(
                $scout->id, $treasury, $fee, 'talent_view', (string) $talent->id, $note, $key, $meta
            );

            return $result['out'] ?? null;
        }

        return $this->wallet->withdraw($scout->id, $fee, $note, 'talent_view', (string) $talent->id, $key, $meta);
    }

    private function refund($tx, User $scout, string $reason): void
    {
        if (! $tx) {
            return;
        }

        $this->wallet->deposit(
            $scout->id, $tx->amount, 'استرداد: ' . $reason, 'talent_view_refund',
            (string) $tx->id, 'talent-refund:' . $tx->id
        );
    }

    private function assertTalent(Post $talent): void
    {
        if ($talent->type !== 'talent') {
            throw ValidationException::withMessages([
                'talent' => 'هذا المنشور ليس بطاقة ناشئ.',
            ]);
        }
    }

    /**
     * The paywall is the boy's protection, so the gate is checked here and not
     * only in a policy — a second caller must not be able to walk round it.
     */
    private function assertScout(User $scout): void
    {
        $childId = (int) config('bim.talent.scout_child_id');

        if ($scout->type !== 'business' || (int) $scout->category_child_id !== $childId) {
            throw ValidationException::withMessages([
                'scout' => 'مشاهدة بطاقات الناشئين متاحة لحساب «مستكشف لاعبين» فقط.',
            ]);
        }
    }
}
