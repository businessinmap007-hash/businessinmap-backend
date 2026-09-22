<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer can now ask a pharmacy for medicine directly — a photo of a paper
 * prescription and/or a free-text note, with no doctor and no dictionary-bound
 * drug required from the customer's side (Chefaa-style). The pharmacy reads
 * it and replies with real, priced, dictionary-bound lines (PrescriptionItem
 * unchanged) — the request only becomes a confirmed order once the customer
 * accepts that quote. `doctor_id` therefore has to allow null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable()->change();
            // doctor | customer — who started this prescription.
            $table->string('origin', 10)->default('doctor')->after('pharmacy_id');
            // The customer's own description of what they need, when origin=customer.
            $table->text('request_note')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['origin', 'request_note']);
            $table->unsignedBigInteger('doctor_id')->nullable(false)->change();
        });
    }
};
