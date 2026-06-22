<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'api/public/v1/_public.php',
    'api/public/v1/programs/index.php',
    'api/public/v1/account-link-start.php',
    'api/public/v1/account-links/start.php',
    'api/public/v1/account-link-complete.php',
    'api/public/v1/sandbox/linked-account.php',
    'api/public/v1/rewards/issue.php',
    'api/public/v1/rewards/status.php',
    'api/distribution/_issuance_worker.php',
    'api/distribution/issuance-worker.php',
    'api/distribution/_developer_webhooks.php',
    'api/distribution/webhook-worker.php',
    'api/merchant/developer-webhook-test.php',
    'api/merchant/developer-api-launch-qa.php',
    'api/merchant/developer-api-go-live.php',
    'scripts/run_distribution_issuance_worker.php',
    'scripts/run_distribution_webhook_worker.php',
    'database/stage_public_distribution_api_webhooks.sql',
    'database/stage_public_distribution_api_quotas.sql',
    'database/stage_public_distribution_api_sandbox.sql',
    'docs/stage-api-5-developer-webhooks.md',
    'docs/stage-api-6-public-docs-examples.md',
    'docs/stage-api-7-rate-limits-quotas.md',
    'docs/stage-api-8-sandbox-flow.md',
    'docs/stage-api-9-dashboard-analytics.md',
    'docs/stage-api-10-developer-onboarding.md',
    'docs/stage-api-12-webhook-hardening.md',
    'docs/stage-api-12-webhook-secrets-hardening.md',
    'docs/stage-api-13-live-launch-qa.md',
    'docs/stage-api-14-go-live-controls.md',
    'assets/js/merchant-developer-api-analytics.js',
    'account-link.php',
    'developer-docs.php',
];

$ok = true;
$rows = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $rows[] = ['path' => $path, 'exists' => $exists];
}

echo json_encode(['ok' => $ok, 'files' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
