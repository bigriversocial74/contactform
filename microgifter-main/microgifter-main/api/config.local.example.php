<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USER',
        'pass' => 'YOUR_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'env' => 'production',
        'debug' => false,
        'base_url' => 'https://YOUR-DOMAIN.com',
        'trust_proxy' => false,
    ],
    'runtime' => [
        'profile' => 'hostgator',
    ],
    'features' => [
        'polling_notifications' => true,
        'db_outbox' => true,
        'queue_worker' => false,
        'redis' => false,
        'websockets' => false,
        'sse' => false,
    ],
    'mail' => [
        'enabled' => false,
        'provider' => 'log',
        'from_email' => 'no-reply@YOUR-DOMAIN.com',
        'from_name' => 'Microgifter',
        'reply_to' => '',
    ],
];
