<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «د.» / «أ.د.» / «استشاري» — the prefix a doctor's own name carries
 * everywhere it's shown (search results, the business card, an
 * appointment, a prescription). Only meaningful for an individual
 * practitioner's own clinic account (category_child_id = عيادة); a
 * hospital/medical-center's OWN name never carries one — the doctors
 * working there get their own title on their own roster entry instead
 * (a later migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'medical_title')) {
                $table->string('medical_title', 20)->nullable()->after('name_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'medical_title')) {
                $table->dropColumn('medical_title');
            }
        });
    }
};
