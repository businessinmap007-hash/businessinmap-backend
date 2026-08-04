<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a priced offering actually IS, in the platform's own vocabulary.
 *
 * A price row said «كشف» and a menu item said «غرفة نوم مودرن» as free text.
 * Neither could be searched, compared or named: the customer who filtered on
 * «عظام» arrived at a hospital and then met a list of prices that never
 * mentioned عظام again.
 *
 * One offering carries:
 *   - exactly one `line` option — what is being sold (عظام، غرفة نوم، شقة)
 *   - any number of `modifier` options — what qualifies it (مودرن، إيجار،
 *     سوبر لوكس، غرفتين)
 *
 * See option_groups.price_role for which group may play which part. `role` is
 * copied here at write time on purpose: re-classifying a group later must not
 * silently turn an existing listing's line into a modifier.
 *
 * Polymorphic because the platform prices two different ways and both need it:
 * `menu_items` (a listing with specs, images and a price) and
 * `business_service_prices` (item type × line, e.g. كشف × عظام).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offering_options')) {
            return;
        }

        Schema::create('offering_options', function (Blueprint $table) {
            $table->id();

            $table->string('offering_type', 191);
            $table->unsignedBigInteger('offering_id');

            $table->unsignedBigInteger('option_id');
            $table->enum('role', ['line', 'modifier'])->default('modifier');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['offering_type', 'offering_id', 'option_id'], 'offering_options_unique');
            $table->index(['offering_type', 'offering_id', 'role'], 'offering_options_role_index');

            // discovery filters offerings by option, so this side is queried alone
            $table->index('option_id');

            $table->foreign('option_id')->references('id')->on('options')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_options');
    }
};
