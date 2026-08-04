<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a group of options is FOR, in pricing terms.
 *
 * Options and priced item types looked like duplicate vocabularies —
 * «عظام» beside «كشف», «غرفة نوم» beside a furniture product — and the
 * temptation was to merge them. They are not duplicates: they are different
 * coordinates of one priced line. «عظام» alone costs nothing and «كشف» alone
 * is meaningless; «كشف عظام» is what has a price.
 *
 * Sorting the 39 live groups by "does the customer pay for this exact thing?"
 * produced THREE answers, not two:
 *
 *   line         the option IS the thing bought — تخصصات طبية، الأنشطة
 *                الرياضية، أثاث، عقارات، خدمات الكوافير
 *   modifier     it never stands alone but it changes the price —
 *                مودرن/كلاسيك، بيع/إيجار، ثانوي/ابتدائي، مرسيدس/هيونداي
 *   descriptive  it is never priced at all — كاش، ممنوع التدخين، توصيل
 *
 * `descriptive` is the default because it is the safe answer: a group nobody
 * has classified stays out of every pricing screen. It is also the truth for
 * the widest groups — الدفع والسداد reaches 240 children, التسليم والاستلام
 * 129 — and those are exactly the ones that would drown a pricing list.
 *
 * Classification lives in database/seeders/data/option_price_roles.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('option_groups', 'price_role')) {
            return;
        }

        Schema::table('option_groups', function (Blueprint $table) {
            $table->enum('price_role', ['line', 'modifier', 'descriptive'])
                ->default('descriptive')
                ->after('is_active');

            $table->index('price_role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('option_groups', 'price_role')) {
            return;
        }

        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropIndex(['price_role']);
            $table->dropColumn('price_role');
        });
    }
};
