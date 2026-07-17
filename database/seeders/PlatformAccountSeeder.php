<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The platform treasury account (BIM-15 / BIM-3 fees).
 *
 * A dedicated account rather than the existing business named "BIM": that one
 * trades — it sells, receives orders and pays fees — so platform money in it
 * could never be told apart from its own. This account exists only to hold
 * money: it never logs in and never sells.
 *
 * Re-runnable. Prints the id to put in .env as BIM_PLATFORM_WALLET_USER_ID;
 * nothing resolves this account by name (see config/bim.php).
 */
class PlatformAccountSeeder extends Seeder
{
    /** Stable marker — how the seeder finds its own row again, not how the app resolves it. */
    public const EMAIL = 'platform-treasury@businessinmap.internal';

    /**
     * users.phone is NOT NULL and UNIQUE. This is deliberately not a dialable
     * number: real users type digits, so this can never collide with one, and
     * it cannot be mistaken for a contact.
     */
    public const PHONE = 'bim-treasury';

    public function run(): void
    {
        $account = User::query()->withTrashed()->where('email', self::EMAIL)->first();

        if ($account && $account->trashed()) {
            $account->restore();
        }

        if (! $account) {
            $account = new User();
            $account->email = self::EMAIL;
            $account->phone = self::PHONE;
            // Unguessable and never used: this account has no login path.
            $account->password = bcrypt(Str::random(64));
            $account->api_token = Str::random(80); // legacy NOT NULL UNIQUE column
        }

        $account->name = 'BIM Platform Treasury';
        $account->type = 'admin';
        $account->activated_at = now();
        $account->save();

        // Its wallet must exist and be active before any fee can land in it.
        $wallet = app(WalletService::class)->getOrCreateWallet((int) $account->id);
        $wallet->update(['status' => Wallet::STATUS_ACTIVE]);

        $configured = (int) config('bim.platform_wallet_user_id');

        $this->command?->info("Platform treasury account id: {$account->id}");

        if ($configured !== (int) $account->id) {
            $this->command?->warn(
                "Set BIM_PLATFORM_WALLET_USER_ID={$account->id} in .env — until then, fees are debited from the payer but credited nowhere."
            );
        }
    }
}
