<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateways (wallet top-up / money-in)
    |--------------------------------------------------------------------------
    | Credentials moved out of app/Libraries/Main.php (where they were hard
    | coded) into env. Fawry is the Egypt gateway; Apple Pay / Google Pay ride
    | on top of it via the hosted checkout — they are not separate gateways.
    */

    'payments' => [
        'default_gateway' => env('PAYMENTS_DEFAULT_GATEWAY', 'fawry'),
        'topup_min' => (float) env('WALLET_TOPUP_MIN', 10),
        'topup_max' => (float) env('WALLET_TOPUP_MAX', 50000),
    ],

    'fawry' => [
        'base_url' => env('FAWRY_BASE_URL', 'https://atfawry.com'),
        'merchant_code' => env('FAWRY_MERCHANT_CODE'),
        'security_key' => env('FAWRY_SECURITY_KEY'),
        'currency' => env('FAWRY_CURRENCY', 'EGP'),
        // Where Fawry redirects the customer's browser after payment (UX only).
        'return_url' => env('FAWRY_RETURN_URL'),
    ],

];
