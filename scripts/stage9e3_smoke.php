<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$pdo = mg_db();
$errors = [];

function mg_stage9e3_smoke_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

function mg_stage9e3_smoke_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() === 1;
}

$requiredTables = [
    'users',
    'roles',
    'permissions',
    'commerce_orders',
    'commerce_order_items',
    'pppm_items',
    'pppm_item_events',
    'entitlements',
    'entitlement_transfers',
    'microgift_templates',
    'microgift_template_versions',
    'microgift_instances',
    'microgift_credentials',
    'microgift_claims',
    'microgift_redemptions',
    'microgift_review_items',
    'microgift_daily_metrics',
];

foreach ($requiredTables as $table) {
    if (!mg_stage9e3_smoke_table($pdo, $table)) {
        $errors[] = 'Missing required table after upgrade: ' . $table;
    }
}

$requiredColumns = [
    ['pppm_items', 'owner_user_id'],
    ['pppm_items', 'redeemed_at'],
    ['microgift_instances', 'pppm_item_id'],
    ['microgift_claims', 'entitlement_transfer_id'],
    ['microgift_redemptions', 'idempotency_key'],
    ['microgift_review_items', 'source_reference'],
];
foreach ($requiredColumns as [$table, $column]) {
    if (!mg_stage9e3_smoke_column($pdo, $table, $column)) {
        $errors[] = "Missing required column after upgrade: {$table}.{$column}";
    }
}

$permissionSlugs = [
    'microgift.templates.manage',
    'microgift.instances.issue',
    'microgift.claim',
    'microgift.redeem',
    'microgift.lifecycle.manage',
    'microgift.operations.view',
    'microgift.reviews.manage',
];
foreach ($permissionSlugs as $slug) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
    $stmt->execute([$slug]);
    if ((int)$stmt->fetchColumn() < 1) {
        $errors[] = 'Missing required permission after upgrade: ' . $slug;
    }
}

$contractFiles = [
    'docs/contracts/event_catalog_stage1_9.yaml',
    'docs/contracts/api_contracts_stage1_9.yaml',
    'docs/stages/stage_9e3_early_install_upgrade.md',
];
foreach ($contractFiles as $relative) {
    if (!is_file(dirname(__DIR__) . '/' . $relative)) {
        $errors[] = 'Missing required contract/doc file: ' . $relative;
    }
}

if ($errors !== []) {
    echo json_encode(['status' => 'failed', 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

echo json_encode(['status' => 'passed', 'message' => 'Stage 9E-3 early install upgrade smoke checks passed.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
