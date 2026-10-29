<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table `hr_payroll_runs` — see the attendance and payroll migration (2026_11_24). */
class PayrollRun extends Model
{
    protected $table = 'hr_payroll_runs';

    protected $guarded = ['id'];

    protected $casts = ['totals' => 'array', 'approved_at' => 'datetime'];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'run_id');
    }
}
