<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * «موبايل»، «تابلت»، «ساعة ذكية» each need their OWN auto-grown section
     * once branches_as_sections is on for their group — today's find-or-create
     * is scoped to (business_id, option_group_id) only, so every branch of a
     * split group collapses onto the SAME one section. `option_id` narrows
     * that key. 0, not null, is "the whole-group section" (today's only
     * behaviour) — MySQL never treats two NULLs as equal in a unique index, so
     * a nullable column would silently let concurrent first-uses of the same
     * un-split group create two rows instead of one, exactly the race the
     * original constraint existed to prevent.
     */
    public function up(): void
    {
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('option_id')->default(0)->after('option_group_id');
        });

        Schema::table('menu_sections', function (Blueprint $table) {
            $table->dropUnique('menu_sections_business_group_unique');
            $table->unique(['business_id', 'option_group_id', 'option_id'], 'menu_sections_business_group_option_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->dropUnique('menu_sections_business_group_option_unique');
            $table->unique(['business_id', 'option_group_id'], 'menu_sections_business_group_unique');
            $table->dropColumn('option_id');
        });
    }
};
