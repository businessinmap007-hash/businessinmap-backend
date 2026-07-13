<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a restaurant owner set their own menu tax rate (percent). NULL means
 * "use the global config rate" (config('bim.menu_tax_rate_percent')), so the
 * default behaviour is unchanged. See MenuBillingService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_menu_settings')
            && ! Schema::hasColumn('business_menu_settings', 'tax_rate_percent')) {
            Schema::table('business_menu_settings', function (Blueprint $table) {
                $table->decimal('tax_rate_percent', 5, 2)->nullable()->after('prices_include_tax');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_menu_settings')
            && Schema::hasColumn('business_menu_settings', 'tax_rate_percent')) {
            Schema::table('business_menu_settings', function (Blueprint $table) {
                $table->dropColumn('tax_rate_percent');
            });
        }
    }
};
