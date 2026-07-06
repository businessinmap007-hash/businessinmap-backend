<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Drops the dead wallet_holds subsystem (superseded by WalletLedgerService).
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('wallet_holds');
    }

    public function down(): void
    {
        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('user_id');

            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['held','released','void','disputed'])->default('held');

            $table->string('context', 50)->default('booking');

            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['wallet_id']);
            $table->index(['status']);
            $table->index(['context']);
            $table->index(['reference_type','reference_id']);
        });
    }
};
