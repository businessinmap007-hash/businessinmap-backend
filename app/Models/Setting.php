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
        // The legacy site-settings table is not present in every environment
        // (it was never migrated here). Degrade to null instead of a 1146 so the
        // shared layout — composed onto every view — still renders. Schema
        // lookups are cached, so this is cheap.
        if (! \Illuminate\Support\Facades\Schema::hasTable('settings')) {
            return null;
        }

        $option = Setting::where('key', $key)->first();
        return $option ? $option->body : null;
    }


}
