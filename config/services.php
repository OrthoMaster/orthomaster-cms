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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Fairplay
    'fairplay' => [
        'ask' => env('FAIRPLAY_ASK'),
        'certificate_path' => 'app/fairplay/fairplay.cer',

        'private_key_path' => 'app/fairplay/fairplay_1024_private.pem',
        'private_key_password' => env('FAIRPLAY_PRIVATE_KEY_PASSWORD'),

        'certification_path' => 'app/fairplay/fps_certificate.bin',
        'provisioning_path' => 'app/fairplay/provisioning_data.bin',
    ],

    'odoo' => [
        'host' => env('ODOO_HOST'),
        'database' => env('ODOO_DB'),
        'username' => env('ODOO_USERNAME'),
        'password' => env('ODOO_PASSWORD'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // 'key' => env('STRIPE_KEY_TEST'),
        // 'secret' => env('STRIPE_SECRET_TEST'),
        // 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET_TEST'),
    ],

    'google' => [
        'project_id' => env('GOOGLE_PROJECT_ID'),
        'agent_id'   => env('GOOGLE_AGENT_ID'),
        'location'   => 'global'
    ]

];
