<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كيف تُعرض مجموعةُ خياراتٍ عند نشاطٍ بعينه — راديو (فردي) أو تشيك بوكس
 * (متعدد) — بلا لمس `option_groups` نفسها.
 *
 * `option_groups` تصنيفٌ عالمي مشتركٌ بين كل الأنشطة (راجع
 * `2026_08_17_000001_create_taxonomy_lab_tables.php`)، فوضعُ `selection_type`
 * عليها مباشرةً كان يفرض نفسَ السلوك على كل نشاطٍ يستعمل المجموعةَ نفسها —
 * فندقٌ يريد «نظام الوجبات» راديو وفندقٌ آخر يريده تشيك بوكس، وهما يستعملان
 * نفسَ مجموعة التصنيف. فالإعدادُ هنا مُلحقٌ بصاحبه (offering) لا بالمجموعة
 * نفسها، تمامًا كما فُعل مع `menu_item_extra_groups` (جدولٌ جديدٌ خاصٌّ
 * بصاحبه) بدل تعديل جدولٍ عالمي هناك أيضًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_option_group_settings', function (Blueprint $table) {
            $table->id();
            $table->string('offering_type', 191);
            $table->unsignedBigInteger('offering_id');
            $table->foreignId('option_group_id')->constrained('option_groups')->cascadeOnDelete();
            $table->string('selection_type', 20)->default('multiple');
            $table->timestamps();

            $table->unique(['offering_type', 'offering_id', 'option_group_id'], 'offering_option_group_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_option_group_settings');
    }
};
