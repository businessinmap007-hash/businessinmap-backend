<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One medicine line on a prescription: the drug, its dose, how much, and how to
 * take it. The pharmacy dispenses against these lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_id')->index();

            $table->string('name');                 // drug name
            $table->string('dosage')->nullable();   // e.g. 500mg
            $table->string('quantity')->nullable(); // e.g. 2 boxes / 20 tablets
            $table->string('instructions')->nullable(); // e.g. after meals, twice daily

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
