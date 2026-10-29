<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_holidays` — see the attendance and payroll migration (2026_11_24). */
class Holiday extends Model
{
    protected $table = 'hr_holidays';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];
}
