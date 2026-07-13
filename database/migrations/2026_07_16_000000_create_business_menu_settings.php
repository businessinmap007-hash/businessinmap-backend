<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business menu billing settings. Lets a restaurant owner declare whether
 * their menu prices already INCLUDE the service fee and/or tax (so they are not
 * added on top) — the default (both false) keeps the current behaviour of adding
 * both on top. See MenuBillingService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_menu_settings')) {
            Schema::create('business_menu_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->unique();
                $table->boolean('prices_include_service')->default(false);
                $table->boolean('prices_include_tax')->default(false);
                $table->timestamps();
                $table->foreign('business_id', 'business_menu_settings_business_fk')
                    ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_menu_settings');
    }
};
