<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_law_tables` — see the attendance and payroll migration (2026_11_24). */
class LawTable extends Model
{
    protected $table = 'hr_law_tables';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'effective_from' => 'date'];
}
