<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This config controls the CORS middleware that Laravel uses. Origins are
    | restricted to FRONTEND_URL so the API is not publicly callable from
    | arbitrary domains. Never use '*' in production.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        env('APP_URL', 'http://localhost:8000'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'Origin'],

    'exposed_headers' => ['X-Token', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
