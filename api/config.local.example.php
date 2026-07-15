<?php
declare(strict_types=1);

/*
 * Copy to api/config.local.php on the server. The destination file is ignored by Git.
 * Never place production credentials in public PHP pages, JavaScript, or committed files.
 */

$mgAnthropicCredential = 'PASTE_ANTHROPIC_CREDENTIAL_HERE';
if ($mgAnthropicCredential !== '' && $mgAnthropicCredential !== 'PASTE_ANTHROPIC_CREDENTIAL_HERE') {
    putenv('MG_ANTHROPIC_API_KEY=' . $mgAnthropicCredential);
}

$mgPaymentCredentialKey = 'PASTE_GENERATED_PAYMENT_CREDENTIAL_KEY_HERE';
if ($mgPaymentCredentialKey !== '' && $mgPaymentCredentialKey !== 'PASTE_GENERATED_PAYMENT_CREDENTIAL_KEY_HERE') {
    putenv('MG_PAYMENT_CREDENTIAL_KEY=' . $mgPaymentCredentialKey);
}

// Generate once with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
$mgMfaEncryptionKey = 'PASTE_BASE64_32_BYTE_MFA_KEY_HERE';
if ($mgMfaEncryptionKey !== '' && $mgMfaEncryptionKey !== 'PASTE_BASE64_32_BYTE_MFA_KEY_HERE') {
    putenv('MG_MFA_ENCRYPTION_KEY=' . $mgMfaEncryptionKey);
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
        'driver' => 'persistent_local',
        'root' => '/home/YOUR-CPANEL-USER/microgifter-storage',
        'public_endpoint' => '/api/public/media.php',
        'require_persistent' => true,
    ],
    'payments' => [
        'credential_key' => $mgPaymentCredentialKey,
    ],
    'security' => [
        'session_absolute_minutes' => 43200,
        'session_idle_minutes' => 720,
        'session_admin_absolute_minutes' => 480,
        'session_admin_idle_minutes' => 30,
        'session_rotation_minutes' => 15,
        'session_cookie_samesite' => 'Lax',
        'step_up_max_age_seconds' => 600,
        'email_verification_required' => true,
        'mfa_encryption_key' => $mgMfaEncryptionKey,
        'mfa_enforce_enrolled' => true,
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
        'enabled' => true,
        'provider' => 'mail',
        'from_email' => 'no-reply@YOUR-DOMAIN.com',
        'from_name' => 'Microgifter',
    ],
];
