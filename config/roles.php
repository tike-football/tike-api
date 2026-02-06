<?php

return [
    'admin' => [
        'scopes' => [
            'test:test',
            'user:update-password'
        ],
    ],
    'user' => [
        'scopes' => [
            'test:test',
            'user:update-password'
        ],
    ],
    'unverified_user' => [
        'scopes' => [
            'user:verify'
        ],
    ],
];
