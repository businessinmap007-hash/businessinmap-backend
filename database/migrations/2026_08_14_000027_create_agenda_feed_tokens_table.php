<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The secret token behind a user's calendar subscription URL
 * (…/agenda/feed/{token}.ics). Unguessable and rotatable: rotating replaces the
 * token, which silently invalidates the old subscription URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_feed_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('token', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_feed_tokens');
    }
};
