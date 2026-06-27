<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$path = 'examples/local-quest-rewards/admin-programs.php';
$file = $root . '/' . $path;
$content = is_file($file) ? (string)file_get_contents($file) : '';

$checks = [
    ['name' => 'admin programs file exists', 'ok' => is_file($file)],
    ['name' => 'requires admin auth', 'ok' => str_contains($content, 'lqr_admin_authed') && str_contains($content, 'admin.php')],
    ['name' => 'program admin title', 'ok' => str_contains($content, 'Distribution Program Admin') && str_contains($content, 'Merchant controls')],
    ['name' => 'program table', 'ok' => str_contains($content, 'Distribution Programs') && str_contains($content, 'Local Quest Demo Program')],
    ['name' => 'template mapping', 'ok' => str_contains($content, 'Reward template mapping') && str_contains($content, 'coffee_checkin') && str_contains($content, 'venue_checkin')],
    ['name' => 'developer app access', 'ok' => str_contains($content, 'Developer app access') && str_contains($content, 'Allowed flow')],
    ['name' => 'program qa checklist', 'ok' => str_contains($content, 'Program QA checklist') && str_contains($content, 'Reward issue tested') && str_contains($content, 'Wallet claim/report tested')],
    ['name' => 'links to related tools', 'ok' => str_contains($content, 'developer-starter.php') && str_contains($content, 'admin-developer-readiness.php') && str_contains($content, 'webhook-tools.php')],
];

$failed = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ok'])));
$result = ['ok' => count($failed) === 0, 'checks' => $checks, 'failed' => $failed, 'generated_at' => gmdate('c')];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
