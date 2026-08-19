<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مفرداتُ عرضٍ تشير إلى صفوفٍ لم تعد موجودة.
 *
 * `offering_options` متعدّدُ الأشكال ومشترك بين سطور الأسعار وأصناف المنيو
 * والوحدات، ولم يكن شىءٌ ينظّف خلف صفٍّ محذوف — فبقيت صفوفُه فيه تشير إلى
 * عدم. تسعةَ عشرَ صفًّا وُجدت فعلًا على سطور أسعارٍ حُذفت.
 *
 * الحارسُ الدائم فى `HasOfferingOptions::bootHasOfferingOptions`. وهذه تكنس
 * ما تراكم قبله.
 *
 * ولا `down()` لها: لا يُعاد صفٌّ يشير إلى شىءٍ غيرِ موجود — ما يُسترجَع
 * سيبقى يتيمًا كما كان.
 */
return new class extends Migration
{
    /** كلُّ نموذجٍ يستعمل السِّمة، وجدولُه. */
    private const OWNERS = [
        \App\Models\BusinessServicePrice::class => 'business_service_prices',
        \App\Models\MenuItem::class => 'menu_items',
        \App\Models\BookableItem::class => 'bookable_items',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('offering_options')) {
            return;
        }

        foreach (self::OWNERS as $model => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $morph = (new $model)->getMorphClass();

            $deleted = DB::table('offering_options')
                ->where('offering_type', $morph)
                ->whereNotIn('offering_id', fn ($query) => $query->from($table)->select('id'))
                ->delete();

            if ($deleted > 0) {
                echo "  swept {$deleted} orphan option rows from {$table}\n";
            }
        }
    }

    public function down(): void
    {
        // بلا رجعة، عن قصد — راجع أعلاه.
    }
};
