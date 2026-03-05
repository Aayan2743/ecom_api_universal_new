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

    'postmark'               => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend'                 => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses'                    => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack'                  => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shiprocket'             => [
        'email'    => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        'base_url' => env('SHIPROCKET_BASE_URL'),
    ],
    'whatsapp'               => [
        'enabled'  => env('WHATSAPP_ENABLED', false),
        'api_key'  => env('WHATSAPP_API_KEY'),
        'base_url' => env('WHATSAPP_BASE_URL'),
    ],
    'admin_whatsapp_numbers' => array_filter(
        array_map(
            'trim',
            explode(',', env('ADMIN_WHATSAPP_NUMBERS', ''))
        )
    ),
    'shipping'               => [

        'shiprocket' => [
            'enabled'  => env('SHIPROCKET_ENABLED', false),
            'base_url' => env('SHIPROCKET_BASE_URL'),
            'email'    => env('SHIPROCKET_EMAIL'),
            'password' => env('SHIPROCKET_PASSWORD'),
        ],

        'shipmozo'   => [
            'enabled'  => env('SHIPMOZO_ENABLED', false),
            'base_url' => env('SHIPMOZO_BASE_URL'),
            'api_key'  => env('SHIPMOZO_API_KEY'),
            'secret'   => env('SHIPMOZO_SECRET'),
        ],

        'xpressbees' => [
            'enabled'  => env('XPRESSBEES_ENABLED', false),
            'base_url' => env('XPRESSBEES_BASE_URL'),
            'api_key'  => env('XPRESSBEES_API_KEY'),
        ],

        'dtdc'       => [
            'enabled'  => env('DTDC_ENABLED', false),
            'base_url' => env('DTDC_BASE_URL'),
            'api_key'  => env('DTDC_API_KEY'),
        ],

        'delhivery'  => [
            'enabled'  => env('DELHIVERY_ENABLED', false),
            'base_url' => env('DELHIVERY_BASE_URL'),
            'api_key'  => env('DELHIVERY_API_KEY'),
        ],

        'ekart'      => [
            'enabled'  => env('EKART_ENABLED', false),
            'base_url' => env('EKART_BASE_URL'),
            'api_key'  => env('EKART_API_KEY'),
        ],
    ],

];
