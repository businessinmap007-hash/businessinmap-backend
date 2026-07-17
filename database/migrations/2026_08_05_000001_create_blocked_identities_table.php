<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ban list — the identities a permanently banned account may not come back
 * with (fraud policy: the balance is deducted and the account is banned by the
 * email and the mobile it used).
 *
 * It is a separate table, not columns on `users`, for one reason: deletion
 * anonymizes the email and the phone. A ban enforced by reading users.email
 * would evaporate the moment the banned account deleted itself — delete,
 * re-register, clean slate. These rows survive that, because they hold no PII
 * to scrub.
 *
 * Only hashes are stored, and they are HMAC-SHA256 keyed with APP_KEY, never a
 * bare hash: a phone number has ~10 digits of entropy, so a plain SHA-256 of
 * one is brute-forced in seconds and the table would be a plaintext contact
 * list to anyone who read it. Keyed, it is worthless without the app key. The
 * cost is that it is a set-membership test only — you can ask "is this identity
 * banned?" and never "which identity is this row?". That is exactly the access
 * the check needs, and rotating APP_KEY invalidates the list (see BlockedIdentity).
 *
 * user_id is the account the ban came from. It survives anonymization because
 * the user row keeps its id — the ledger, ratings and invoices point at it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_identities', function (Blueprint $table) {
            $table->id();

            // Nullable: a ban may know only one of the two. Unique rather than a
            // plain index — the same identity is banned once, and re-banning
            // updates the reason. MySQL allows many NULLs under a unique index,
            // so an email-only ban does not collide with another email-only ban.
            $table->char('email_hash', 64)->nullable()->unique('blocked_identities_email_hash_uq');
            $table->char('phone_hash', 64)->nullable()->unique('blocked_identities_phone_hash_uq');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason')->nullable();
            $table->string('source', 40)->default('manual'); // manual | fraud | …
            $table->unsignedBigInteger('blocked_by')->nullable(); // admin who did it
            $table->json('meta')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'blocked_identities_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_identities');
    }
};
