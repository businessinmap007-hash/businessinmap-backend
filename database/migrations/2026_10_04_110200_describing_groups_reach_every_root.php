<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The describing groups batch one borrowed («الجمهور المستهدف»، «أنواع الأقمشة») were linked to their
 * trades only under the roots the trade already carried them on — a merchant standing under another
 * root of the same trade was never offered them. Link them under every root (0).
 */
return new class extends Migration
{
    public function up(): void
    {
        $curator = new MenuShapeCurator;
        $audience = (int) DB::table('option_groups')->where('name_ar', 'الجمهور المستهدف')->value('id');
        $fabrics = (int) DB::table('option_groups')->where('name_ar', 'أنواع الأقمشة')->value('id');

        if ($audience) {
            $curator->describe($audience, [73], 20, 'chips', false);
            $curator->describe($audience, [59, 60, 168], 10, 'chips', false);
        }
        if ($fabrics) {
            $curator->describe($fabrics, [115], 20, 'dropdown', true);
            $curator->describe($fabrics, [59, 60], 30, 'dropdown', true);
        }
    }

    public function down(): void
    {
    }
};
