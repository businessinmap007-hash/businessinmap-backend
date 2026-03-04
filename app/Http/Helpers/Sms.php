<?php
/**
 * Created by PhpStorm.
 * User: Hassan Saeed
 * Date: 9/18/2017
 * Time: 1:32 PM
 */

namespace App\Http\Helpers;


class Sms
{


    public static function sendMessage($message, $recepientNumber)
    {


        $getdata = http_build_query(
            $fields = array(
                "Username" => "s12-Dietdish",
                "Password" => "Dietdish@2018",
                "Message" => $message,
                "RecepientNumber" => $recepientNumber,
                "ReplacementList" => "",
                "SendDateTime" => "0",
                "EnableDR" => False,
                "Tagname" => "Dietdish",
                "VariableList" => "0"
            ));

        $opts = array('http' =>
            array(
                'method' => 'GET',
                'header' => 'Content-type: application/x-www-form-urlencoded',

            )
        );

        $context = stream_context_create($opts);

        $results = file_get_contents('http://api.yamamah.com/SendSMSV2?' . $getdata, false, $context);


        return $results;


//         //$encodedPostFields = json_encode($postFields);

//         // $data = "https://sms.gateway.sa/api/sendsms.php?username=@user&password=@pass&message=test&numbers=@mo;"

//         $uname = "Athathec";
//         $password = "athathec2018";
//         $num = '966'.(int) $recepientNumber;
//         $data = "user=$uname&password=$password&msg=$message&msisdn=$num&sid=Athathec&fl=0";

//         //open connection

// //       return  file_get_contents("https://sms.gateway.sa/api/sendsms.php?username=DcapSMS&password=Dc@2017#&message=ASAsaS&numbers=$recepientNumber&sender=DcapSMS");


//         $url = 'http://apps.gateway.sa/vendorsms/pushsms.aspx';
//         //$url = 'https://sms.gateway.sa/api/sendsms.php';
//         $ch = curl_init($url);

//         curl_setopt($ch, CURLOPT_POST, true);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


//         //execute post
//         $result = curl_exec($ch);
//         //close connection
//         curl_close($ch);

//         return $result;


        // $url = 'http://api.yamamah.com/SendSMS';
        // $fields = array(
        //     "Username" => "966561780944",
        //     "Password" => "Saned@20018",
        //     "Message" => $message,
        //     "RecepientNumber" => '966'. (int)$recepientNumber,
        //     "ReplacementList" =>"",
        //     "SendDateTime" => "0",
        //     "EnableDR" =>False,
        //     "Tagname"=>"Athathec",
        //     "VariableList"=>"0"
        // );

        // $fields_string=json_encode($fields);


// //open connection
//         $ch = curl_init($url);
//         curl_setopt_array($ch, array(
//             CURLOPT_POST => TRUE,
//             CURLOPT_RETURNTRANSFER => TRUE,
//             CURLOPT_HTTPHEADER => array(
//                 'Content-Type: application/json'
//             ),
//             CURLOPT_POSTFIELDS => $fields_string
//         ));


// //execute post
//         $result = curl_exec($ch);
//         // echo $result;
// //close connection
//         curl_close($ch);


    }


}