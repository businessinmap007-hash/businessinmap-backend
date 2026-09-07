<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الاسطر المعدلة للسعر يجب ان يكون لها شاشة لاضافة سعرها ... وتكون اختياري
 * لصاحب البزنس ان يحدد اذا كان الاختيار فردي فتكون راديو بوتون او متعدد يبقى
 * تشيك بوكس» — المالك، 2026-09-07.
 *
 * `menu_item_extras` كانت مسطّحة تمامًا: `group_key` نص حر بلا كيان خاص به،
 * فمفهوم «هذه المجموعة اختيار واحد فقط» لم يكن له مكانٌ يُكتب فيه أصلًا —
 * لا في قاعدة البيانات ولا في الواجهة، فكل إضافة كانت تُعرض كتشيك بوكس دائمًا
 * مهما كتب صاحب النشاط في حقل «المجموعة».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_extra_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();

            // single = راديو بوتون (اختيار واحد بالضبط) · multiple = تشيك بوكس.
            // القيمة الافتراضية «multiple» تحافظ على سلوك كل الصفوف الحالية:
            // كانت كلها تُعرض كتشيك بوكس مستقل دون أي قيد.
            $table->string('selection_type', 10)->default('multiple');

            $table->unsignedInteger('reorder')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['menu_item_id', 'is_active'], 'extra_groups_item_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_extra_groups');
    }
};
