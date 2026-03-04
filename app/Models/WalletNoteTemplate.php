<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WalletNoteTemplate extends Model
{
    protected $table = 'wallet_note_templates';

    protected $fillable = [
        'title','text','is_active','sort'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];
}