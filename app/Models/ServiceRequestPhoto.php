<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A photo of the problem on a «أحتاج فنيًا» request — private, see {@see \App\Services\Requests\RequestPhotos}. */
class ServiceRequestPhoto extends Model
{
    protected $fillable = ['service_request_id', 'path', 'sort'];

    protected $casts = ['service_request_id' => 'integer', 'sort' => 'integer'];
}
