<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Keycloak como proveedor de identidad (OIDC). La URL pública la usa el navegador y la interna
    // el contenedor de Laravel; con proveedor 'local' se mantiene el login propio del sistema
    'keycloak' => [
        'proveedor' => env('AUTH_PROVEEDOR', 'local'),
        'url_publica' => env('KEYCLOAK_URL_PUBLICA', 'http://localhost:8080'),
        'url_interna' => env('KEYCLOAK_URL_INTERNA', 'http://keycloak:8080'),
        'realm' => env('KEYCLOAK_REALM', 'menina'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'menina-web'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
