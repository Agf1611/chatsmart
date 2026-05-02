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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'github_updater' => [
        'repo_url' => env('GITHUB_UPDATER_REPO_URL', 'https://github.com/Agf1611/wagt.git'),
        'branch' => env('GITHUB_UPDATER_BRANCH', 'main'),
        'token' => env('GITHUB_UPDATER_TOKEN'),
        'exclude_paths' => array_filter(array_map('trim', explode(',', (string) env(
            'GITHUB_UPDATER_EXCLUDE_PATHS',
            '.env,storage/,bootstrap/cache/,vendor/,node_modules/,credentials/,public/storage/,database/database.sqlite'
        )))),
    ],

];
