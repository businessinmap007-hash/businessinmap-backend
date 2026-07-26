<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegated staff on a business account: a person (any user) the business lets
 * manage its page — a clinic secretary, a shop or restaurant employee — limited
 * to a set of capabilities (orders, menu, working hours, projects, …). The
 * capability vocabulary is the App\Support\BusinessCapability registry.
 *
 * The owning business (the account itself) always has every capability and is
 * never a row here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index(); // the employer account
            $table->unsignedBigInteger('user_id')->index();     // the delegate

            $table->string('title', 120)->nullable();  // e.g. "سكرتيرة", "كاشير"
            $table->json('capabilities')->nullable();   // list of capability keys
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_staff');
    }
};
