<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two more things the admin decides per field of a menu kind, in «أشكال المنيو»:
 *
 *  - `show_on_page`: does the field appear on the CUSTOMER's product page («صفحة
 *    المنتج»)? On by default — every field already shown stays shown.
 *  - `display`: how a list field is drawn in the merchant's form — `chips` (the
 *    buttons: quick, everything in view), `dropdown` (compact, for many options)
 *    or `auto` (buttons up to six options, a dropdown beyond).
 *
 * Both for the catalog fields and for the descriptive option groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['menu_detail_profile_attributes', 'menu_detail_profile_option_groups'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('show_on_page')->default(true);
                $t->string('display', 12)->default('auto');
            });
        }
    }

    public function down(): void
    {
        foreach (['menu_detail_profile_attributes', 'menu_detail_profile_option_groups'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['show_on_page', 'display']);
            });
        }
    }
};
