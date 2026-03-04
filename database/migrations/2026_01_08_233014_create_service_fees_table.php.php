<?php

// database/migrations/2026_01_09_000002_create_service_fees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_fees', function (Blueprint $table) {
            $table->id();

            // مثال: delivery_platform_fee, limo_platform_fee
            $table->string('code', 100)->unique();

            // قيمة الرسوم الأساسية (مؤقتًا ثابتة)
            $table->decimal('amount', 12, 2)->default(0);

            // لاحقًا: يمكن إضافة rules json للمسافة/الوزن
            $table->json('rules')->nullable();

            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fees');
    }
};
