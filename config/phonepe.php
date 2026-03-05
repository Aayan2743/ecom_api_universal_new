<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PhonePe Environment
    |--------------------------------------------------------------------------
    | UAT or PROD
    */

    'env'            => env('PHONEPE_ENV', 'UAT'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Credentials
    |--------------------------------------------------------------------------
    */

    'merchant_id'    => env('PHONEPE_MERCHANT_ID'),
    'client_id'      => env('PHONEPE_CLIENT_ID'),
    'client_secret'  => env('PHONEPE_CLIENT_SECRET'),
    'client_version' => env('PHONEPE_CLIENT_VERSION', 1),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    */

    'base_url'       => env('PHONEPE_ENV') === 'PROD'
        ? 'https://api.phonepe.com/apis/pg'
        : 'https://api-preprod.phonepe.com/apis/pg-sandbox',

];
