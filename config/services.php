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

    'paystack'=>[
        'base_url'=> getenv('PAYSTACK_BASE_URL'),
        'pk'=> getenv('PAYSTACK_PK'),
        'sk'=> getenv('PAYSTACK_SK'),
        'call_back' => getenv('PAYSTACK_CALLBACK'),
    ],
    'nomba'=>[
        'base_url'=> getenv('NOMBA_BASE_URL'),
        'account_id'=> getenv('NOMBA_ACCOUNT_ID'),
        'secret_key'=> getenv('NOMBA_CLIENT_SECRET'),
        'client_id'=> getenv('NOMBA_CLIENT_ID'),
    ],

    'dojah' => [
        'base_url' => env('DOJAH_BASE_URL', 'https://your-bvn-service-url.com'),
        'app_id' => env('DOJAH_APP_ID'),
        'secret_key' => env('DOJAH_SEC_KEY'),
        'public_key' => env('DOJAH_PUB_KEY'),
    ]

];
