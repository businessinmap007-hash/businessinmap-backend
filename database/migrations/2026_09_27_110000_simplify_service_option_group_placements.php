<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اين تظهر... سيكون تلقائيا» — المالك: where a group shows follows from what
 * it IS (a section, a descriptive field, a price dimension), so the per-group
 * surface/input-type/required knobs are gone. usage is now one of
 * section | price_variant | descriptive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_option_group_placements', function (Blueprint $table) {
            $table->dropColumn(['surfaces', 'input_type', 'is_required']);
        });
    }

    public function down(): void
    {
        Schema::table('service_option_group_placements', function (Blueprint $table) {
            $table->json('surfaces')->nullable();
            $table->string('input_type', 20)->default('single');
            $table->boolean('is_required')->default(false);
        });
    }
};
