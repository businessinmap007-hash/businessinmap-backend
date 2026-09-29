<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_option_group_placements', function (Blueprint $table) {
            // Only meaningful when usage=section. Default (false): the GROUP is
            // one section and its options are that section's branches (today's
            // only behaviour — «أجهزة الموبايل وملحقاتها» holding موبايل/تابلت/
            // ساعة ذكية/... all under one department). true: each OPTION is its
            // own section instead — a device shop's «موبايل»، «تابلت»، «ساعة
            // ذكية» each get their own storefront section/nav chip, matching the
            // Tech Catalog Setup canvas's TechSelect groups.
            $table->boolean('branches_as_sections')->default(false)->after('usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_option_group_placements', function (Blueprint $table) {
            $table->dropColumn('branches_as_sections');
        });
    }
};
