<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ثلاثةُ أعمدةٍ على `users` لم تحمل قرارًا قطّ.
 *
 *   booking_hold_enabled     — صفرٌ على 3812 حسابًا، بلا استثناء
 *   booking_hold_amount      — صفرٌ على 3812 حسابًا، بلا استثناء
 *   booking_hold_max_percent — ٢٠ على 3812 حسابًا: قيمةُ العمود الافتراضية،
 *                              لم يغيّرها أحد ولا شاشةَ تعرضها
 *
 * وسياسةُ العربون تُقرأ من `business_deposit_policies` منذ زمن، فهذه بقيّةُ
 * محاولةٍ سبقتها. الدوالُّ الثلاثُ التى تقرؤها على النموذج لا ينادى عليها
 * شىء إلا بعضُها بعضًا.
 *
 * ويُعيدها `down()` بقيمها كما كانت — وهى واحدةٌ للجميع، فالاسترجاعُ أمين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['booking_hold_enabled', 'booking_hold_amount', 'booking_hold_max_percent'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'booking_hold_enabled')) {
                $table->boolean('booking_hold_enabled')->default(0);
            }

            if (! Schema::hasColumn('users', 'booking_hold_amount')) {
                $table->decimal('booking_hold_amount', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('users', 'booking_hold_max_percent')) {
                $table->unsignedTinyInteger('booking_hold_max_percent')->default(20);
            }
        });
    }
};
