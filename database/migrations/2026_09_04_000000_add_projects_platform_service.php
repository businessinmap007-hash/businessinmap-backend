<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "المشاريع" (project timeline tracking) becomes a real platform service —
 * until now BusinessCapability::PROJECTS was deliberately left ungated for
 * every business (owner decision, 2026-08-19: no data signal distinguished
 * a contractor from a marketing agency, so a guess-based gate would hide
 * what shouldn't be hidden). Owner reversed that 2026-09-04: a hotel
 * genuinely doesn't need project tracking, and the fix is to give it the
 * same category_platform_services signal every other gated service already
 * has, then assign it per category in a follow-up session — not to keep
 * guessing in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_services')->updateOrInsert(
            ['key' => 'projects'],
            [
                'name_ar' => 'متابعة المشاريع',
                'name_en' => 'Project Tracking',
                'is_active' => 1,
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('platform_services')->where('key', 'projects')->delete();
    }
};
