<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_note_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);        // اسم يظهر في القائمة
            $table->string('text', 255);         // النص الفعلي للملاحظة (قصير)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique('title');
            $table->index(['is_active','sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_note_templates');
    }
};