<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «استراحة»: a business that answers customers' requests (a limousine driver, a showroom) can pause them — no new
 * request is sent to it, it cannot answer one and its «راجع فاضي» offers are not shown — until it switches back.
 * null = available (the default for everybody).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('requests_paused_at')->nullable()->after('banned_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('requests_paused_at');
        });
    }
};
