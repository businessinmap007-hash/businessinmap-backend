<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    //

    protected $primaryKey = 'key';
    protected $fillable = [
        'body', 'key'
    ];


    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array $attributes
     * @param  array $values
     * @return static
     */

    /**
     * If the register exists in the table, it updates it.
     * Otherwise it creates it
     * @param array $data Data to Insert/Update
     * @param array $keys Keys to check for in the table
     * @return Object
     */
    public static function createOrUpdate($data, $keys)
    {
        $record = self::where($keys)->first();
        if (is_null($record)) {
            return self::create($data);
        } else {
            return self::where($keys)->update($data);
        }
    }

    public static function getBody($key)
    {
        $option = Setting::where('key', $key)->first();
        return $option ? $option->body : null;
    }


}
