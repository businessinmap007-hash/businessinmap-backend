<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('question_ar')->after('id');
            $table->string('question_en')->after('question_ar');
            $table->text('answer_ar')->after('question_en');
            $table->text('answer_en')->after('answer_ar');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn([
                'question_ar',
                'question_en',
                'answer_ar',
                'answer_en',
            ]);
        });
    }
};
