<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A patient's sealed backup used to be a column, capped at 600 KB because megabytes in a row hit a database's packet
 * limit. New backups are private files (like the clinic's), up to 5 MB; rows written before keep their column until the
 * next backup replaces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_backups', function ($table) {
            $table->string('path')->nullable()->after('user_id');
            $table->unsignedInteger('bytes')->nullable()->after('path');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE medical_backups MODIFY `blob` MEDIUMTEXT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('medical_backups', function ($table) {
            $table->dropColumn(['path', 'bytes']);
        });
    }
};
