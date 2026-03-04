<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationsTable extends Migration
{
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // صاحب المحادثة الأول (مثلاً اللي بدأ الشات)
            $table->unsignedBigInteger('user_one_id');

            // الطرف الثاني في المحادثة
            $table->unsignedBigInteger('user_two_id');

            // آخر رسالة (للسرعة في عرض القائمة)
            $table->text('last_message')->nullable();

            // وقت آخر رسالة
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->foreign('user_one_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_two_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversations');
    }
}
