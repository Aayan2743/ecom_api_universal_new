<?php


// config/payment.php
return [

    'razorpay' => [
        'key'     => env('RAZORPAY_KEY'),
        'secret'  => env('RAZORPAY_SECRET'),
        'enabled' => filter_var(env('RAZORPAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'cashfree' => [
        'app_id'  => env('CASHFREE_APP_ID'),
        'secret'  => env('CASHFREE_SECRET'),
        'enabled' => filter_var(env('CASHFREE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'phonepe'  => [
        'merchant_id'    => env('PHONEPE_MERCHANT_ID'),
        'client_id'      => env('PHONEPE_CLIENT_ID'),
        'client_secret'  => env('PHONEPE_CLIENT_SECRET'),
        'client_version' => env('PHONEPE_CLIENT_VERSION'),
        'base_url'       => env('PHONEPE_BASE_URL'),
        'enabled'        => filter_var(env('PHONEPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'payu'     => [
        'key'     => env('PAYU_KEY'),
        'salt'    => env('PAYU_SALT'),
        'enabled' => filter_var(env('PAYU_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'cod'      => [
        'enabled' => filter_var(env('COD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

];