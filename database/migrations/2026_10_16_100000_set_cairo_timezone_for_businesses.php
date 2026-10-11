<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every business that never chose a timezone is on Cairo's clock. Until now none had one, so each was judged in UTC —
 * three hours behind its own customers: «open now», a walk-in's «now», the queue and the day grids all read the
 * wrong hour. A business that did set its own zone is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('type', 'business')
            ->where(fn ($q) => $q->whereNull('timezone')->orWhere('timezone', ''))
            ->update(['timezone' => (string) (config('app.business_timezone') ?: 'Africa/Cairo')]);
    }

    public function down(): void
    {
        // not reversible: the zone a business had before is "none", and Cairo is also a deliberate choice for some
    }
};
