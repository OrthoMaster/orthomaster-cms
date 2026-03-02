<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:8081',
        'http://localhost:19006',
        'https://web.d2ogbd7sjbl5au.amplifyapp.com', // Version test
    ],
    'allowed_origins_patterns' => [
        '#^https://.*\.orthomaster\.fr$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];