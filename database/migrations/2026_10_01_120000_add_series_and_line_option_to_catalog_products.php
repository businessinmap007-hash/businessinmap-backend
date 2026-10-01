<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two facets a device catalog is browsed by, below the brand:
 *
 *   series          «Galaxy A», «Reno», «F», «Redmi Note» — the family a model
 *                   belongs to, so a merchant (and a customer) can go
 *                   أوبو → F → every F model instead of scrolling one list.
 *   line_option_id  the menu BRANCH the product is sold under («موبايل»,
 *                   «تابلت», «ساعة ذكية» …, an `options` id). Without it the
 *                   «تابلت» branch's picker offered phones and chargers,
 *                   because the whole of «موبايلات وإكسسوارات» is one
 *                   product_category_child. Null = not mapped: the picker
 *                   then falls back to the business's whole catalog scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_products', 'series')) {
                $table->string('series', 80)->nullable()->after('model')->index();
            }
            if (! Schema::hasColumn('catalog_products', 'line_option_id')) {
                $table->unsignedBigInteger('line_option_id')->nullable()->after('series')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->dropColumn(['series', 'line_option_id']);
        });
    }
};
