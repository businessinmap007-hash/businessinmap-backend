<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a priced row used to cost, so a discount can be checked against it.
 *
 * «يجب مرور شهر كامل دون رفع السعر قبل تسجيل عرض خصم — منعًا للعروض الوهمية
 *  التي يرفع فيها التاجر السعر ثم يعلن خصمًا ثم يعيده كما كان» — المالك،
 *  2026-08-23.
 *
 * That rule cannot be enforced against a number the merchant types into the
 * offer form, and until now that is exactly what «السعر السابق» was: an offer
 * carried a `base_price` the API accepted from the request without ever
 * looking at what the item actually costs.
 *
 * So the platform has to remember. One row per change, on any priced surface —
 * a menu item, a shop's catalog listing, a service price — with the old value
 * beside the new one, so «was this an increase» is a comparison and not an
 * inference from two rows an hour apart.
 *
 * Polymorphic rather than three tables: the rule is one rule, and an offer on
 * a sandwich is checked the same way as an offer on a pair of jeans.
 *
 * `old_price` is NULL for the first row, which is a creation and not an
 * increase — a shop that opens at 200 has not raised anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offering_price_changes')) {
            return;
        }

        Schema::create('offering_price_changes', function (Blueprint $table) {
            $table->id();

            $table->string('priceable_type', 60);
            $table->unsignedBigInteger('priceable_id');

            // Denormalised so «has this business raised anything lately» is one
            // query rather than a walk through three polymorphic parents.
            $table->unsignedBigInteger('business_id')->nullable();

            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->string('currency', 10)->default('EGP');

            // Stored, not derived at read time: the one question every reader
            // asks is «when did this last go UP», and an index answers it.
            $table->boolean('is_increase')->default(false);

            $table->string('source', 40)->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->index(['priceable_type', 'priceable_id', 'changed_at'], 'opc_priceable_idx');
            $table->index(['business_id', 'is_increase', 'changed_at'], 'opc_business_increase_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_price_changes');
    }
};
