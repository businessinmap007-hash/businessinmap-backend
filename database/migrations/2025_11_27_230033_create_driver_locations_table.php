<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('driver_locations', function (Blueprint $table) {

            // أضف هنا الأعمدة الجديدة
            if (!Schema::hasColumn('driver_locations', 'status')) {
                $table->enum('status', ['online', 'offline'])->default('offline')->after('lng');
            }

            if (!Schema::hasColumn('driver_locations', 'updated_by')) {
                $table->bigInteger('updated_by')->unsigned()->nullable()->after('status');
            }

        });
    }

    public function down()
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            // حذف الأعمدة لو عملت rollback
            if (Schema::hasColumn('driver_locations', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('driver_locations', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
        });
    }
};
