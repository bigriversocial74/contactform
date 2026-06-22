<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'api/public/v1/_public.php',
    'api/public/v1/programs/index.php',
    'api/public/v1/account-link-start.php',
    'api/public/v1/account-links/start.php',
    'api/public/v1/account-link-complete.php',
    'api/public/v1/rewards/issue.php',
    'api/public/v1/rewards/status.php',
    'api/distribution/_issuance_worker.php',
    'api/distribution/issuance-worker.php',
    'scripts/run_distribution_issuance_worker.php',
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
