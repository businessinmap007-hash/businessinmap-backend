<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A moderator can take a reported comment down (owner, 2026-10-27): it keeps its row but no longer shows to anyone, the
 * post's author included. `removed` joins `public` / `private`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE comments MODIFY status ENUM('private','public','removed') NOT NULL DEFAULT 'public'");
    }

    public function down(): void
    {
        DB::table('comments')->where('status', 'removed')->update(['status' => 'private']);
        DB::statement("ALTER TABLE comments MODIFY status ENUM('private','public') NOT NULL DEFAULT 'public'");
    }
};
