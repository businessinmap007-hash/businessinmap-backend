<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المُوصِّف يُسعِّر: «شاشة كبيرة +٢٠ على الساعة».
 *
 * «اريد مثلا هناك سعر للغرفة الخاصة مع بلايستشن 4 وسعر ل 5 وايضا اضافة سعر
 * على الشاشة الكبيرة» — المالك، 2026-08-19.
 *
 * ── ما كان ينقص ─────────────────────────────────────────────────────────────
 *
 * `offering_options` تربط بالعرض سطرًا واحدًا وعدّةَ مُوصِّفات — والأدوارُ
 * الثلاثة تقول ما يُباع وما يوصِّفه — ثم يقف الأمر: المُوصِّفُ يوصِّف ولا
 * يُسعِّر. فمن أراد سعرين لغرفةٍ بجهازين اضطُرّ إلى سطرين اثنين.
 *
 * ── ولماذا هنا لا فى جدولٍ جديد ─────────────────────────────────────────────
 *
 * بدأتُ بجدولٍ مستقلٍّ معلَّقٍ على `business_service_prices`، ثم تبيّن أن هذا
 * الجدول موجودٌ بالفعل وأوسع: `offering_options` متعدّدُ الأشكال، يخدم صفَّ
 * السعر وصنفَ المنيو معًا. فزيادةُ عمودين هنا تعطى «جبنة إضافية +٥» على البيتزا
 * بالمجّان، وجدولٌ ثانٍ كان سيعطى آليتين لسؤالٍ واحد.
 *
 * ── والقيمة قد تكون سالبة ───────────────────────────────────────────────────
 *
 * عن قصد: «بلايستيشن ٤» أرخصُ من «٥»، فيسعّر التاجرُ الأعلى ويخصم للأقدم بدل
 * أن يُجبَر على سطرين. والحدُّ الأدنى صفرٌ يُفرَض عند الحساب لا هنا — جمعُ
 * خصمين قد يهبط تحت الصفر، وذلك حسابٌ لا بيان.
 *
 * وصفرُ القيمة هو الحال القائمة: كل الصفوف الموجودة تبقى مُوصِّفاتٍ بلا سعر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offering_options') || Schema::hasColumn('offering_options', 'adjust_value')) {
            return;
        }

        Schema::table('offering_options', function (Blueprint $table) {
            // amount = مبلغٌ ثابت على الوحدة · percent = نسبةٌ من سعر الوحدة
            $table->string('adjust_type', 12)->default('amount')->after('role');
            $table->decimal('adjust_value', 12, 2)->default(0)->after('adjust_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('offering_options') || ! Schema::hasColumn('offering_options', 'adjust_value')) {
            return;
        }

        Schema::table('offering_options', function (Blueprint $table) {
            $table->dropColumn(['adjust_type', 'adjust_value']);
        });
    }
};
