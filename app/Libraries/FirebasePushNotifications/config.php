<?php
/**
 * Created by PhpStorm.
 * User: Hassan Saeed
 * Date: 2/22/2018
 * Time: 4:14 PM
 */

namespace App\Libraries\FirebasePushNotifications;


class config
{

    public $key;

    public function __construct($key)
    {

        $this->key = "AAAAr1zlwXc:APA91bGOdtP2ALVuhEXVHeSKVBqAtZJYPzcnkZ0Fkc3mn6KniyA1Ftu4x25DpP7OE_ufGzL6yz5lERgsgBKgMSMyIvIdTsjxugCMCU3pwFT0lKMdkG4p46y-vi-LXKeP3VBTkemH0ZJH";
    }

    public function getKey()
    {
        return $this->key;
    }

}