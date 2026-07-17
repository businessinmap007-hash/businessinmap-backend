<?php

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * BIM-14.1 — preserve today's access at the moment enforcement arrives.
 *
 * The next commit puts `can:` middleware on all 321 AdminV2 routes. Right now
 * exactly one admin (#1) holds Bouncer's `*`; the other human admin holds NO
 * abilities at all and passes the panel middleware purely on `type = 'admin'`.
 * The instant the checks land, that account loses the entire panel — including
 * the ability to grant itself anything back, since there is no roles UI yet.
 *
 * So: every human admin that exists RIGHT NOW keeps exactly what it has today,
 * spelled out as `*` instead of implied by a type column. This is a one-time
 * backfill, which is precisely why it is a migration and not a seeder — a
 * re-runnable seeder that hands `*` to any admin without abilities would undo
 * every future restriction the moment somebody re-seeds.
 *
 * The treasury is excluded on purpose: it is `type = 'admin'` only so it is not
 * a business that trades, it has no login path, and it must never hold panel
 * powers it cannot use. Deliberately no down() — regranting `*` on rollback
 * would be a privilege escalation dressed as a revert.
 */
return new class extends Migration
{
    public function up(): void
    {
        $treasuryId = (int) config('bim.platform_wallet_user_id');

        $admins = User::query()
            ->where('type', User::TYPE_ADMIN)
            ->when($treasuryId > 0, fn ($q) => $q->where('id', '!=', $treasuryId))
            ->get();

        foreach ($admins as $admin) {
            if ($admin->can(AdminAbility::WILDCARD)) {
                continue; // already a super-admin (#1) — nothing to preserve
            }

            Bouncer::allow($admin)->everything();

            Log::info('BIM-14.1: preserved existing admin access as an explicit grant.', [
                'user_id' => (int) $admin->id,
                'email' => $admin->email,
            ]);
        }

        Bouncer::refresh();
    }

    public function down(): void
    {
        // Intentionally empty: see the class docblock.
    }
};
