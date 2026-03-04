<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletsTable extends Migration
{
    public function up()
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // ربط المحفظة بالمستخدم
            $table->unsignedBigInteger('user_id');

            // الرصيد المتاح للاستخدام
            $table->decimal('balance', 12, 2)->default(0);

            // الرصيد المحجوز (للطلبات الجارية أو الحجوزات)
            $table->decimal('locked_balance', 12, 2)->default(0);

            // إجمالي الإيداع – للعمليات المحاسبية
            $table->decimal('total_in', 12, 2)->default(0);

            // إجمالي السحب – للعمليات المحاسبية
            $table->decimal('total_out', 12, 2)->default(0);

            // حالة المحفظة
            $table->enum('status', ['active', 'blocked'])->default('active');

            $table->timestamps();

            // المفتاح الخارجي
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallets');
    }
}
