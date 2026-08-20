<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دورُ كل مجموعةِ خياراتٍ عند هذا النشاط بعينه.
 *
 * مفرداتُ الفندق ثلاثُ مجموعات — «الغرف» و«إطلالة الوحدة» و«نظام الوجبات» —
 * وكلُّها تظهر فى كل قائمةٍ من قوائم اللوحة، لأن الحاجز بين السطر والمُوصِّف
 * مفتوحٌ عمدًا. فلا شىءَ يقول أيُّها أساسُ السعر وأيُّها يزيد عليه وأيُّها
 * يُسعَّر وحده — ويُترك للتاجر أن يستنتجه من الشاشة التى يقف فيها.
 *
 * فيُعلَن مرّةً هنا:
 *
 *   line   الغرف          — الأساس. «غرفة مزدوجة ٩٠٠».
 *   unit   إطلالة الوحدة  — تزيد على الأساس وتُثبَّت على غرفةٍ بعينها. D117 ١١٠٠.
 *   addon  نظام الوجبات   — سعرٌ منفصل يختاره النزيل ويُضرب فى عدد الأفراد.
 *
 * ومجموعةٌ بلا دورٍ تظهر فى الجميع كما كانت، فلا ينكسر تاجرٌ لم يُعلن بعد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_option_group_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('option_group_id')->index();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['business_id', 'option_group_id'], 'bogr_business_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_option_group_roles');
    }
};
