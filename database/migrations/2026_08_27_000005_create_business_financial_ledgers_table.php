<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كشف الوارد والصادر والمكسب — رصيدٌ متراكم لا تقرير يُجمَّع كل مرة.
 *
 * كل عملية بيع أو رسم منصة تُضاف مباشرة على الصف القائم لحظة وقوعها
 * (`FinancialLedgerService`) بدل مسح كل الطلبات والحجوزات عند كل قراءة —
 * المالك: «كل عملية صادر تجمع على السابقة مباشرة ويكون الناتج لحظى لا
 * يحتاج اعادة تجميع كل مرة». صفٌّ واحد لكل (نشاط، مصدر)؛ `total` يجمع كل
 * المصادر لنفس النشاط فيبقى الإجمالي قراءةً واحدة أيضًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_financial_ledgers')) {
            return;
        }

        Schema::create('business_financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('source', 20); // total|menu|retail|booking
            $table->decimal('revenue_total', 14, 2)->default(0);
            $table->decimal('cost_of_goods_total', 14, 2)->default(0);
            $table->decimal('platform_fees_total', 14, 2)->default(0);
            $table->unsignedInteger('operations_count')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'source']);
            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_financial_ledgers');
    }
};
