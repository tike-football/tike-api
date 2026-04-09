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
            'group:get',
            'football-data:sync',
            'football-data:cache',
            'football-data:get',
            'util:get',
            'admin:run-commands',
            'pool:add',
            'pool:join',
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
            'group:get',
            'football-data:get',
            'util:get',
            'pool:add',
            'pool:join',
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
