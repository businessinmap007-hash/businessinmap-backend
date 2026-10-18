<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An offer can point at the listing it is about (a dealer answering «أريد سيارة» with the car he has). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_request_offers', function (Blueprint $t) {
            $t->unsignedBigInteger('menu_item_id')->nullable()->after('eta_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('service_request_offers', function (Blueprint $t) {
            $t->dropColumn('menu_item_id');
        });
    }
};
