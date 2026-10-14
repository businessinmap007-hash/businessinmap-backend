<?php

use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الطبيب المعالج له صلاحيات أساسية» — المالك، 2026-10-19. Physicians marked before the role carried its bundle (the
 * visit, the prescription, the test orders, the decision to perform a procedure) get it now, limited to what their
 * hospital's account can hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('business_staff')->where('is_physician', true)->orderBy('id')->each(function ($row) {
            $business = User::query()->find($row->business_id);

            if (! $business) {
                return;
            }

            $current = json_decode((string) $row->capabilities, true);
            $current = is_array($current) ? $current : [];
            $merged = BusinessCapability::sanitizeFor($business, array_values(array_unique(array_merge($current, BusinessCapability::PHYSICIAN_BUNDLE))));

            if ($merged !== array_values($current)) {
                DB::table('business_staff')->where('id', $row->id)->update(['capabilities' => json_encode($merged)]);
            }
        });
    }

    public function down(): void
    {
        // the bundle is a grant, not a schema: nothing to take back
    }
};
