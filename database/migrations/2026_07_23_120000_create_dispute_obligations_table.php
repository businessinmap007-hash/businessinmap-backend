<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a ruling left someone owing, and whether they have paid it.
 *
 * Until now a charge that the wallet could not cover simply threw: the ruling
 * said one thing and the ledger recorded nothing, so an arbitrator had no way
 * to tell "paid" from "never collected". An obligation is the missing noun —
 * the debt exists whether or not it can be met today.
 *
 * `due_at` is what makes the guarantee raid legitimate. The party is asked
 * first, given a stated window, and only then is their frozen guarantee opened
 * without returning to them. Taking it immediately would be seizure; taking it
 * after a deadline they were told about is enforcement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_obligations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');

            $table->enum('type', ['session_fee', 'platform_fine', 'compensation']);
            $table->decimal('amount', 12, 2);

            // Who the money is for. NULL means the platform treasury.
            $table->unsignedBigInteger('payee_user_id')->nullable();

            $table->enum('status', ['pending', 'paid'])->default('pending');

            // How it was eventually met — the distinction an arbitrator and the
            // payer both care about.
            $table->enum('settled_from', ['wallet', 'guarantee'])->nullable();

            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // One obligation of a given kind per dispute: a retry must not
            // double the debt.
            $table->unique(['dispute_id', 'type'], 'dispute_obligations_unique');
            $table->index(['user_id', 'status'], 'dispute_obligations_user_status_idx');
            $table->index(['status', 'due_at'], 'dispute_obligations_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_obligations');
    }
};
