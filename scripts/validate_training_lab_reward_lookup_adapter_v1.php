<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'service' => 'api/integrations/_training_lab_reward_lookup.php',
    'route' => 'api/integrations/training-lab-reward-lookup.php',
    'docs' => 'docs/STAGE-894-TRAINING-LAB-REWARD-LOOKUP-ADAPTER-V1.md',
    'workflow' => '.github/workflows/training-lab-reward-lookup-validation.yml',
];
$source = [];
foreach ($files as $key => $path) {
    $source[$key] = is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
}
$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};

$service = $source['service'];
$route = $source['route'];

$check($service !== '' && $route !== '', 'service and route exist');
$check(str_contains($route, "mg_require_method('POST')"), 'route requires POST');
$check(str_contains($route, 'application/json'), 'route requires JSON');
$check(str_contains($service, 'MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED'), 'feature flag exists');
$check(str_contains($service, 'MG_TRAINING_LAB_REWARD_LOOKUP_SECRET'), 'shared secret setting exists');
$check(str_contains($service, 'strlen($secret) >= 32'), 'shared secret minimum is enforced');
$check(str_contains($service, 'training-lab-reward-lookup-v1'), 'versioned signing contract exists');
$check(str_contains($service, "hash('sha256', \$rawBody)"), 'body digest is signed');
$check(str_contains($service, "hash_hmac('sha256'"), 'HMAC SHA-256 is used');
$check(str_contains($service, 'hash_equals($expected, $signature)'), 'signature comparison is constant time');
$check(str_contains($service, 'timestamp_expired'), 'timestamp window is enforced');
$check(str_contains($service, 'mg_idempotency_reserve($nonceKey'), 'nonce replay record is reserved');
$check(str_contains($service, 'training_lab_reward_reconciliation_v1'), 'reconciliation contract is required');
$check(str_contains($service, 'read_only_required'), 'read-only declaration is required');
$check(str_contains($service, 'microgifter_user_required'), 'Microgifter identity is required');
$check(substr_count($service, 'i.owner_user_id=? OR i.recipient_user_id=?') >= 2, 'both lookup routes are identity scoped');
$check(str_contains($service, 'WHERE i.idempotency_key=?'), 'idempotency-key lookup exists');
$check(str_contains($service, 'WHERE (i.public_id=?'), 'external-reference lookup exists');
$check(str_contains($service, 'lookup_reference_conflict'), 'reference conflicts are rejected');
$check(str_contains($service, "'delivery_status' => 'delivered'"), 'existing canonical instance confirms delivery');
$check(str_contains($service, "'lifecycle_status' => (string)\$row['status']"), 'current lifecycle is preserved');
$check(str_contains($service, "'status' => 'not_found'"), 'missing instance is explicit');
$check(str_contains($service, 'idempotency_key_hash'), 'audit hashes the idempotency reference');
$check(str_contains($service, 'external_reference_hash'), 'audit hashes the external reference');
$check(str_contains($route, "'lookup_failed'"), 'unexpected failures are generic');
$check(str_contains($source['docs'], 'No SQL required'), 'documentation states SQL status');
$check(str_contains($source['workflow'], "'8.2'") && str_contains($source['workflow'], "'8.3'"), 'workflow covers PHP 8.2 and 8.3');
$check(!is_file($root . '/database/stage894_training_lab_reward_lookup.sql'), 'no Stage 894 migration exists');

if ($failures) {
    fwrite(STDERR, "Stage 894 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 894 validation passed.\n";
