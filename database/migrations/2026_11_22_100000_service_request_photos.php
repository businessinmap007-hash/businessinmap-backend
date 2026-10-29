<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «أحتاج فنيًا» — photos of the problem (owner, 2026-10-29): up to four, stored PRIVATELY and served only through a signed link to
 * the customer, to the craftsmen who may answer while the request is open, and to the one he chose. They are deleted, rows and
 * files, once the request is cancelled or expired, or once its visit is over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_request_id')->index();
            $table->string('path');
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_photos');
    }
};
