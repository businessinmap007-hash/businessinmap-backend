<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('owner_listing_reports');
    }

    public function down(): void
    {
        Schema::create('owner_listing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_listing_id')->constrained('owner_listings')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['owner_listing_id', 'reporter_id']);
        });
    }
};
