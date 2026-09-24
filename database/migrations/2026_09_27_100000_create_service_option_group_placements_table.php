<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اريد تحديد مكونات الخدمة واين تعرض كل مجموعة وكيفية استخدام كل مجموعة مع
 * اى بند من المكونات» — المالك، 2026-09-24.
 *
 * category_child_option says WHICH child may use an option group. This says
 * WHERE that group shows up inside a service (surfaces) and HOW it is used
 * with an item type of that service (usage + input type). One row per
 * (service, group, child, item type); child_id 0 is the service-wide default,
 * a real child id overrides it for that child alone, and item_type_key '' means
 * every item type of the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_option_group_placements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_service_id');
            $table->unsignedBigInteger('option_group_id');
            $table->unsignedBigInteger('child_id')->default(0);
            $table->string('item_type_key', 100)->default('');
            $table->json('surfaces')->nullable();
            $table->string('usage', 30);
            $table->string('input_type', 20)->default('single');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['platform_service_id', 'option_group_id', 'child_id', 'item_type_key'],
                'sogp_scope_unique'
            );
            $table->index(['platform_service_id', 'child_id'], 'sogp_service_child_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_option_group_placements');
    }
};
