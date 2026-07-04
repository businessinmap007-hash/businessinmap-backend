<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the deposit_events table's original CREATE migration
 * (see 2026_02_20_000001_create_deposits_table.php for context).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deposit_events') || ! Schema::hasTable('deposits')) {
            return;
        }

        Schema::create('deposit_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('deposit_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('dispute_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->enum('actor_type', ['client', 'business', 'admin', 'system'])->default('system');
            $table->string('event_type', 100);
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('deposit_id', 'idx_deposit_events_deposit');
            $table->index('booking_id', 'idx_deposit_events_booking');
            $table->index('dispute_id', 'idx_deposit_events_dispute');
            $table->index('event_type', 'idx_deposit_events_type');

            $table->foreign('deposit_id', 'fk_deposit_events_deposit')
                ->references('id')->on('deposits')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_events');
    }
};
