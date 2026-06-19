<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);

/**
 * Collect an entry page and its statically resolvable local PHP includes.
 */
function mg_layout_collect_source(string $path, string $root, array &$visited, int $depth = 0): string
{
    $real = realpath($path);
    if ($real === false || !is_file($real) || !str_starts_with($real, $root) || isset($visited[$real]) || $depth > 8) {
        return '';
    }

    $visited[$real] = true;
    $source = (string) file_get_contents($real);
    $combined = $source;
    $currentDir = dirname($real);

    if (preg_match_all('/(?:require|include)(?:_once)?\s+([^;]+);/', $source, $statements)) {
        foreach ($statements[1] as $expression) {
            if (!preg_match_all('/[\'\"]([^\'\"]+\.php)[\'\"]/', $expression, $quoted) || $quoted[1] === []) {
                continue;
            }

            $fragment = (string) end($quoted[1]);
            if (str_contains($expression, 'dirname(__DIR__)')) {
                $base = dirname($currentDir);
            } elseif (str_contains($expression, '__DIR__')) {
                $base = $currentDir;
            } else {
                $base = $currentDir;
            }

            $candidate = $base . '/' . ltrim($fragment, '/');
            $candidateReal = realpath($candidate);
            if ($candidateReal === false || !str_starts_with($candidateReal, $root)) {
                continue;
            }

            $combined .= "\n" . mg_layout_collect_source($candidateReal, $root, $visited, $depth + 1);
        }
    }

    return $combined;
}

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
    $entrySource = (string) file_get_contents($path);
    $visited = [];
    $combinedSource = mg_layout_collect_source($path, $root, $visited);

    $mode = null;
    if (preg_match('/\$header_mode\s*=\s*[\'\"](agent|account|crm|builder|public)[\'\"]/', $entrySource, $match)) {
        $mode = $match[1];
    }

    $usesSharedHeader = str_contains($combinedSource, 'includes/header.php');
    $usesSharedFooter = str_contains($combinedSource, 'includes/footer.php');
    $delegates = !$usesSharedHeader && preg_match('/require(?:_once)?\s+[^;]+\/(?:account|agent|builder|build)\.php[\'\"]?\s*;/', $entrySource) === 1;
    $authenticated = $mode !== null && in_array($mode, $appModes, true);
    $authenticated = $authenticated
        || str_contains($entrySource, 'mg_require_auth(')
        || str_contains($entrySource, 'mg_require_api_user(');

    $hasShell = preg_match('/mg-(?:app-shell|account-layout|builder-shell|commerce-page)/', $combinedSource) === 1;
    $hasSidebar = preg_match('/mg-(?:app-sidebar|account-sidebar)/', $combinedSource) === 1
        || str_contains($combinedSource, 'agent-sidebar.php')
        || str_contains($combinedSource, 'account-sidebar.php')
        || str_contains($combinedSource, 'admin-sidebar.php');
    $standaloneHtml = preg_match('/<!doctype\s+html/i', $entrySource) === 1;

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
        'files_checked' => count($visited),
    ];
    $rows[] = $row;
    if ($status === 'broken') {
        $failures[] = $row;
    }
}

$summary = [
    'scanned' => count($rows),
    'authenticated_ok' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'ok')),
    'delegated' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'delegated')),
    'broken' => count($failures),
];

if (in_array('--json', $argv, true)) {
    echo json_encode([
        'pages' => $rows,
        'failures' => $failures,
        'summary' => $summary,
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
    echo "\nScanned: {$summary['scanned']} | Authenticated OK: {$summary['authenticated_ok']} | Broken: {$summary['broken']}\n";
}

exit($failures === [] ? 0 : 1);
