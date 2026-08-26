<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «مجموعة أبناء» — a shared platform-fee rate several children can point at
 * instead of each carrying its own. See `App\Models\FeeGroup`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_groups')) {
            return;
        }

        Schema::create('fee_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 191);

            $table->boolean('business_fee_enabled')->default(1);
            $table->string('business_fee_type', 20)->default('fixed');
            $table->decimal('business_fee_amount', 12, 2)->default(5.00);

            $table->boolean('client_fee_enabled')->default(1);
            $table->string('client_fee_type', 20)->default('fixed');
            $table->decimal('client_fee_amount', 12, 2)->default(1.00);

            $table->char('currency', 3)->default('EGP');
            $table->boolean('is_active')->default(1);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_groups');
    }
};
