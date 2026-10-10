<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «النتيجة بتظهر في المعمل وبتكون كنص» — المالك، 2026-10-11. A result is written as TEXT, test by test, beside the test
 * it answers: it is read in the app, costs a few bytes instead of a photo, and can be copied into the patient's file.
 * Photos stay for what is not text (a film, a signed paper).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigation_order_items', function (Blueprint $table) {
            $table->text('result_text')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('investigation_order_items', function (Blueprint $table) {
            $table->dropColumn('result_text');
        });
    }
};
