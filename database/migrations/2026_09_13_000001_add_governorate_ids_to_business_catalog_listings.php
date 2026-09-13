<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A retail listing's own governorate allow-list - the merchant's answer to
 * "who can even see this, geographically" sitting ABOVE the existing
 * business/shop-type/category audience (see CatalogListingAudience): that
 * system grants wholesale visibility to named buyers, this one narrows an
 * otherwise-visible listing to specific governorates regardless of who is
 * asking. Null or an empty array means no restriction - every governorate.
 * See RetailListingVisibility, which is the one place both gates are
 * enforced.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_catalog_listings', 'governorate_ids')) {
            return;
        }

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->json('governorate_ids')->nullable()->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->dropColumn('governorate_ids');
        });
    }
};
