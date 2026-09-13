<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'size_id',
        'qty',
        'unit_price',
        'total_price',
        'options',
        'notes'
    ];

    protected $casts = [
        'options' => 'array'
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
