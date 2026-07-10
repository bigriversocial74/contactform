<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_IDENTITY_LAUNCH_ENABLED=true');
putenv('TL_IDENTITY_SHARED_SECRET=stage887-test-secret-0123456789-abcdef');
putenv('TL_IDENTITY_ISSUER=microgifter.test');
putenv('TL_IDENTITY_AUDIENCE=training-lab-test');
putenv('TL_IDENTITY_TARGET_URL=https://labs.microgifter.test/account-link.php');
putenv('TL_IDENTITY_LAUNCH_TTL=120');

require_once $root . '/includes/training-lab-launch.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$decode = static function (string $part): array {
    $padding = (4 - (strlen($part) % 4)) % 4;
    $json = base64_decode(strtr($part . str_repeat('=', $padding), '-_', '+/'), true);
    return is_string($json) ? (json_decode($json, true) ?: []) : [];
};

$participant = [
    'id' => 42,
    'email' => 'participant@example.test',
    'display_name' => 'Participant Test',
    'roles' => ['customer'],
    'permissions' => [],
    'password' => 'must-not-leak',
    'password_hash' => 'must-not-leak',
];
$handoff = mg_training_lab_build_assertion(
    $participant,
    1700000000,
    'nonce-0123456789abcdef',
    'token-stage887-1',
    ['merchant' => 'merchant-public-7', 'organization' => 'Example Merchant']
);
$parts = explode('.', (string) $handoff['assertion']);
$assert(count($parts) === 3, 'Assertion must contain exactly three segments.');
$header = count($parts) === 3 ? $decode($parts[0]) : [];
$payload = count($parts) === 3 ? $decode($parts[1]) : [];
$assert(($header['alg'] ?? '') === 'HS256', 'Assertion algorithm must be HS256.');
$assert(($header['typ'] ?? '') === 'TL-ID', 'Assertion type must be TL-ID.');
$assert(($payload['iss'] ?? '') === 'microgifter.test', 'Issuer must match server configuration.');
$assert(($payload['aud'] ?? '') === 'training-lab-test', 'Audience must match server configuration.');
$assert(($payload['sub'] ?? '') === '42', 'Subject must come from the authenticated server user.');
$assert(($payload['role'] ?? '') === 'participant', 'Customer role must map to participant.');
$assert(($payload['merchant'] ?? '') === 'merchant-public-7', 'Server merchant context must be retained.');
$assert(($payload['organization'] ?? '') === 'Example Merchant', 'Server organization context must be retained.');
$assert(((int) ($payload['exp'] ?? 0) - (int) ($payload['iat'] ?? 0)) === 120, 'Assertion TTL must match the bounded launch TTL.');
$assert(!array_key_exists('password', $payload), 'Password must never be included in the assertion.');
$assert(!array_key_exists('password_hash', $payload), 'Password hash must never be included in the assertion.');

if (count($parts) === 3) {
    $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], 'stage887-test-secret-0123456789-abcdef', true);
    $providedPadding = (4 - (strlen($parts[2]) % 4)) % 4;
    $provided = base64_decode(strtr($parts[2] . str_repeat('=', $providedPadding), '-_', '+/'), true);
    $assert(is_string($provided) && hash_equals($expected, $provided), 'Assertion signature must verify with the configured shared secret.');
}

$assert(mg_training_lab_normalize_role(['roles' => ['super_admin'], 'permissions' => []]) === 'admin', 'Super admin must map to Training Lab admin.');
$assert(mg_training_lab_normalize_role(['roles' => ['merchant'], 'permissions' => []]) === 'manager', 'Merchant must map to Training Lab manager.');
$assert(mg_training_lab_normalize_role(['roles' => ['reviewer'], 'permissions' => []]) === 'reviewer', 'Reviewer must retain reviewer access.');
$assert(mg_training_lab_normalize_role(['roles' => ['unknown_role'], 'permissions' => []]) === 'participant', 'Unknown roles must downgrade to participant.');
$assert(mg_training_lab_target_is_safe('https://labs.microgifter.com/account-link.php'), 'Expected HTTPS Training Lab receiver must be allowed.');
$assert(!mg_training_lab_target_is_safe('http://labs.microgifter.com/account-link.php'), 'HTTP Training Lab receiver must be rejected.');
$assert(!mg_training_lab_target_is_safe('https://labs.microgifter.com/other.php'), 'Unexpected receiver paths must be rejected.');
$assert(!mg_training_lab_target_is_safe('https://user:pass@labs.microgifter.com/account-link.php'), 'Credential-bearing target URLs must be rejected.');

$launchRoute = file_get_contents($root . '/api/training-lab/launch.php') ?: '';
$launchPage = file_get_contents($root . '/training-lab.php') ?: '';
$assert(str_contains($launchRoute, "mg_require_method('POST')"), 'Launch endpoint must require POST.');
$assert(str_contains($launchRoute, 'mg_require_csrf_for_write'), 'Launch endpoint must enforce CSRF.');
$assert(str_contains($launchRoute, 'mg_require_api_user'), 'Launch endpoint must refresh and require the authenticated DB-backed session.');
$assert(str_contains($launchRoute, "name=\"identity_assertion\""), 'Launch endpoint must POST the expected Training Lab field.');
$assert(str_contains($launchPage, 'mg_require_auth'), 'Launch page must require a Microgifter account session.');
$assert(!preg_match('/\$_(?:GET|POST|REQUEST).*?(?:user|email|role|merchant)/is', $launchRoute), 'Launch endpoint must not read identity or role values from browser input.');

if ($failures) {
    fwrite(STDERR, "Stage 887 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 887 Training Lab signed launch validation passed.\n";
