<?php
declare(strict_types=1);

/**
 * EcoPulse MVP V3 - Application Configuration
 */

return [
    'name' => 'EcoPulse Carbon Intelligence & Carbon Marketing',
    'version' => '3.0.0-mvp',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => (bool)(getenv('APP_DEBUG') ?: true),
    'url' => getenv('APP_URL') ?: 'http://localhost/ecopulse',
    'timezone' => 'UTC',
    'session_lifetime_seconds' => 7200, // 2 hours
];
