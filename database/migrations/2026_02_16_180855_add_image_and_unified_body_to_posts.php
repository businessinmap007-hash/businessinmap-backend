<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // 1) add columns
        Schema::table('posts', function (Blueprint $table) {

            if (!Schema::hasColumn('posts', 'image')) {
                $table->string('image', 255)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('posts', 'body')) {
                $table->longText('body')->nullable()->after('title_en');
            }
        });

        // ✅ 2) force utf8mb4 on the new column (important for emoji)
        DB::statement("ALTER TABLE posts MODIFY body LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");

        // ✅ 3) move data (prefer AR then EN)
        DB::statement("
            UPDATE posts
            SET body = COALESCE(NULLIF(body_ar, ''), NULLIF(body_en, ''))
            WHERE body IS NULL
        ");

        // 4) drop old translated bodies only
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'body_ar')) $table->dropColumn('body_ar');
            if (Schema::hasColumn('posts', 'body_en')) $table->dropColumn('body_en');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'body_ar')) $table->longText('body_ar')->nullable();
            if (!Schema::hasColumn('posts', 'body_en')) $table->longText('body_en')->nullable();
        });

        DB::statement("UPDATE posts SET body_ar = COALESCE(body_ar, body) WHERE body_ar IS NULL");

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'image')) $table->dropColumn('image');
            if (Schema::hasColumn('posts', 'body'))  $table->dropColumn('body');
        });
    }
};
