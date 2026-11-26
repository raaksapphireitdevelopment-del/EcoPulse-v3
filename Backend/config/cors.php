<?php
declare(strict_types=1);

/**
 * EcoPulse MVP V3 - CORS Configuration
 * Supports local XAMPP Apache, direct file:// origin checks, and localhost ports
 */

return [
    'allowed_origins' => [
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:80',
        'http://localhost:8000',
        'http://localhost:8080',
        'http://localhost:3000',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'http://ecopulse.local',
    ],
    'allow_credentials' => true,
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-Token',
        'X-Organization-Id',
        'Accept',
        'Origin',
    ],
    'exposed_headers' => ['X-CSRF-Token'],
    'max_age' => 86400,
];
