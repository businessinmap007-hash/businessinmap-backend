<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/** Table `hr_payslips` — see the attendance and payroll migration (2026_11_24). */
class Payslip extends Model
{
    protected $table = 'hr_payslips';

    protected $guarded = ['id'];

    protected $casts = ['lines' => 'array', 'attendance' => 'array', 'gross' => 'float', 'deductions' => 'float', 'net' => 'float', 'employer_insurance' => 'float', 'paid_at' => 'datetime'];
}
