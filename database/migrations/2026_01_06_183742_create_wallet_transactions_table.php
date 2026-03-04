<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('user_id');

            // Pending/Completed/Failed/Reversed
            $table->enum('status', ['pending','completed','failed','reversed'])->default('completed');

            // In / Out
            $table->enum('direction', ['in','out']);

            // Stable types (don’t change later)
            $table->enum('type', [
                'deposit',
                'withdraw',
                'hold',
                'release',
                'refund',
                'adjustment',
                'transfer'
            ]);

            // Money (decimal only)
            $table->decimal('amount', 12, 2);

            // Auditing snapshots
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->decimal('locked_before', 12, 2)->default(0);
            $table->decimal('locked_after', 12, 2)->default(0);

            // Generic relation to anything (orders/escrows/delivery/...)
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id', 191)->nullable(); // string to support your escrows.order_id varchar

            // Optional: prevent duplicates if needed
            $table->string('idempotency_key', 80)->nullable();

            $table->text('note')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'id']);
            $table->index(['user_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->unique(['wallet_id', 'idempotency_key']);

            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
