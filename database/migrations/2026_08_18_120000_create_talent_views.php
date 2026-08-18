<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who looked at which young player, and what it cost.
 *
 * Owner, 2026-08-18: «يكون السحب من الرصيد على كل مشاهدة من كشاف، واذا شاهده
 * اكثر من مرة تحسب مرة واحدة فقط… والكشاف ايضا سيدفع مقابل الفيديو اذا قام
 * بالتواصل او طلب البيانات لان بيانات الناشئ سوف تكون مخفية».
 *
 * The UNIQUE (talent_post_id, scout_id) is not an optimisation — it IS the
 * «تحسب مرة واحدة» rule. A scout who opens the same card thirty times has one
 * row, and the charge hangs off the row rather than off the request.
 *
 * Two events, two prices, two nullable stamps. Watching is cheap and is the
 * shop window; asking for the boy's number is the product, and it is the row a
 * complaint is answered from — `revealed_at` plus `scout_id` says exactly who
 * took a minor's contact details and when.
 *
 * The wallet transaction ids are kept so a charge can be traced back and
 * refunded without guessing which of a scout's debits belonged to which player.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('talent_post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('scout_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('first_seen_at');
            $table->unsignedInteger('view_count')->default(1);

            $table->decimal('view_fee', 12, 2)->default(0);
            $table->foreignId('view_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();

            $table->timestamp('revealed_at')->nullable();
            $table->decimal('reveal_fee', 12, 2)->default(0);
            $table->foreignId('reveal_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();

            $table->timestamps();

            // One row per (player, scout) — the «counted once» rule itself.
            $table->unique(['talent_post_id', 'scout_id']);

            // «who has seen my card» on the player's side, newest first.
            $table->index(['talent_post_id', 'revealed_at']);

            // «who have I already paid for» on the scout's side.
            $table->index(['scout_id', 'first_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_views');
    }
};
