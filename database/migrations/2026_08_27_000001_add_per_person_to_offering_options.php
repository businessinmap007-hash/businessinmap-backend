<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «نظام الوجبات يجب ان يتم حسابة على عدد الافراد، فليس الافطار فى الغرفة
 * الفردى مثل الغرفة الثلاثية» — المالك، 2026-08-20.
 *
 * الزيادةُ كانت على الوحدة دائمًا: «إفطار +٥٠» خمسون على الغرفة سواءٌ نزل
 * فيها واحدٌ أو ثلاثة. وهذا صحيحٌ للإطلالة — البحرُ لا يُقسَّم على النزلاء —
 * وخطأٌ للطعام.
 *
 * فالعمودُ يقول أيَّهما: زيادةٌ على الوحدة، أو زيادةٌ لكل فرد تُضرب فى عدد
 * النزلاء. والافتراضُ صفر، فلا شىءَ مكتوبٌ اليوم يتغيّر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offering_options', function (Blueprint $table) {
            $table->boolean('per_person')->default(0)->after('adjust_value');
        });
    }

    public function down(): void
    {
        Schema::table('offering_options', function (Blueprint $table) {
            $table->dropColumn('per_person');
        });
    }
};
