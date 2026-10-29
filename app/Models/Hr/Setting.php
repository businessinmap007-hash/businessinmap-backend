<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_settings` — see the attendance and payroll migration (2026_11_24). */
class Setting extends Model
{
    protected $table = 'hr_settings';

    protected $guarded = ['id'];

    protected $casts = ['late_tiers' => 'array', 'rest_days' => 'array', 'absence_day_fraction' => 'float', 'monthly_cap_days' => 'float', 'overtime_day_pct' => 'float', 'overtime_night_pct' => 'float'];
}
