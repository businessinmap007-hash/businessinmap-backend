<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigIncrements('id');

            // المستخدم الذي قام بالحجز (Client)
            $table->unsignedBigInteger('user_id');

            // صاحب النشاط (Business) — عندك في السيستم هو user نوعه business
            $table->unsignedBigInteger('business_id');

            // لو عندك جدول services في المستقبل نربطه هنا (اختياري الآن)
            $table->unsignedBigInteger('service_id')->nullable();

            // تاريخ الحجز + الوقت
            $table->date('date');
            $table->time('time');

            // السعر (اختياري في البداية)
            $table->decimal('price', 10, 2)->nullable();

            // حالة الحجز
            $table->enum('status', [
                'pending',      // في الانتظار
                'accepted',     // تم قبول الحجز
                'rejected',     // تم رفض الحجز
                'canceled',     // تم إلغاء الحجز
                'completed'     // تم تنفيذ الحجز
            ])->default('pending');

            // ملاحظات إضافية من العميل أو البزنس
            $table->text('notes')->nullable();

            $table->timestamps();

            // العلاقات (نفترض users هي جدول المستخدمين)
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->foreign('business_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            // في المستقبل لو عملنا جدول services
            // $table->foreign('service_id')
            //     ->references('id')->on('services')
            //     ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookings');
    }
}
