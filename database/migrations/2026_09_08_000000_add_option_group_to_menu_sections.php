<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a menu section to the option group it was grown from.
 *
 * A goods business no longer types "أنواع الأجهزة الكهربائية" by hand — the
 * section is created the first time an item picks a `line` option from that
 * group ({@see \App\Services\Menu\MenuSectionFromOptionGroup}), and this
 * column is what makes that find-or-create idempotent per business. A
 * hand-typed section (a restaurant's "مقبلات") has no group and keeps
 * `option_group_id` null — this migration only adds the link, it never
 * back-fills existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_sections') && ! Schema::hasColumn('menu_sections', 'option_group_id')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->unsignedBigInteger('option_group_id')->nullable()->after('business_id');
                $table->foreign('option_group_id', 'menu_sections_option_group_fk')
                    ->references('id')->on('option_groups')->nullOnDelete()->cascadeOnUpdate();
                $table->unique(['business_id', 'option_group_id'], 'menu_sections_business_group_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_sections') && Schema::hasColumn('menu_sections', 'option_group_id')) {
            Schema::table('menu_sections', function (Blueprint $table) {
                $table->dropUnique('menu_sections_business_group_unique');
                $table->dropForeign('menu_sections_option_group_fk');
                $table->dropColumn('option_group_id');
            });
        }
    }
};
