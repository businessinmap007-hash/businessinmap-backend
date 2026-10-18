<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A field that depends on one of the unit's own choices.
 *
 * A car for SALE is described by its year, mileage, gearbox; the same car for RENT by whether a driver comes, what the
 * insurance covers, how many kilometres a day are included. Both are the same kind of listing — the difference is one
 * option the merchant ticks («نوع التعامل»: بيع / إيجار) — so the fields follow that option instead of the kind
 * being split in two:
 *
 *  - `only_with_option_id`: the field exists only for a unit that carries this option (rental terms ← «إيجار»).
 *  - `not_with_option_id`: the field is hidden for a unit that carries it (mileage means nothing on a rental).
 *
 * Null both = always asked, which is every field written so far.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_detail_profile_attributes', function (Blueprint $t) {
            $t->unsignedBigInteger('only_with_option_id')->nullable()->after('display');
            $t->unsignedBigInteger('not_with_option_id')->nullable()->after('only_with_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('menu_detail_profile_attributes', function (Blueprint $t) {
            $t->dropColumn(['only_with_option_id', 'not_with_option_id']);
        });
    }
};
