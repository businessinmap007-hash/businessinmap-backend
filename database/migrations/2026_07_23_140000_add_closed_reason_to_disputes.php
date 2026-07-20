<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHY a dispute closed, not just that it did.
 *
 * "Closed" already existed but said nothing about the outcome: a case shut
 * because everyone complied and a case shut because an admin gave up looked
 * identical afterwards. `complied` is a verdict — the ruling was carried out,
 * every debt met — and it is the thing a party points to later to prove the
 * matter is genuinely over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->enum('closed_reason', ['complied', 'admin_closed'])
                ->nullable()
                ->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('closed_reason');
        });
    }
};
