<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use willvincent\Rateable\Rateable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;

class Product extends Model implements TranslatableContract
{

    use Translatable;
    use Rateable;
    /**
     * @var array
     */
    public $with = ['category','images'];

    /**
     * @var array
     */
    protected $casts = [
        'sizes' => 'array',
        'colors' => 'array'
    ];

    /**
     * @var array
     */
    public $translatedAttributes = ['name', 'description', 'materials'];

    /**
     * @var array
     */
    protected $fillable = ['category_id','price' ,'is_featured', 'location_id', 'price_sale', 'image', 'quantity', 'sizes', 'colors'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function images()
    {
        return $this->morphMany('App\Models\Image', 'imageable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category(){
        return $this->belongsTo(Category::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country(){
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function offers(){
        return $this->hasMany(Offer::class);
    }

    public function carts(){
        return $this->hasMany(Cart::class);
    }

    public function ratings()
    {
        return $this->morphMany('willvincent\Rateable\Rating', 'rateable');
    }




}
