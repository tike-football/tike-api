<?php

return [

    'data' => [

        'default' => env('FOOTBALL_DRIVER', 'api_football'),

        'drivers' => [
            'api_football' => [
                'base_url' => env('API_FOOTBALL_BASE_URL', env('API_API_FOOTBALL_BASE_URL')),
                'api_key'  => env('API_FOOTBALL_KEY'),
            ],
        ],

    ],

];
