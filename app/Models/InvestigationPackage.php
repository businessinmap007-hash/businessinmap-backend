<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** «باقة»: a named set of a centre's tests sold at ONE price — «باقة الفحص الشامل». */
class InvestigationPackage extends Model
{
    protected $fillable = ['center_id', 'name', 'note', 'price', 'is_active'];

    protected $casts = ['price' => 'float', 'is_active' => 'boolean'];

    public function entries(): HasMany
    {
        return $this->hasMany(InvestigationPackageItem::class, 'package_id');
    }
}
