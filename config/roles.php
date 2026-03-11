<?php

return [
    'admin' => [
        'scopes' => [
            'test:test',
            'user:update-password',
            'user:get',
            'user:update',
            'friend:search',
            'football-data:sync',
            'football-data:cache',
            'football-data:get',
            'util:get'
        ],
    ],
    'user' => [
        'scopes' => [
            'test:test',
            'user:update-password',
            'user:get',
            'user:update',
            'friend:search',
            'football-data:get',
            'util:get'
        ],
    ],
    'unverified_user' => [
        'scopes' => [
            'user:verify',
            'user:recover-password'
        ],
    ],
    'refreshed_user' => [
        'scopes' => [
            'user:refresh-token'
        ],
    ],
];
