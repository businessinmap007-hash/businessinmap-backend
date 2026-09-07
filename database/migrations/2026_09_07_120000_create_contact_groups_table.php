<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's own named contact groups ("العائلة", "أصدقاء دمياط") — a reusable
 * address book of already-registered friends, so sharing a cart with the
 * same circle of people repeatedly doesn't mean re-typing a phone/email
 * every time. See ContactGroupMember for the membership rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index(); // the owner
            $table->string('name', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_groups');
    }
};
