<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_booking_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('platform_service_id')
                ->constrained('platform_services')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            // كيف يتم الحجز زمنيًا
            $table->string('booking_mode', 50)->default('fixed');
            // أمثلة:
            // hourly / daily / nightly / slot / fixed_event / flexible

            // العائلة العامة للعناصر القابلة للحجز
            $table->string('item_family', 100)->nullable();
            // أمثلة:
            // hotel_room / apartment_unit / sports_field / clinic_slot / hall / table

            // هل هذا التصنيف يحتاج عنصر فعلي من bookable_items؟
            $table->boolean('requires_bookable_item')->default(true);

            // هل المستخدم يحدد بداية ونهاية؟
            $table->boolean('requires_start_end')->default(true);

            // هل يحتاج quantity؟
            $table->boolean('supports_quantity')->default(false);

            // هل يحتاج عدد ضيوف؟
            $table->boolean('supports_guest_count')->default(false);

            // هل يدعم إضافات مثل وجبات / خدمات إضافية؟
            $table->boolean('supports_extras')->default(false);

            // أنواع العناصر المسموحة لهذا التصنيف
            $table->json('allowed_item_types')->nullable();
            // مثال:
            // ["single_room","double_room","suite"]

            // الحقول الإضافية المطلوبة في الواجهة أو الميتا
            $table->json('required_fields')->nullable();
            // مثال:
            // ["check_in","check_out","guests","meal_plan"]

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['category_id', 'platform_service_id'],
                'cbp_category_service_unique'
            );

            $table->index(['category_id', 'is_active'], 'cbp_category_active_idx');
            $table->index(['platform_service_id', 'is_active'], 'cbp_service_active_idx');
            $table->index(['booking_mode'], 'cbp_booking_mode_idx');
            $table->index(['item_family'], 'cbp_item_family_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_booking_profiles');
    }
};