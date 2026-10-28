<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «العشاء الإلزامي» and «بالغ واحد مع طفل» (owner, 2026-10-28).
 *
 * A gala dinner is a night the hotel names (رأس السنة، الكريسماس، شم النسيم…) whose dinner every guest staying that night pays: a price
 * per adult, a child a percentage of it, a child under an age free — the way Egyptian rate sheets write «Compulsory Gala Dinner».
 *
 * single_parent_percent: a room with ONE adult has the second adult's place empty but paid for in the room's price; the child in it pays
 * this percentage of one person's night (0 = free). Null = the child pays its normal age-band share.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gala_dinners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->date('date');
            $table->string('name', 120);
            $table->decimal('adult_price', 10, 2);
            $table->decimal('child_percent', 5, 2)->default(50);
            $table->unsignedTinyInteger('child_free_under')->default(6);
            $table->timestamps();

            $table->unique(['business_id', 'date']);
        });

        Schema::table('child_policies', function (Blueprint $table) {
            $table->decimal('single_parent_percent', 5, 2)->nullable()->after('extra_beds_max');
        });
    }

    public function down(): void
    {
        Schema::table('child_policies', function (Blueprint $table) {
            $table->dropColumn('single_parent_percent');
        });

        Schema::dropIfExists('gala_dinners');
    }
};
