<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wholesale price is not a public price.
 *
 * «المصنع ينتج ويعرض منتجاته حصريًا للشركات التي يحددها … ولا يستطيع رؤية هذه
 *  المنتجات وأسعارها إلا الشركات التي حددها المصنع، ويمكنه تحديد محلات بعينها.
 *  الشركة تشتري بسعر الجملة ثم تعيد البيع للمحلات بسعر تحدده هي» — المالك،
 *  2026-08-23.
 *
 * Every `business_catalog_listings` row is public today: it surfaces in retail
 * discovery to anybody, signed in or not. That is right for a shop's shelf and
 * wrong for a factory's price list — a manufacturer publishing wholesale
 * numbers would be showing them to his customers' customers, and to his
 * customers' competitors.
 *
 * ── Two columns and one table ───────────────────────────────────────────────
 *
 * `visibility` — `public` (today's behaviour, and the default, so nothing that
 * exists changes) or `restricted`. Nothing in between: a listing is either on
 * the shelf or it is addressed to named people.
 *
 * `catalog_listing_audiences` — WHO. Three kinds, because the owner named
 * three: a business by id («الشركات التي يحددها», «محلات بعينها»), a category
 * child («كل محلات الأدوات الصحية»), and a whole root («كل الشركات»). The
 * classification kinds are what «مقصورة على التصنيفات المعنية» means, and they
 * are what makes the feature usable — a factory with 400 customers is not
 * going to tick 400 names.
 *
 * `source_listing_id` — the resale chain. A company that can SEE a factory's
 * restricted listing may list the same product itself at its own price; this
 * points back at what it bought. Nullable, and null is the normal case: a shop
 * listing its own stock bought nothing from anybody here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_catalog_listings')) {
            return;
        }

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('business_catalog_listings', 'visibility')) {
                $table->string('visibility', 20)->default('public')->after('is_active');
                $table->index(['visibility', 'is_active'], 'bcl_visibility_idx');
            }

            if (! Schema::hasColumn('business_catalog_listings', 'source_listing_id')) {
                $table->unsignedBigInteger('source_listing_id')->nullable()->after('visibility');
                $table->index('source_listing_id', 'bcl_source_idx');
            }
        });

        if (Schema::hasTable('catalog_listing_audiences')) {
            return;
        }

        Schema::create('catalog_listing_audiences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_catalog_listing_id');

            // business | category_child | category
            $table->string('audience_type', 20);
            $table->unsignedBigInteger('audience_id');

            $table->timestamps();

            $table->unique(
                ['business_catalog_listing_id', 'audience_type', 'audience_id'],
                'cla_unique'
            );

            // The read that matters: «may this viewer see this listing».
            $table->index(['audience_type', 'audience_id'], 'cla_lookup_idx');

            $table->foreign('business_catalog_listing_id', 'cla_listing_fk')
                ->references('id')->on('business_catalog_listings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_listing_audiences');

        if (Schema::hasColumn('business_catalog_listings', 'visibility')) {
            Schema::table('business_catalog_listings', function (Blueprint $table) {
                $table->dropIndex('bcl_visibility_idx');
                $table->dropIndex('bcl_source_idx');
                $table->dropColumn(['visibility', 'source_listing_id']);
            });
        }
    }
};
