<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::dropIfExists('address_translations');
    }

    public function down(): void
    {
        // لا حاجة لإرجاعه
    }
};
