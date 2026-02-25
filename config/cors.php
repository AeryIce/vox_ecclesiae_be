<?php

return [

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    // aman buat local + bisa kamu set via env di Railway
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://127.0.0.1:3000'
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];