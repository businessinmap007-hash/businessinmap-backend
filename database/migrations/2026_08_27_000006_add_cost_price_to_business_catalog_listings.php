<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تكلفة البضاعة على التاجر (سعر التوريد) — كانت موجودة على `menu_items`
 * (`supply_price`) وحدها؛ نظائرها هنا فى التجزئة الحقيقية (إعادة بيع B2B عبر
 * `BusinessCatalogListing`) لم توجد قط، فكل بيع تجزئة كان يبدو بلا تكلفة فى
 * كشف الوارد والصادر. اختيارى دائمًا — صفٌّ بلا تكلفة يُحسب مكسبُه = سعر
 * البيع فقط، لا صفرًا ولا خطأ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_catalog_listings')) {
            return;
        }

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('business_catalog_listings', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            if (Schema::hasColumn('business_catalog_listings', 'cost_price')) {
                $table->dropColumn('cost_price');
            }
        });
    }
};
