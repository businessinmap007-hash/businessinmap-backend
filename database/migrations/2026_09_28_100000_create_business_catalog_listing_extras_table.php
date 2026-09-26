<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الإضافات» — priced add-ons a merchant offers on one retail listing
 * («ضمان سنة / ضمان سنتين» as a pick-one group, «تركيب» as a tick box), the
 * non-food twin of a menu item's extras. Like a menu extra, a chosen add-on is
 * snapshotted onto the cart line (name + price) and priced into the unit
 * price server-side, so retiring or repricing one later never rewrites a past
 * order.
 *
 * group_name_ar '' means «a lone tick box»; rows sharing a non-empty group
 * name form one group, `single` (radio) or `multiple` (checkboxes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_catalog_listing_extras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('group_name_ar')->default('');
            $table->string('selection_type', 12)->default('multiple');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('listing_id', 'bcle_listing_idx');
            $table->foreign('listing_id', 'bcle_listing_fk')
                ->references('id')->on('business_catalog_listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_catalog_listing_extras');
    }
};
