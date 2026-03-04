<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question_ar',
        'question_en',
        'answer_ar',
        'answer_en',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];
}
