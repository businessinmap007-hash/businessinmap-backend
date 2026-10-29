<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_adjustments` — see the attendance and payroll migration (2026_11_24). */
class Adjustment extends Model
{
    protected $table = 'hr_adjustments';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float'];
}
