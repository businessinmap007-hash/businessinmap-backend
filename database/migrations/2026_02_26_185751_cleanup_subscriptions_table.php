<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            //
        });
    }

    /**<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {

            // حذف الأعمدة الزائدة
            if (Schema::hasColumn('subscriptions', 'coupon_id')) {
                $table->dropColumn('coupon_id');
            }

            if (Schema::hasColumn('subscriptions', 'code_type')) {
                $table->dropColumn('code_type');
            }

            if (Schema::hasColumn('subscriptions', 'price')) {
                $table->dropColumn('price');
            }

            if (Schema::hasColumn('subscriptions', 'finished_at')) {
                $table->dropColumn('finished_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // rollback (اختياري)
            $table->string('coupon_id', 50)->nullable();
            $table->string('code_type', 50)->nullable();
            $table->float('price')->default(0);
            $table->timestamp('finished_at')->nullable();
        });
    }
};
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            //
        });
    }
};
