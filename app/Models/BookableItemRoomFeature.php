<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One feature (an option of the hotel's feature vocabulary: «إطلالة بحر») carried by one numbered room. */
class BookableItemRoomFeature extends Model
{
    protected $table = 'bookable_item_room_features';

    protected $fillable = ['room_id', 'option_id'];
}
