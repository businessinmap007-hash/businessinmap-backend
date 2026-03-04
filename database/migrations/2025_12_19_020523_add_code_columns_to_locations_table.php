<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->string('parent_code')->nullable()->after('parent_id');

            $table->unique('code');
            $table->index('parent_code');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropIndex(['parent_code']);

            $table->dropColumn(['code', 'parent_code']);
        });
    }
};
