<?php

return [
    'paths' => ['api/*', 'oauth/*', 'api/documentation', 'docs'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:4300',
        'http://127.0.0.1:4300',
        'https://totonomia.rockerstats.com',
        'https://localhost',        // Capacitor Android/iOS
        'capacitor://localhost',    // Capacitor iOS nativo
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
