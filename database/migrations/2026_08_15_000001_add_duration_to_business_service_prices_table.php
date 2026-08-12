<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الطبيب يقدر يحدد للكشف ٣٠ دقيقة والاستشارة ٢٠».
 *
 * The duration lived on the appointment and on the published slot, so a clinic
 * could say «this batch of slots is thirty minutes» but never «a كشف is always
 * thirty and an استشارة always twenty». It had to retype it every time it
 * published, and a patient requesting a time picked the length himself.
 *
 * The priced row is where it belongs: it already IS (item type × line) — «كشف
 * — عظام», «استشارة» — which is exactly the granularity the question is asked
 * at. Nothing new to define, and the clinic sets it beside the price it is
 * already typing.
 *
 * Nullable on purpose: a hotel room and a football pitch have no consultation
 * length, and a clinic that never fills it keeps today's 30-minute default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('charge_amount');
        });
    }

    public function down(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
