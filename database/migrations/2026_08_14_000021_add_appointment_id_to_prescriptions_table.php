<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a prescription to the clinic appointment it was written during, so a
 * completed visit and its روشتة connect (the clinic and patient are the same two
 * parties in both). Nullable: a prescription can still be issued standalone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_id')->nullable()->after('patient_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('appointment_id');
        });
    }
};
