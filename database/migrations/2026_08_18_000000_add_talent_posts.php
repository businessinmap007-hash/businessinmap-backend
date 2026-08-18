<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «ناشئ موهوب» — a young player putting himself in front of a scout.
 *
 * Owner, 2026-08-18: «الناشئ سيعرض بياناته والمستكشف يحدد الرياضات الخاصة به».
 *
 * A POST and not a child, which is the whole point. Every taxonomy child on
 * this platform is a home for a MERCHANT — services, prices, invoices, a
 * rating, a wallet — and a fifteen-year-old offering himself to an academy
 * sells nothing. Filed as a child he would inherit that machinery empty: a
 * catalog with no product, a booking service with no unit, a wallet with no
 * movement. `posts` already carries the right shape: `type = 'job'` is a
 * vacancy with applicants and a visibility rule, and a talent card is the
 * same object pointing the other way — the person advertising, the business
 * reading.
 *
 * So the enum grows a third member and the slice gets the columns a scouting
 * card actually needs. All nullable: an ordinary post and a vacancy never set
 * them, exactly as `salary` and `requirements` are never set on a plain post.
 *
 * `sport` is free text against the option vocabulary rather than a foreign key
 * — the same choice `salary` made. The scout's «الرياضات المستهدفة» axis is
 * what the SEARCH matches on; a boy typing «كرة قدم» must not be dropped
 * because he did not pick from a list.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum: rewritten whole, so both existing members are restated.
        DB::statement("ALTER TABLE `posts` MODIFY `type` ENUM('post','job','talent') NOT NULL DEFAULT 'post'");

        Schema::table('posts', function (Blueprint $table) {
            $table->string('sport', 191)->nullable()->after('interview_starts_at');
            $table->string('playing_position', 191)->nullable()->after('sport');
            $table->date('birth_date')->nullable()->after('playing_position');
            $table->unsignedSmallInteger('height_cm')->nullable()->after('birth_date');
            $table->unsignedSmallInteger('weight_kg')->nullable()->after('height_cm');
            // «قدم يمين ولا شمال» is the first question every scout asks, and
            // it is not a number.
            $table->string('preferred_foot', 32)->nullable()->after('weight_kg');
            $table->string('current_club', 191)->nullable()->after('preferred_foot');
            // A scouting card is a video before it is a paragraph.
            $table->string('video_url', 500)->nullable()->after('current_club');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'sport', 'playing_position', 'birth_date', 'height_cm',
                'weight_kg', 'preferred_foot', 'current_club', 'video_url',
            ]);
        });

        // Any talent row would violate the narrowed enum, so it goes back to a
        // plain post rather than blocking the rollback.
        DB::table('posts')->where('type', 'talent')->update(['type' => 'post']);

        DB::statement("ALTER TABLE `posts` MODIFY `type` ENUM('post','job') NOT NULL DEFAULT 'post'");
    }
};
