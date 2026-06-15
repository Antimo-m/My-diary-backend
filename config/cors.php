<?php

$frontendUrl = env('FRONTEND_URL', 'http://127.0.0.1:5173');
$localOrigins = in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ? [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
    ]
    : [];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique([$frontendUrl, ...$localOrigins])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
