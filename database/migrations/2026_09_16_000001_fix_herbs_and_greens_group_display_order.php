<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `2026_09_09_000002_split_herbs_and_greens_into_their_own_group` created
 * option_groups #993 («أعشاب وورقيات») with reorder=0, which put it FIRST in the
 * "line" tier instead of where the alphabet puts it - between
 * "أصناف مستحضرات التجميل" (reorder 130) and "أعمال الأرضيات" (reorder 140).
 * 135 sits in that gap without renumbering anything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('option_groups')->where('id', 993)->update(['reorder' => 135]);
    }

    public function down(): void
    {
        DB::table('option_groups')->where('id', 993)->update(['reorder' => 0]);
    }
};
