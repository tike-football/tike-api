<?php

return [
    'admin' => [
        'scopes' => [
            'test:test',
            'user:update-password',
            'user:get'
        ],
    ],
    'user' => [
        'scopes' => [
            'test:test',
            'user:update-password',
            'user:get'
        ],
    ],
    'unverified_user' => [
        'scopes' => [
            'user:verify',
            'user:recover-password'
        ],
    ],
];
