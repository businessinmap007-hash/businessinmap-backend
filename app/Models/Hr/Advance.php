<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_advances` — see the attendance and payroll migration (2026_11_24). */
class Advance extends Model
{
    protected $table = 'hr_advances';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float', 'installment' => 'float', 'remaining' => 'float', 'given_on' => 'date'];
}
