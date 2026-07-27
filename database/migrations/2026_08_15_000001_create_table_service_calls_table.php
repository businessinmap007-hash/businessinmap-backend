<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dine-in customer's request for staff attention from their table (BIM-13.3):
 * "call the waiter", "the bill please", or generic assistance. One open row per
 * (table, type) is kept live; the business sees the pending list and resolves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_service_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('business_table_id')->constrained('business_tables')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('waiter'); // waiter | bill | assistance
            $table->string('status', 12)->default('pending'); // pending | resolved
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_service_calls');
    }
};
