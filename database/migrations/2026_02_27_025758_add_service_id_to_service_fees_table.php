<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_fees', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('code');
            $table->index(['service_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('service_fees', function (Blueprint $table) {
            $table->dropIndex(['service_id', 'is_active']);
            $table->dropColumn('service_id');
        });
    }
};