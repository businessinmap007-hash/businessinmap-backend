<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posts and job posts are user-written content, not platform copy: the author
 * types the title once, in their own language. Keeping `title_ar`/`title_en`
 * asked every author to write it twice and nobody ever did — `title_en` was
 * empty on all 880 rows, which made Post::getTitleAttribute() (locale === 'ar'
 * ? title_ar : title_en) return NULL for every English reader.
 *
 * One `title` column, mirroring `body` — which was unified the same way in
 * 2026_02_16_180855. The `_ar`/`_en` pair stays where it belongs: platform-
 * maintained taxonomy (categories, menu sections) that BIM itself translates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // TEXT, not varchar(191): title_ar was TEXT and 4 legacy rows hold
        // 500–2900 chars (a body pasted into the title), which a varchar would
        // silently truncate. Writes are still validated to 191 — the loose
        // column only preserves what is already there.
        Schema::table('posts', function (Blueprint $table) {
            $table->text('title')->nullable()->after('type');
        });

        // Arabic is the written side everywhere; English only where it isn't.
        DB::table('posts')->update([
            'title' => DB::raw("COALESCE(NULLIF(title_ar, ''), NULLIF(title_en, ''))"),
        ]);

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'title_en']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('title_ar')->nullable();
            $table->text('title_en')->nullable();
        });

        DB::table('posts')->update(['title_ar' => DB::raw('title')]);

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
