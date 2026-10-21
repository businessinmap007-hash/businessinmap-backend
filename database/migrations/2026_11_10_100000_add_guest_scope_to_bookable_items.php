<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «أسعار للمصريين والمقيمين فقط» (owner, 2026-10-21): a hotel sells the same rooms at a local rate and a tourist rate. A room type
 * says who it is sold to (everyone, Egyptians and residents only, or foreigners only) and the guest declares which they are when
 * booking; the hotel checks the papers on arrival.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookable_items', 'guest_scope')) {
            Schema::table('bookable_items', function (Blueprint $table) {
                $table->string('guest_scope', 12)->default('all')->after('status');   // all | locals | foreigners
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookable_items', 'guest_scope')) {
            Schema::table('bookable_items', function (Blueprint $table) {
                $table->dropColumn('guest_scope');
            });
        }
    }
};
