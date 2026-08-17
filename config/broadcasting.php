<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcast Connection
    |--------------------------------------------------------------------------
    */

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'log' => [
            'driver' => 'log',
        ],

        'reverb' => [
            'driver' => 'reverb',
            'app_id' => env('REVERB_APP_ID'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'host' => env('REVERB_HOST'),
            'port' => env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
            'use_tls' => env('REVERB_SCHEME', 'https') === 'https',
        ],

    ],

];
