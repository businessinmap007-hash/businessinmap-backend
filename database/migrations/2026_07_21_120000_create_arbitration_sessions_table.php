<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per ruling: who decided, what they decided, and what it moved.
 *
 * The dispute itself already records `resolution_type` and `resolved_by`, but
 * only its LATEST state — and a dispute is ruled once. What was missing is the
 * view from the other side: an arbitrator's own record across every case they
 * have heard. That cannot be derived from `disputes` alone once a dispute is
 * archived or its payload rewritten, and an arbitrator's record is exactly the
 * thing that must not be quietly editable.
 *
 * Amounts are stored rather than recomputed from percentages: the escrow total
 * can change meaning later (a policy edit, a refund elsewhere), and this table
 * has to keep saying what actually happened at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arbitration_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();

            // Nullable: a ruling made before arbitrators existed, or by a system
            // path, still belongs in the record — attributed to nobody.
            $table->unsignedBigInteger('arbitrator_id')->nullable();

            $table->string('outcome', 40);

            $table->decimal('client_percent', 5, 2)->nullable();
            $table->decimal('business_percent', 5, 2)->nullable();

            $table->decimal('amount_to_client', 12, 2)->default(0);
            $table->decimal('amount_to_business', 12, 2)->default(0);

            // What the platform took as a fine, and from whom.
            $table->decimal('platform_fine_amount', 12, 2)->default(0);
            $table->enum('platform_fine_on', ['client', 'business'])->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // A dispute is ruled once, so a second row means a bug, not history.
            $table->unique('dispute_id', 'arbitration_sessions_dispute_unique');
            $table->index('arbitrator_id', 'arbitration_sessions_arbitrator_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arbitration_sessions');
    }
};
