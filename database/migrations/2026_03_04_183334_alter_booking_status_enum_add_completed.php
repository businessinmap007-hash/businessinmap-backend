<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // عدّل القيم حسب الموجودة عندك فعلاً
        DB::statement("ALTER TABLE `bookings` 
            MODIFY `status` ENUM('pending','accepted','rejected','cancelled','in_progress','completed') 
            NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `bookings` 
            MODIFY `status` ENUM('pending','accepted','rejected','cancelled','in_progress') 
            NOT NULL DEFAULT 'pending'");
    }
};