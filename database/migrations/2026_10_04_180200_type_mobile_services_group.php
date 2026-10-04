<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** «خدمات الموبايل» is a service priced as a service — the basic type. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('option_groups')->where('name_ar', 'خدمات الموبايل')->whereNull('detail_type')->update(['detail_type' => 'basic']);
    }

    public function down(): void
    {
    }
};
