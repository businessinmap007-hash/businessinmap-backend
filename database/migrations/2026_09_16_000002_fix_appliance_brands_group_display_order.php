<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * option_groups #992 («ماركات الأجهزة الكهربائية», created 2026-09-08) was born with
 * reorder=0, same class of mistake as #993 fixed in the previous migration:
 * it sorted first in the "modifier" tier instead of alphabetically, right
 * before "ماركات السيارات" (reorder 10170). 10165 sits in the gap after
 * "فترة الحجز" (10160) without renumbering anything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('option_groups')->where('id', 992)->update(['reorder' => 10165]);
    }

    public function down(): void
    {
        DB::table('option_groups')->where('id', 992)->update(['reorder' => 0]);
    }
};
