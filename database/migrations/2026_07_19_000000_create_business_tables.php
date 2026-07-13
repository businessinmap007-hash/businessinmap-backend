<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant tables (BIM-13.3). Each table carries a permanent QR token; scanning
 * it joins the table's open shared cart or opens a new one. `orders.business_table_id`
 * tags the shared cart that belongs to a table (dine-in group order).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_tables')) {
            Schema::create('business_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string('label');
                $table->string('token', 64)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['business_id', 'is_active']);
                $table->foreign('business_id', 'business_tables_business_fk')
                    ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'business_table_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('business_table_id')->nullable()->after('booking_id');
                $table->index(['business_table_id', 'status']);
                $table->foreign('business_table_id', 'orders_business_table_fk')
                    ->references('id')->on('business_tables')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'business_table_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign('orders_business_table_fk');
                $table->dropColumn('business_table_id');
            });
        }

        Schema::dropIfExists('business_tables');
    }
};
