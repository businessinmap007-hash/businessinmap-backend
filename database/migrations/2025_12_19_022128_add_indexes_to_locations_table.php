<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {

            // هذه موجودة بالفعل → لا نعيدها
            // code (UNIQUE)
            // parent_code

            // نضيف فقط ما ينقص
            $table->index('type', 'locations_type_index');
            $table->index('parent_id', 'locations_parent_id_index');

            // أهم index للأداء
            $table->index(
                ['type', 'parent_id'],
                'locations_type_parent_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_type_index');
            $table->dropIndex('locations_parent_id_index');
            $table->dropIndex('locations_type_parent_id_index');
        });
    }
};
