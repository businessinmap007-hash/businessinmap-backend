<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own named groups of OTHER businesses ("محلات الخضار",
 * "مصانع الأثاث") — a reusable target list for wholesale/retail offers, so
 * naming the same circle of buyers on a restricted listing repeatedly
 * doesn't mean searching for each one every time. Mirrors ContactGroup,
 * whose members are friends for a shared cart; these members are
 * businesses, added either by search or from a business's own profile page
 * ("add to offers group"). See BusinessGroupMember for the membership rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index(); // the owner
            $table->string('name', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_groups');
    }
};
