<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times a moderator froze this account (owner, 2026-10-29): the fifth freeze deletes the account and confiscates its
 * balance and guarantee (`bim.moderation.freezes_to_delete`). Counted per freeze, never reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'freeze_count')) {
                $table->unsignedSmallInteger('freeze_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('freeze_count'));
    }
};
