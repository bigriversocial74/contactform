<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/creator-campaigns/definitions.php';
require_once $root . '/includes/creator-campaigns/validation.php';

$files = [
    'migration' => $root . '/database/20260721_creator_campaign_native_foundation_v1.sql',
    'entrypoint' => $root . '/includes/creator-campaigns.php',
    'definitions' => $root . '/includes/creator-campaigns/definitions.php',
    'validation' => $root . '/includes/creator-campaigns/validation.php',
    'repository' => $root . '/includes/creator-campaigns/repository.php',
    'context' => $root . '/includes/creator-campaigns/context.php',
    'audit' => $root . '/includes/creator-campaigns/audit.php',
    'status_service' => $root . '/includes/creator-campaigns/status-service.php',
    'eligibility_service' => $root . '/includes/creator-campaigns/eligibility-service.php',
    'campaign_service' => $root . '/includes/creator-campaigns/campaign-service.php',
    'manifest' => $root . '/config/migrations.php',
    'workflow' => $root . '/.github/workflows/creator-campaign-native-foundation-v1.yml',
    'alignment' => $root . '/docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE1_REPOSITORY_ALIGNMENT.md',
];

$failures = [];
$scores = [
    'schema_and_ownership' => 0,
    'identity_and_authorization' => 0,
    'lifecycle_and_concurrency' => 0,
    'service_boundaries' => 0,
    'validation_and_delivery' => 0,
];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$content = [];
foreach ($files as $key => $path) {
    $assert(is_file($path), 'Missing required file: ' . str_replace($root . '/', '', $path));
    $content[$key] = is_file($path) ? (string) file_get_contents($path) : '';
}

$migrationChecks = [
    'CREATE TABLE IF NOT EXISTS creator_campaigns',
    'CREATE TABLE IF NOT EXISTS creator_campaign_products',
    'CREATE TABLE IF NOT EXISTS creator_campaign_eligibility_rules',
    'CREATE TABLE IF NOT EXISTS creator_campaign_status_events',
    'workspace_id BIGINT UNSIGNED NOT NULL',
    'creation_idempotency_hash CHAR(64) NOT NULL',
    'lock_version INT UNSIGNED NOT NULL DEFAULT 1',
    'REFERENCES merchant_workspaces(id)',
    'REFERENCES catalog_products(id)',
    'REFERENCES catalog_product_versions(id)',
    'merchant.creator_campaigns.manage',
    'merchant.creator_campaigns.publish',
    'merchant.creator_directory.view',
];
foreach ($migrationChecks as $needle) {
    $assert(str_contains($content['migration'], $needle), 'Migration contract missing: ' . $needle);
}
if ($failures === []) {
    $scores['schema_and_ownership'] = 20;
}

$identityChecks = [
    'mg_creator_campaign_require_active_merchant_model',
    "mg_user_has_active_model(\$userId, 'merchant')",
    "mg_current_active_model_context(\$userId)",
    'Cross-workspace creator campaign access is not allowed.',
    'mg_creator_campaign_creator_eligibility',
    "um.code='creator'",
    "cp.status creator_profile_status",
    'mg_creator_campaign_require_permission',
    'mg_creator_campaign_workspace_role_allows',
    "empty(\$context['merchant_access'])",
    "SELECT status FROM users WHERE id=? LIMIT 1",
];
$identityText = $content['context'] . $content['entrypoint'];
$identityStart = count($failures);
foreach ($identityChecks as $needle) {
    $assert(str_contains($identityText, $needle), 'Identity/authorization contract missing: ' . $needle);
}
$assert(!str_contains($content['context'], "um.code='marketing_affiliate'"), 'Affiliate identities must not satisfy Creator eligibility.');
if (count($failures) === $identityStart) {
    $scores['identity_and_authorization'] = 20;
}

