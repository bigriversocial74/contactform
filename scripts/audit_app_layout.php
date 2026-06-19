<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$targets = [];
foreach ([$root . '/*.php', $root . '/admin/*.php', $root . '/account/*.php'] as $pattern) {
    foreach (glob($pattern) ?: [] as $path) {
        if (is_file($path)) {
            $targets[] = $path;
        }
    }
}
$targets = array_values(array_unique($targets));
sort($targets);

$appModes = ['agent', 'account', 'crm', 'builder'];
$rows = [];
$failures = [];

foreach ($targets as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $source = (string) file_get_contents($path);

    $mode = null;
    if (preg_match('/\$header_mode\s*=\s*[\'\"](agent|account|crm|builder|public)[\'\"]/', $source, $match)) {
        $mode = $match[1];
    }

    $usesSharedHeader = str_contains($source, 'includes/header.php');
    $usesSharedFooter = str_contains($source, 'includes/footer.php');
    $delegates = !$usesSharedHeader && preg_match('/require(?:_once)?\s+[^;]+\/(?:account|agent|builder|build)\.php[\'\"]?\s*;/', $source) === 1;
    $authenticated = $mode !== null && in_array($mode, $appModes, true);
    $authenticated = $authenticated
        || str_contains($source, 'mg_require_auth(')
        || str_contains($source, 'mg_require_api_user(');

    $hasShell = preg_match('/mg-(?:app-shell|account-layout|builder-shell|commerce-page)/', $source) === 1;
    $hasSidebar = preg_match('/mg-(?:app-sidebar|account-sidebar)/', $source) === 1
        || str_contains($source, 'agent-sidebar.php')
        || str_contains($source, 'account-sidebar.php')
        || str_contains($source, 'admin-sidebar.php');
    $standaloneHtml = preg_match('/<!doctype\s+html/i', $source) === 1;

    $status = 'skip';
    $reason = 'Not an authenticated shared-layout entry page.';

    if ($delegates) {
        $status = 'delegated';
        $reason = 'Delegates rendering to another shared-layout entry page.';
    } elseif ($authenticated && $standaloneHtml && !$usesSharedHeader) {
        $status = 'broken';
        $reason = 'Authenticated standalone document bypasses the shared header and sidebar templates.';
    } elseif ($authenticated && $usesSharedHeader) {
        $missing = [];
        if (!$usesSharedFooter) {
            $missing[] = 'shared footer';
        }
        if (!$hasShell) {
            $missing[] = 'app shell';
        }
        if (!$hasSidebar) {
            $missing[] = 'sidebar';
        }
        if ($missing !== []) {
            $status = 'broken';
            $reason = 'Missing ' . implode(', ', $missing) . '.';
        } else {
            $status = 'ok';
            $reason = 'Uses the shared authenticated header, app shell, sidebar, and footer.';
        }
    }

    $row = [
        'page' => $relative,
        'mode' => $mode,
        'status' => $status,
        'reason' => $reason,
    ];
    $rows[] = $row;
    if ($status === 'broken') {
        $failures[] = $row;
    }
}

if (in_array('--json', $argv, true)) {
    echo json_encode([
        'pages' => $rows,
        'failures' => $failures,
        'summary' => [
            'scanned' => count($rows),
            'broken' => count($failures),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} else {
    echo "Authenticated page layout audit\n";
    echo str_repeat('=', 31) . "\n";
    foreach ($rows as $row) {
        if (!in_array($row['status'], ['ok', 'broken'], true)) {
            continue;
        }
        printf("%-7s %-36s %s\n", strtoupper($row['status']), $row['page'], $row['reason']);
    }
    echo "\nScanned: " . count($rows) . " | Broken: " . count($failures) . "\n";
}

exit($failures === [] ? 0 : 1);
