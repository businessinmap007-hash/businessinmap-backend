<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment the parties settled BETWEEN THEMSELVES, outside the platform.
 *
 * The platform never touches this money — it cannot verify a bank transfer or
 * cash in a hand. So what is recorded is not the payment but the three
 * statements about it, each by a different person: one proposes the amount, the
 * other accepts that this is the deal, and the RECEIVER confirms it actually
 * arrived. That third statement is the one that matters, and it is why the
 * payee is the only account allowed to make it: a payer confirming their own
 * payment proves nothing.
 *
 * Its own table rather than columns on `disputes` because a settlement can be
 * proposed, refused, and proposed again at a different figure — the haggling is
 * the record, and an arbitrator reading the case needs to see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();

            $table->unsignedBigInteger('proposed_by_user_id');
            // An arbitrator proposes too, once one is involved — same screen,
            // different author.
            $table->enum('proposed_by_role', ['client', 'business', 'arbitrator']);

            // Who hands money to whom. The other side is the receiver, and the
            // receiver is the only one who may confirm arrival.
            $table->enum('payer_side', ['client', 'business']);
            $table->decimal('amount', 12, 2);
            $table->string('method', 40)->nullable();
            $table->text('note')->nullable();

            $table->enum('status', ['proposed', 'accepted', 'received', 'rejected', 'withdrawn'])
                ->default('proposed');

            $table->unsignedBigInteger('accepted_by_user_id')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['dispute_id', 'status'], 'dispute_settlements_dispute_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_settlements');
    }
};
