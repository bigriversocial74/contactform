<?php
declare(strict_types=1);

/*
 * Copy this file to api/config.local.php on the server using File Manager.
 * api/config.local.php is ignored by Git and should never be committed.
 *
 * Agent / Claude setup:
 * - Prefer setting MG_ANTHROPIC_API_KEY in the hosting environment.
 * - On cPanel/HostGator-style hosting without environment variable UI,
 *   paste the Claude / Anthropic credential into $mgAnthropicCredential below.
 * - Never paste a production provider credential into a public PHP page,
 *   JavaScript file, committed source file, or browser-visible form.
 */

$mgAnthropicCredential = 'PASTE_ANTHROPIC_CREDENTIAL_HERE';
if ($mgAnthropicCredential !== '' && $mgAnthropicCredential !== 'PASTE_ANTHROPIC_CREDENTIAL_HERE') {
    putenv('MG_ANTHROPIC_API_KEY=' . $mgAnthropicCredential);
}

$mgPaymentCredentialKey = 'PASTE_GENERATED_PAYMENT_CREDENTIAL_KEY_HERE';
if ($mgPaymentCredentialKey !== '' && $mgPaymentCredentialKey !== 'PASTE_GENERATED_PAYMENT_CREDENTIAL_KEY_HERE') {
    putenv('MG_PAYMENT_CREDENTIAL_KEY=' . $mgPaymentCredentialKey);
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
    'storage' => [
        // Use an absolute path outside public_html / the extracted release folder.
        // On cPanel this is commonly /home/YOUR-CPANEL-USER/microgifter-storage.
        'driver' => 'persistent_local',
        'root' => '/home/YOUR-CPANEL-USER/microgifter-storage',
        'public_endpoint' => '/api/public/media.php',
        'require_persistent' => true,
    ],
    'payments' => [
        // Generated from /admin-payments.php. This unlocks encrypted database storage for Stripe secret values.
        'credential_key' => $mgPaymentCredentialKey,
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