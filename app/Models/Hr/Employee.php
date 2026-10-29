<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_employees` — see the attendance and payroll migration (2026_11_24). */
class Employee extends Model
{
    protected $table = 'hr_employees';

    protected $guarded = ['id'];

    protected $casts = ['allowances' => 'array', 'rest_days' => 'array', 'basic_salary' => 'float', 'insurable_wage' => 'float', 'insured' => 'boolean', 'taxable' => 'boolean', 'is_active' => 'boolean', 'hire_date' => 'date', 'birth_date' => 'date'];
}
