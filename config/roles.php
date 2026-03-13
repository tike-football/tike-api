<?php

return [
    'admin' => [
        'scopes' => [
            'test:test',
            'user:update-password',
            'user:get',
            'user:update',
            'friend:get',
            'friend:add',
            'group:add',
            'football-data:sync',
            'football-data:cache',
            'football-data:get',
            'util:get',
            'admin:run-commands'
        ],
    ],
    'user' => [
        'scopes' => [
            'test:test',
            'user:update-password',
            'user:get',
            'user:update',
            'friend:get',
            'friend:add',
            'group:add',
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