$lifecycleStart = count($failures);
$expectedTransitions = [
    ['draft', 'scheduled', true],
    ['draft', 'active', true],
    ['active', 'paused', true],
    ['paused', 'active', true],
    ['completed', 'archived', true],
    ['archived', 'active', false],
    ['draft', 'completed', false],
    ['cancelled', 'active', false],
];
foreach ($expectedTransitions as [$from, $to, $expected]) {
    $assert(
        mg_creator_campaign_can_transition($from, $to) === $expected,
        sprintf('Unexpected lifecycle transition result: %s -> %s', $from, $to)
    );
}
foreach (['expected_lock_version', 'lock_version=lock_version+1', 'idempotency_hash', 'creator_campaign_status_events', 'mg_creator_campaign_assert_publish_ready', "relationship_type<>'excluded'"] as $needle) {
    $assert(str_contains($content['status_service'], $needle), 'Lifecycle/concurrency contract missing: ' . $needle);
}
$assert(str_contains($content['repository'], 'FOR UPDATE'), 'Lifecycle/concurrency contract missing: FOR UPDATE');
$assert(!preg_match('/UPDATE\s+creator_campaign_status_events/i', $content['status_service']), 'Status events must be append-only.');
$assert(!preg_match('/DELETE\s+FROM\s+creator_campaign_status_events/i', $content['status_service']), 'Status events must never be deleted.');
if (count($failures) === $lifecycleStart) {
    $scores['lifecycle_and_concurrency'] = 20;
}

$serviceStart = count($failures);
foreach ([
    'mg_creator_campaign_create_draft',
    'mg_creator_campaign_update_draft',
    'mg_creator_campaign_attach_product',
    'mg_creator_campaign_replace_eligibility_rules',
    'mg_creator_campaign_transition_status',
    'mg_creator_campaign_repository_assert_product_owned',
    'mg_creator_campaign_repository_assert_asset_owned',
    'mg_creator_campaign_record_audit',
    'mg_creator_campaign_assert_transaction_boundary',
] as $needle) {
    $serviceText = implode("\n", [
        $content['campaign_service'], $content['eligibility_service'], $content['status_service'],
        $content['repository'], $content['audit'], $content['definitions'],
    ]);
    $assert(str_contains($serviceText, $needle), 'Service boundary missing: ' . $needle);
}
$legacySqlPatterns = [
    '/\bFROM\s+campaigns\b/i',
    '/\bINTO\s+campaigns\b/i',
    '/\bUPDATE\s+campaigns\b/i',
    '/\bJOIN\s+campaigns\b/i',
];
foreach ($legacySqlPatterns as $pattern) {
    $assert(!preg_match($pattern, implode("\n", $content)), 'Creator Campaign foundation must not write the legacy campaigns table.');
}
if (count($failures) === $serviceStart) {
    $scores['service_boundaries'] = 20;
}

$deliveryStart = count($failures);
$assert(str_contains($content['manifest'], "'20260721_creator_campaign_native_foundation_v1.sql'"), 'Migration is missing from config/migrations.php.');
$assert(str_contains($content['workflow'], 'composer migrate'), 'Workflow must validate the canonical migration chain.');
$assert(str_contains($content['workflow'], "php: ['8.2','8.3']"), 'Workflow must validate PHP 8.2 and 8.3.');
$assert(str_contains($content['workflow'], 'validate_creator_campaign_native_foundation_v1.php'), 'Workflow must run the scored foundation validator.');
$assert(str_contains($content['alignment'], 'Out of scope'), 'Repository alignment document must preserve the Phase 1 boundary.');

try {
    $sample = mg_creator_campaign_normalize_create_input([
        'title' => 'Summer Creator Campaign',
        'timezone' => 'America/Phoenix',
        'starts_at' => '2026-08-01 09:00:00',
        'ends_at' => '2026-08-31 17:00:00',
        'access_mode' => 'hybrid',
        'idempotency_key' => 'validator-create-0001',
    ]);
    $assert($sample['title'] === 'Summer Creator Campaign', 'Create input normalization failed.');
    $assert($sample['starts_at'] === '2026-08-01 16:00:00', 'Timezone normalization must persist UTC datetimes.');
} catch (Throwable $error) {
    $failures[] = 'Create input validation failed unexpectedly: ' . $error->getMessage();
}

try {
    mg_creator_campaign_normalize_create_input([
        'title' => 'Invalid Campaign',
        'starts_at' => '2026-08-31 17:00:00',
        'ends_at' => '2026-08-01 09:00:00',
        'idempotency_key' => 'validator-create-0002',
    ]);
    $failures[] = 'Invalid campaign date ordering was accepted.';
} catch (InvalidArgumentException) {
    // Expected.
}

if (count($failures) === $deliveryStart) {
    $scores['validation_and_delivery'] = 20;
}

$total = array_sum($scores);
$result = [
    'ok' => $failures === [],
    'score' => $total,
    'score_out_of' => 100,
    'sections' => $scores,
    'failures' => $failures,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failures === [] ? 0 : 1);
