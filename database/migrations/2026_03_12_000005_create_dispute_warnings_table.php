<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the dispute_warnings table's original CREATE migration
 * (see 2026_02_20_000001_create_deposits_table.php for context).
 * Must run after create_disputes_table (2026_03_12_000002) since it has
 * a foreign key on disputes.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispute_warnings') || ! Schema::hasTable('disputes')) {
            return;
        }

        Schema::create('dispute_warnings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('dispute_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('deposit_id')->nullable();
            $table->unsignedBigInteger('sent_to_user_id');
            $table->unsignedInteger('warning_no')->default(1);
            $table->enum('channel', ['database', 'email', 'sms', 'whatsapp'])->default('database');
            $table->text('message')->nullable();
            $table->dateTime('sent_at');

            $table->timestamps();

            $table->index('dispute_id', 'idx_dispute_warnings_dispute');
            $table->index('booking_id', 'idx_dispute_warnings_booking');
            $table->index('deposit_id', 'idx_dispute_warnings_deposit');
            $table->index('sent_to_user_id', 'idx_dispute_warnings_user');

            $table->foreign('dispute_id', 'fk_dispute_warnings_dispute')
                ->references('id')->on('disputes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_warnings');
    }
};
