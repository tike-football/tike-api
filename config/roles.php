<?php

return [
    'admin' => [
        'scopes' => ['test:test'],
    ],
    'user' => [
        'scopes' => [
            'test:test'
        ],
    ],
    'unverified_user' => [
        'scopes' => [
            'user:verify'
        ],
    ],
];
