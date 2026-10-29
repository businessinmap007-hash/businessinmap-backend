<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_requests` — see the attendance and payroll migration (2026_11_24). */
class HrRequest extends Model
{
    protected $table = 'hr_requests';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'date_from' => 'date', 'date_to' => 'date', 'decided_at' => 'datetime'];
}
