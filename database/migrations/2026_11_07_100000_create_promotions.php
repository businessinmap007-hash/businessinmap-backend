<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «العروض والخصومات» (owner, 2026-10-21): a discount that REACHES THE PRICE. A promotion is a rule of a business — what it covers
 * (the whole shop, a section, some items), what it does (a percentage, an amount, «اشترِ ٣ بسعر أغلى واحدة», a spend threshold), when
 * it runs, and for whom — and the cart prices its lines by the best rule that fits each of them.
 *
 * The line keeps the promotion that priced it and the amount taken off, so the order is a record of what was promised.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->index();
                $table->string('title', 120)->nullable();
                $table->string('kind', 20);                       // percent | amount | n_for_price_of | spend_percent
                $table->json('params');                           // {value} | {n, pay} | {min_total, value}
                $table->string('audience', 16)->default('all');   // all | new_customers
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('max_uses')->nullable();  // orders; null = until ends_at
                $table->unsignedInteger('used_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('promotion_targets')) {
            Schema::create('promotion_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id')->index();
                $table->string('target_type', 20);                // all | menu_section | menu_item | listing | listing_category
                $table->unsignedBigInteger('target_id')->nullable();
                $table->index(['target_type', 'target_id']);
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'promotion_id')) {
                $table->unsignedBigInteger('promotion_id')->nullable()->after('total_price');
            }
            if (! Schema::hasColumn('order_items', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('total_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['promotion_id', 'discount']));
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
