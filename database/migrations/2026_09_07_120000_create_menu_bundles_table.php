<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «باقات منيو بأسماء شخصية وخصم مجمّع (زي "وجبة العيلة") بدل تسعير صنف-بصنف
 * فقط» — من Fresha، docs/product-changes-plan.md قسم (ب). المالك حسم نقطتين:
 * السعر يقدر يبقى مبلغ ثابت للباقة كلها أو خصم عن مجموع أسعار الأصناف
 * (النسبة أو المبلغ الثابت يحدده صاحب البيزنس)، والأصناف المكوّنة للباقة
 * ثابتة بالكامل (لا اختيار للعميل) — {@see menu_bundle_items}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();

            $table->string('name_ar', 191);
            $table->string('name_en', 191)->nullable();

            // fixed = صاحب البيزنس يحدد سعر ثابت للباقة كلها، متجاهلًا مجموع
            // أسعار الأصناف. discount_percent/discount_fixed = السعر يُحسب
            // تلقائيًا من مجموع أسعار الأصناف (وقت الطلب، مش وقت التعريف)
            // ناقص الخصم — نسبة أو مبلغ ثابت.
            $table->string('pricing_mode', 20)->default('fixed');
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['business_id', 'is_active'], 'menu_bundles_business_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_bundles');
    }
};
