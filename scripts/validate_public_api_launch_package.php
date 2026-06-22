<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function mg_read_required(string $root, string $path): string
{
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $content;
}

function mg_check_contains(array &$checks, string $name, string $content, array $needles): void
{
    $missing = [];
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) $missing[] = $needle;
    }
    $checks[] = ['name'=>$name,'ok'=>count($missing)===0,'missing'=>$missing];
}

$checks = [];
$files = [
    'api/public/v1/_public.php',
    'api/public/v1/programs/index.php',
    'api/public/v1/account-link-start.php',
    'api/public/v1/sandbox/linked-account.php',
    'api/public/v1/rewards/issue.php',
    'api/public/v1/rewards/status.php',
    'api/distribution/_developer_webhooks.php',
    'api/merchant/developer-api-launch-qa.php',
    'api/merchant/developer-api-go-live.php',
    'developer-docs.php',
    'docs/stage-api-15-launch-package.md',
    'docs/public-api-launch-checklist.md',
    'docs/public-api-error-reference.md',
    'docs/public-api-webhook-verification-examples.md',
    'docs/public-api-sandbox-live-guide.md',
];

foreach ($files as $file) {
    $checks[] = ['name'=>'file:' . $file,'ok'=>is_file($root . '/' . $file),'missing'=>is_file($root . '/' . $file) ? [] : [$file]];
}

$public = mg_read_required($root, 'api/public/v1/_public.php');
mg_check_contains($checks, 'public api bearer-only auth', $public, [
    'Authorization',
    'Bearer ',
    'WWW-Authenticate: Bearer realm="Microgifter Public API"',
    'Missing public API bearer credential.',
    'X-RateLimit-Limit',
    'Retry-After',
]);

$webhooks = mg_read_required($root, 'api/distribution/_developer_webhooks.php');
mg_check_contains($checks, 'developer webhook signing', $webhooks, [
    'X-Microgifter-Signature-Version: v1',
    'X-Microgifter-Signature: ',
    'hash_hmac',
    'mg_dev_webhook_url_allowed',
    'Live webhook URL is blocked by environment policy.',
]);

$launchQa = mg_read_required($root, 'api/merchant/developer-api-launch-qa.php');
mg_check_contains($checks, 'launch qa blockers', $launchQa, [
    'Live-mode app',
    'Live credential',
    'Webhook signing key',
    'Required scopes',
    'ready_for_launch',
]);

$goLive = mg_read_required($root, 'api/merchant/developer-api-go-live.php');
mg_check_contains($checks, 'go-live controls', $goLive, [
    'clone_to_live',
    'promote_live',
    'create_live_credential',
    'mg_go_live_assert_promotable',
    'Live credential created. Copy it now; it will not be shown again.',
]);

$developerDocs = mg_read_required($root, 'developer-docs.php');
mg_check_contains($checks, 'public developer docs launch path', $developerDocs, [
    'Launch package',
    'sandbox linked-account',
    'Query-string credentials are not supported.',
    'Webhook verification examples',
    'Full error reference',
]);

$errorReference = mg_read_required($root, 'docs/public-api-error-reference.md');
mg_check_contains($checks, 'public error reference', $errorReference, [
    '401',
    '403',
    '409',
    '429',
    'Retry-After',
    'X-Idempotency-Key',
]);

$webhookExamples = mg_read_required($root, 'docs/public-api-webhook-verification-examples.md');
mg_check_contains($checks, 'webhook verification examples', $webhookExamples, [
    'X-Microgifter-Signature-Version',
    'hash_hmac',
    'crypto.createHmac',
    'timingSafeEqual',
]);

$launchChecklist = mg_read_required($root, 'docs/public-api-launch-checklist.md');
mg_check_contains($checks, 'launch checklist coverage', $launchChecklist, [
    'Live launch QA',
    'Authorization: Bearer <credential>',
    'Webhook handler verifies timestamp and signature.',
]);

$failed = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ok'])));
$result = ['ok'=>count($failed)===0,'checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
