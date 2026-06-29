<?php
 declare(strict_types=1);

/**
 * Training Campaign Lab validation script.
 *
 * This checks the build package and catches missing docs/routes/assets/schema
 * before agents continue implementation work.
 *
 * Run from repo root:
 * php scripts/validate_training_campaign_lab.php
 */

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$passed = [];

function tclv_path(string $root, string $path): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function tclv_required_file(string $root, string $path, array &$errors, array &$passed): void
{
    if (!is_file(tclv_path($root, $path))) {
        $errors[] = "Missing required file: {$path}";
        return;
    }
    $passed[] = "Found {$path}";
}

function tclv_required_dir(string $root, string $path, array &$errors, array &$passed): void
{
    if (!is_dir(tclv_path($root, $path))) {
        $errors[] = "Missing required directory: {$path}";
        return;
    }
    $passed[] = "Found directory {$path}";
}

function tclv_file_contains(string $root, string $path, array $needles, array &$errors, array &$passed): void
{
    $full = tclv_path($root, $path);
    if (!is_file($full)) {
        $errors[] = "Cannot inspect missing file: {$path}";
        return;
    }
    $content = (string)file_get_contents($full);
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "File {$path} is missing expected text: {$needle}";
            continue;
        }
        $passed[] = "{$path} contains {$needle}";
    }
}

$requiredDocs = [
    'docs/training-campaign-lab/README.md',
    'docs/training-campaign-lab/documentation-completion-plan.md',
    'docs/training-campaign-lab/product-requirements.md',
    'docs/training-campaign-lab/branch-strategy.md',
    'docs/training-campaign-lab/build-plan.md',
    'docs/training-campaign-lab/route-map.md',
    'docs/training-campaign-lab/ui-data-map.md',
    'docs/training-campaign-lab/schema.md',
    'docs/training-campaign-lab/schema-install.md',
    'docs/training-campaign-lab/status-model.md',
    'docs/training-campaign-lab/data-lifecycle.md',
    'docs/training-campaign-lab/security-permissions.md',
    'docs/training-campaign-lab/admin-workflows.md',
    'docs/training-campaign-lab/participant-workflows.md',
    'docs/training-campaign-lab/implementation-tickets.md',
    'docs/training-campaign-lab/qa-test-script.md',
    'docs/training-campaign-lab/open-questions.md',
    'docs/training-campaign-lab/ui/ui-page-map.md',
    'docs/training-campaign-lab/ui/ui-layout-spec.md',
    'docs/training-campaign-lab/ui/component-inventory.md',
    'docs/training-campaign-lab/ui/responsive-rules.md',
    'docs/training-campaign-lab/ui/mockups.md',
];

$requiredRoutes = [
    'examples/local-quest-rewards/training-lab.php',
    'examples/local-quest-rewards/training-campaigns.php',
    'examples/local-quest-rewards/training-campaign-data.php',
    'examples/local-quest-rewards/assets/training-lab.css',
    'examples/local-quest-rewards/assets/training-lab.js',
];

$protectedLocalQuestFiles = [
    'examples/local-quest-rewards/index.php',
    'examples/local-quest-rewards/wallet.php',
    'examples/local-quest-rewards/quests.php',
    'examples/local-quest-rewards/admin.php',
    'examples/local-quest-rewards/admin-quest-controls.php',
    'examples/local-quest-rewards/quest-controls.php',
    'examples/local-quest-rewards/storage-sql.php',
    'examples/local-quest-rewards/webhook.php',
];

$schemaFiles = [
    'examples/local-quest-rewards/database/training_campaign_lab.sql',
    'examples/local-quest-rewards/database/training_campaign_lab_seed.sql',
];

foreach ($requiredDocs as $path) {
    tclv_required_file($root, $path, $errors, $passed);
}
foreach ($requiredRoutes as $path) {
    tclv_required_file($root, $path, $errors, $passed);
}
foreach ($protectedLocalQuestFiles as $path) {
    tclv_required_file($root, $path, $errors, $passed);
}
foreach ($schemaFiles as $path) {
    tclv_required_file($root, $path, $errors, $passed);
}

tclv_required_dir($root, 'docs/training-campaign-lab/ui/mockups', $errors, $passed);

$requiredTables = [
    'training_campaigns',
    'training_sequences',
    'training_tasks',
    'training_participants',
    'training_files',
    'training_task_submissions',
    'training_reviews',
    'training_action_receipts',
    'training_reward_rules',
    'training_reward_issues',
    'training_streaks',
    'training_events',
];

tclv_file_contains($root, 'examples/local-quest-rewards/database/training_campaign_lab.sql', $requiredTables, $errors, $passed);

tclv_file_contains($root, 'examples/local-quest-rewards/database/training_campaign_lab_seed.sql', [
    '5-Day Movement Challenge',
    'Coffee Shop Opening Routine',
    '14-Day Creator Practice Streak',
    'daily-movement-routine',
    'training_reward_rules',
], $errors, $passed);

tclv_file_contains($root, 'docs/training-campaign-lab/branch-strategy.md', [
    'local-quest-workspace',
    'Do not replace the existing Local Quest files',
], $errors, $passed);

tclv_file_contains($root, 'docs/training-campaign-lab/qa-test-script.md', [
    'Final vertical slice QA',
    'Original Local Quest app still works',
], $errors, $passed);

$expectedFutureRoutes = [
    'training-campaign-detail.php',
    'training-sequence.php',
    'training-proof-upload.php',
    'training-rewards.php',
    'admin-training-review.php',
    'admin-training-receipts.php',
];

$routeMap = tclv_path($root, 'docs/training-campaign-lab/route-map.md');
if (is_file($routeMap)) {
    $routeMapContent = (string)file_get_contents($routeMap);
    foreach ($expectedFutureRoutes as $route) {
        if (strpos($routeMapContent, $route) === false) {
            $warnings[] = "Route map does not mention expected future route: {$route}";
        } else {
            $passed[] = "Route map mentions {$route}";
        }
    }
}

echo "Training Campaign Lab validation\n";
echo str_repeat('=', 34) . "\n\n";

echo "Passed checks: " . count($passed) . "\n";
foreach ($passed as $line) {
    echo "  [OK] {$line}\n";
}

if ($warnings) {
    echo "\nWarnings: " . count($warnings) . "\n";
    foreach ($warnings as $line) {
        echo "  [WARN] {$line}\n";
    }
}

if ($errors) {
    echo "\nErrors: " . count($errors) . "\n";
    foreach ($errors as $line) {
        echo "  [FAIL] {$line}\n";
    }
    exit(1);
}

echo "\nValidation passed. Training Campaign Lab build package is ready for the next staged implementation phase.\n";
exit(0);
