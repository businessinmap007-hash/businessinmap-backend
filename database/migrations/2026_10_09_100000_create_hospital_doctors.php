<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «ابنِ نموذج الأطباء تحت الأقسام، وإذا كان الطبيب له حساب على التطبيق يُضاف تحت القسم وأستطيع زيارة صفحته من تحت
 * القسم» — المالك، 2026-10-09.
 *
 * A hospital's departments are the medical specialties it ticked on itself (options of «تخصصات طبية», rows of
 * `option_user`) — there is no department table. This table puts a doctor under one of them: either a doctor with an
 * account on the app (`user_id`, who must accept, so no hospital can claim a doctor who never agreed) or a doctor with
 * no account (`name`, listed as text, no page to visit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('users')->cascadeOnDelete();
            // the department: an option of the group «تخصصات طبية»
            $table->foreignId('option_id')->constrained('options')->cascadeOnDelete();
            // the doctor's own clinic account — null for a doctor with no account
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('title', 60)->nullable();
            // pending: waiting for the doctor to accept; active: shown to patients
            $table->string('status', 10)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hospital_id', 'option_id', 'user_id']);
            $table->index(['hospital_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_doctors');
    }
};
