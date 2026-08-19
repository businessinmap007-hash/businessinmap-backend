<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الغرفةُ تُوصَف، والمِلاحظةُ تبقى خلف الكواليس.
 *
 * كانت الوحدةُ تُعرَّف برقمها وسعتها فقط، فصاحبُ الفندق لا يملك أن يقول
 * «إطلالة على النيل، الدور السادس» لنزيله، ولا أن يكتب «التكييف يحتاج
 * صيانة» لموظّفه. عمودان لأنهما جمهوران: `description` يخرج فى واجهة
 * العميل، و`notes` لا يخرج أبدًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->text('notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropColumn(['description', 'notes']);
        });
    }
};
