<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The store's terms (returns, minimum order, delivery…) as they stood when the order was placed — frozen,
 * so a later edit of the profile never rewrites what the customer agreed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'terms')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->json('terms')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'terms')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('terms');
            });
        }
    }
};
