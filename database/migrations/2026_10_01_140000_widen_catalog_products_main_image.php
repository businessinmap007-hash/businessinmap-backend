<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Wikimedia Commons thumbnail URL of a file with a non-Latin name
 * («삼성 갤럭시 A24») is percent-encoded and runs past 255 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->string('main_image', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not narrowed back: a longer URL already stored would be truncated.
    }
};
