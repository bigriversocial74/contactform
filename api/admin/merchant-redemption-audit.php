<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array('super_admin', $roles, true) && !in_array('merchant.gifts.redeem', $permissions, true)) {
    mg_fail('Permission denied.', 403);
}

$root = dirname(__DIR__, 2);
$checks = [];
$pass = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'pass','message'=>$message,'context'=>$context]; };
$fail = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'fail','message'=>$message,'context'=>$context]; };

$files = [
    'merchant-locations.php',
    'api/merchant/locations.php',
    'api/merchant/_claims.php',
    'api/merchant/claim-codes.php',
    'api/merchant/claim-code-action.php',
    'api/merchant/scanner-claim.php',
    'api/gifts/verify-merchant-claim.php',
    'api/gifts/redeem-merchant-claim.php',
    'includes/agent-sidebar.php',
    'assets/js/agent-tools.js',
    'assets/css/agent-workspace-layout.css',
];
foreach ($files as $file) {
    is_file($root . '/' . $file) ? $pass('File exists: ' . $file) : $fail('Missing file: ' . $file);
}

$contracts = [
    'api/merchant/scanner-claim.php' => [
        'merchant.gifts.redeem',
        'require_confirmation',
        'confirmed',
        'needs_confirmation',
        'merchant_claim_code_id',
        'usage_count=usage_count+1',
        'gift.scanner_claim_redeemed',
        'links',
    ],
    'api/merchant/locations.php' => [
        'has_active_claim_code',
        'claim_code_last4',
        'hash_hmac',
        'merchant_user_id',
    ],
    'api/merchant/claim-codes.php' => [
        'mg_claim_code_normalize_input',
        'mg_claim_code_assert_unique',
        'is_currently_valid',
        'hash_hmac',
    ],
    'api/merchant/claim-code-action.php' => [
        'mg_claim_code_action_normalize_code',
        'mg_claim_code_action_assert_unique',
        'rotated',
        'revoked',
    ],
    'assets/js/agent-tools.js' => [
        'data-scanner-location',
        'data-scanner-two-step',
        'data-scanner-confirm-claim',
        'BarcodeDetector',
        'scanner-claim.php',
    ],
    'assets/css/agent-workspace-layout.css' => [
        'z-index:20000',
        'mg-scanner-confirm',
        'mg-scanner-location-note',
    ],
];
foreach ($contracts as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) continue;
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        str_contains($content, $needle) ? $pass('Contract found in ' . $file . ': ' . $needle) : $fail('Missing contract in ' . $file . ': ' . $needle);
    }
}

try {
    $pdo = mg_db();
    $tables = ['merchant_workspaces','merchant_locations','merchant_claim_codes','merchant_claim_code_events','gifts','gift_claims','gift_claim_attempts','gift_merchant_eligibility','notifications'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Table exists: ' . $table) : $fail('Missing table: ' . $table);
    }
    $columns = [
        'merchant_locations' => ['public_id','workspace_id','merchant_user_id','name','status','is_primary'],
        'merchant_claim_codes' => ['public_id','merchant_user_id','location_id','code_hash','code_last4','status','valid_from','valid_until','usage_limit','usage_count'],
        'gift_claims' => ['public_id','gift_id','location_id','merchant_claim_code_id','status','verified_by_user_id','redeemed_by_user_id','verified_at','redeemed_at','failed_attempts','locked_at'],
        'gift_claim_attempts' => ['claim_id','actor_user_id','successful','ip_hash','user_agent_hash'],
        'gift_merchant_eligibility' => ['gift_id','merchant_user_id','location_id'],
    ];
    foreach ($columns as $table => $cols) {
        foreach ($cols as $column) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table, $column]);
            ((int)$stmt->fetchColumn() > 0) ? $pass('Column exists: ' . $table . '.' . $column) : $fail('Missing column: ' . $table . '.' . $column);
        }
    }
    foreach (['merchant.locations.manage','merchant.claim_codes.manage','merchant.gifts.redeem'] as $permission) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
        $stmt->execute([$permission]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Permission exists: ' . $permission) : $fail('Missing permission: ' . $permission);
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM gift_claims LIKE 'status'");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $type = (string)($row['Type'] ?? '');
    foreach (['pending','verified','redeemed','cancelled','expired','locked'] as $status) {
        str_contains($type, "'" . $status . "'") ? $pass('Claim status supported: ' . $status) : $fail('Claim status missing: ' . $status, ['type'=>$type]);
    }
    $pepperReady = false;
    try {
        require_once dirname(__DIR__) . '/merchant/_claims.php';
        $pepper = mg_claim_code_pepper();
        $pepperReady = is_string($pepper) && strlen(trim($pepper)) >= 32;
    } catch (Throwable $error) {
        $fail('Claim-code pepper unavailable.', ['exception_class'=>$error::class, 'message'=>$error->getMessage()]);
    }
    $pepperReady ? $pass('Claim-code pepper is initialized.') : $fail('Claim-code pepper is not initialized.');
} catch (Throwable $error) {
    $fail('Database audit failed.', ['exception_class'=>$error::class, 'message'=>$error->getMessage()]);
}

$failures = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'fail'));
$score = count($checks) === 0 ? 0 : (int)round(((count($checks) - count($failures)) / count($checks)) * 10);
mg_ok([
    'score' => $score,
    'status' => count($failures) === 0 ? 'passed' : 'failed',
    'summary' => ['checks'=>count($checks), 'passed'=>count($checks)-count($failures), 'failed'=>count($failures)],
    'checks' => $checks,
], count($failures) === 0 ? 'Merchant location, claim code, and scanner redemption audit passed.' : 'Merchant redemption audit has failures.');
