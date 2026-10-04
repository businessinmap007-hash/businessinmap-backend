<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The menu line groups the first classification missed: fashion and sanitary ware are sold by the piece;
 * the three service groups (workshop specialties, A/C works, key services) are priced as services and
 * say so with the basic type.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('option_groups')->whereIn('id', [10, 403])->whereNull('detail_type')->update(['detail_type' => 'piece_goods']);
        DB::table('option_groups')->whereIn('id', [304, 432, 594])->whereNull('detail_type')->update(['detail_type' => 'basic']);
    }

    public function down(): void
    {
    }
};
