<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الحجز لكل نشاط — الدرجة الثالثة من سُلّم التفصيل.
 *
 * الطفل يفتح الأنماط، والنشاط يملؤها، والعميل يرى شاشةً مبنيّةً من الاثنين.
 * قبل هذا الجدول كانت الدرجة الوسطى غائبة تمامًا: صاحبُ المحل لا يملك مفتاحًا
 * واحدًا، فكل بلايستيشن وكل جيم وكل بولينج يعرضون الشاشة نفسها.
 *
 * ── كل عمودٍ هنا يقبل NULL، وهذا هو التصميم ─────────────────────────────────
 *
 * NULL تعنى «خذ ما يقوله النمط» لا «لا شىء». فالنشاط الذى لم يفتح هذه الشاشة
 * قطّ يعمل تمامًا كما يعمل اليوم، والصفُّ لا يُنشأ إلا حين يقرّر صاحبه شيئًا.
 * ولهذا لا `default` على أىِّ عمود: الافتراضىُّ يسكن BookingPattern وحده،
 * وتكراره هنا يخلق مصدرَىْ حقيقة يفترقان عند أول تعديل.
 *
 * ── `uses_units` هو سبب وجود الجدول ─────────────────────────────────────────
 *
 * نمط «مدّة» يمتنع عن الحكم فى الوحدة (BookingPattern::UNIT_OPTIONAL) لأن
 * البلايستيشن يؤجّر أجهزة والبولينج حارات والجيم لا يؤجّر شيئًا — والطفل
 * الواحد فوق ثلاثتهم لا يعرف أيُّهم هو. هنا يُجاب السؤال، ومن يملك الجواب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_booking_settings')) {
            return;
        }

        Schema::create('business_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();

            // أىُّ أنماط الطفل اختار. NULL = الأساسى الذى يعلنه الطفل.
            $table->string('pattern', 32)->nullable();

            // عندى وحدات تُحجَز؟ NULL = خذ حكم النمط، إن كان له حكم.
            $table->boolean('uses_units')->nullable();

            // طول الفترة الواحدة، والحدّ الأدنى لليالى، وكم قبل الموعد يُحجَز.
            $table->unsignedSmallInteger('slot_minutes')->nullable();
            $table->unsignedTinyInteger('min_nights')->nullable();
            $table->unsignedInteger('lead_time_minutes')->nullable();

            // «فى المحل» أم «زيارةٌ عندك» أم الاثنان — السبّاك يذهب والرخام لا.
            $table->string('visit_mode', 16)->nullable();

            // قنوات الاستشارة: حضورى، أونلاين.
            $table->json('channels')->nullable();

            // ما يعرضه هذا النشاط وما يشترطه، فوق ما يقوله النمط.
            $table->json('asks')->nullable();
            $table->json('requires')->nullable();

            // ماذا يسأل حقلُ الملاحظات: «سبب الزيارة»، «رقم الشقة»…
            $table->string('notes_label', 120)->nullable();

            $table->timestamps();

            $table->foreign('business_id', 'business_booking_settings_business_fk')
                ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_booking_settings');
    }
};
