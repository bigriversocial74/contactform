<?php
declare(strict_types=1);

/*
 * Copy this file to api/config.local.php on the server using File Manager.
 * api/config.local.php is ignored by Git and should never be committed.
 *
 * Agent / Claude setup:
 * - Prefer setting MG_ANTHROPIC_API_KEY in the hosting environment.
 * - On cPanel/HostGator-style hosting without environment variable UI,
 *   paste the Claude / Anthropic key into $mgAnthropicApiKey below.
 * - Never paste a production AI key into a public PHP page, JavaScript file,
 *   committed source file, or browser-visible form.
 */

$mgAnthropicApiKey = 'PASTE_CLAUDE_ANTHROPIC_API_KEY_HERE';
if ($mgAnthropicApiKey !== '' && $mgAnthropicApiKey !== 'PASTE_CLAUDE_ANTHROPIC_API_KEY_HERE') {
    putenv('MG_ANTHROPIC_API_KEY=' . $mgAnthropicApiKey);
}

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