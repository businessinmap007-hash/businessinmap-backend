<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Co-guarantors for a single operation. When a user's own (platform-purchased)
 * guarantee coverage is not enough for an operation, they may invite a friend
 * whose guarantee coverage supplements theirs for THAT operation only. The
 * friend's guarantee coverage is frozen (never charged); it is released when
 * the operation completes or a dispute is resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_guarantors')) {
            return;
        }

        Schema::create('operation_guarantors', function (Blueprint $table) {
            $table->id();

            // Polymorphic operation (booking now; delivery/marketplace later),
            // aligned with GuaranteeOperationCoverageService OP_* types.
            $table->string('operation_type', 40)->default('booking');
            $table->unsignedBigInteger('operation_id');

            // The user being guaranteed and the friend guaranteeing them.
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guarantor_user_id')->constrained('users')->cascadeOnDelete();

            // The friend's guarantee whose coverage is used, and the portion it
            // contributes to this operation (frozen while active).
            $table->foreignId('user_guarantee_id')->nullable()->constrained('user_guarantees')->nullOnDelete();
            $table->decimal('covered_amount', 12, 2)->default(0);

            // invited -> accepted (coverage frozen) -> released ; or declined/cancelled.
            $table->string('status', 20)->default('invited')->index();

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['operation_type', 'operation_id']);
            // A friend can be invited to a given operation only once.
            $table->unique(['operation_type', 'operation_id', 'guarantor_user_id'], 'op_guarantor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_guarantors');
    }
};
