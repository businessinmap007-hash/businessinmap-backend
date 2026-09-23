<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor with more than one clinic account links them together, with both
 * sides' consent, so a patient sees one unified schedule («سبت واتنين
 * واربعاء عيادة دمياط... حد وتلاتة وخميس عيادة القاهرة» — المالك) instead of
 * two unconnected business pages. See ClinicLinkService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | accepted
            $table->timestamps();

            $table->unique(['requester_id', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_links');
    }
};
