<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One platform-wide number: how many distinct admins must each approve
 * viewing a chat before it unlocks without the parties' own consent. A
 * singleton row (id=1) rather than a key/value table — there is only ever
 * this one setting today, and a second one is a migration away if it's ever
 * needed. Owner-configurable from AdminV2; see ChatAccessSettingsController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_access_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('admin_quorum')->default(3);
            $table->timestamps();
        });

        DB::table('chat_access_settings')->insert([
            'admin_quorum' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_access_settings');
    }
};
