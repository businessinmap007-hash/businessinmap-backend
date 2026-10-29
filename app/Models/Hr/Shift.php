<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_shifts` — see the attendance and payroll migration (2026_11_24). */
class Shift extends Model
{
    protected $table = 'hr_shifts';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
