<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «نظام التعيين» — المالك، 2026-10-19: who is responsible for a piece of work is a platform concept, not a hospital one.
 *   - business_staff.is_provider   the staff member serves customers directly (a barber, a trainer, a doctor…); a
 *                                  physician is one of them, so `is_physician` rows are backfilled;
 *   - assignments                  one row per assigned item (a booking today; an order or anything morphable later):
 *                                  the business, the item and the staff member responsible for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('business_staff', 'is_provider')) {
            Schema::table('business_staff', function (Blueprint $t) {
                $t->boolean('is_provider')->default(false)->after('is_physician');
            });

            DB::table('business_staff')->where('is_physician', true)->update(['is_provider' => true]);
        }

        if (! Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('business_id')->index();
                $t->string('assignable_type', 120);
                $t->unsignedBigInteger('assignable_id');
                $t->unsignedBigInteger('assignee_id');
                $t->unsignedBigInteger('assigned_by')->nullable();
                $t->timestamps();

                // one person is responsible for an item at a time
                $t->unique(['assignable_type', 'assignable_id'], 'assignments_item_unique');
                $t->index(['business_id', 'assignee_id', 'assignable_type'], 'assignments_assignee_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');

        if (Schema::hasColumn('business_staff', 'is_provider')) {
            Schema::table('business_staff', fn (Blueprint $t) => $t->dropColumn('is_provider'));
        }
    }
};
