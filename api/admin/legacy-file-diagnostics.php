<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

function mg_legacy_file_diag_root(): string
{
    return dirname(__DIR__, 2);
}

function mg_legacy_file_diag_normalize(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    return ltrim($path, '/');
}

function mg_legacy_file_diag_candidate_catalog(): array
{
    return [
        [
            'key' => 'root_index_content',
            'path' => 'index-content.php',
            'type' => 'file',
            'classification' => 'protected_active',
            'reason' => 'Current logged-out homepage presentation content. Preserve until index/auth-state tests and public landing routing are replaced.',
            'tokens' => ['index-content.php', '/index-content.php'],
        ],
        [
            'key' => 'microgifter_main_index',
            'path' => 'microgifter-main/index.php',
            'type' => 'file',
            'classification' => 'legacy_candidate',
            'reason' => 'Older nested homepage copy. Verify routing, includes, and deploy packaging before any file organization decision.',
            'tokens' => ['microgifter-main/index.php', 'microgifter-main/'],
        ],
        [
            'key' => 'microgifter_main_index_content',
            'path' => 'microgifter-main/index-content.php',
            'type' => 'file',
            'classification' => 'legacy_candidate',
            'reason' => 'Nested copy of index-content.php. Compare checksum and references before any file organization decision.',
            'tokens' => ['microgifter-main/index-content.php'],
        ],
        [
            'key' => 'landing_index_v3',
            'path' => 'includes/landing/index-v3',
            'type' => 'directory',
            'classification' => 'legacy_candidate',
            'reason' => 'Previously preserved landing variant. Confirm no include/deploy references before any file organization decision.',
            'tokens' => ['includes/landing/index-v3', 'landing/index-v3', 'index-v3'],
        ],
        [
            'key' => 'public_index',
            'path' => 'index.php',
            'type' => 'file',
            'classification' => 'protected_active',
            'reason' => 'Canonical public entry point. Used as comparison anchor only.',
            'tokens' => ['index.php'],
        ],
        [
            'key' => 'root_agentic_index_onboarding_test',
            'path' => 'tests/phpunit/AgenticIndexOnboardingTest.php',
            'type' => 'file',
            'classification' => 'protected_active',
            'reason' => 'Canonical PHPUnit coverage for the current public landing architecture.',
            'tokens' => ['tests/phpunit/AgenticIndexOnboardingTest.php', 'AgenticIndexOnboardingTest'],
        ],
        [
            'key' => 'nested_agentic_index_onboarding_test',
            'path' => 'microgifter-main/tests/phpunit/AgenticIndexOnboardingTest.php',
            'type' => 'file',
            'classification' => 'legacy_candidate',
            'reason' => 'Nested PHPUnit copy. Compare checksum and references against the canonical root test before deciding whether to sync or retire this nested test path.',
            'tokens' => ['microgifter-main/tests/phpunit/AgenticIndexOnboardingTest.php'],
        ],
    ];
}

function mg_legacy_file_diag_skip_dir(string $relativePath): bool
{
    $skip = [
        '.git', 'node_modules', 'vendor', 'storage', 'uploads', 'tmp', 'cache', '.idea', '.vscode', 'coverage', 'dist', 'build',
    ];
    $parts = explode('/', trim($relativePath, '/'));
    foreach ($parts as $part) {
        if (in_array($part, $skip, true)) return true;
    }
    return false;
}

function mg_legacy_file_diag_is_scannable_file(string $path): bool
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, ['php', 'js', 'css', 'html', 'htm', 'md', 'txt', 'json', 'yml', 'yaml'], true);
}

function mg_legacy_file_diag_hash_path(string $absolutePath, string $type): ?string
{
    if ($type !== 'file' || !is_file($absolutePath) || !is_readable($absolutePath)) return null;
    $size = filesize($absolutePath);
    if ($size === false || $size > 5242880) return null;
    $hash = hash_file('sha256', $absolutePath);
    return is_string($hash) ? $hash : null;
}

function mg_legacy_file_diag_dir_summary(string $absolutePath): array
{
    $summary = ['files' => 0, 'directories' => 0, 'bytes' => 0];
    if (!is_dir($absolutePath) || !is_readable($absolutePath)) return $summary;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) continue;
            if ($item->isDir()) {
                $summary['directories']++;
            } elseif ($item->isFile()) {
                $summary['files']++;
                $size = $item->getSize();
                if ($size > 0) $summary['bytes'] += $size;
            }
            if ($summary['files'] > 1000) break;
        }
    } catch (Throwable) {
        return $summary;
    }
    return $summary;
}

function mg_legacy_file_diag_scan_references(string $root, array $candidates): array
{
    $references = [];
    foreach ($candidates as $candidate) {
        $references[$candidate['key']] = [];
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $scanned = 0;
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) continue;
            $absolute = $item->getPathname();
            $relative = mg_legacy_file_diag_normalize(substr($absolute, strlen($root)));
            if ($relative === '' || mg_legacy_file_diag_skip_dir($relative) || !mg_legacy_file_diag_is_scannable_file($relative)) continue;
            $size = $item->getSize();
            if ($size < 1 || $size > 1048576) continue;
            $content = @file_get_contents($absolute);
            if (!is_string($content) || $content === '') continue;
            $scanned++;
            foreach ($candidates as $candidate) {
                if ($relative === $candidate['path']) continue;
                foreach (($candidate['tokens'] ?? []) as $token) {
                    if ($token === '' || strpos($content, (string)$token) === false) continue;
                    $references[$candidate['key']][] = [
                        'file' => $relative,
                        'token' => (string)$token,
                    ];
                    break;
                }
                if (count($references[$candidate['key']]) >= 20) continue;
            }
            if ($scanned >= 2500) break;
        }
    } catch (Throwable) {
        return $references;
    }

    foreach ($references as $key => $items) {
        $deduped = [];
        $seen = [];
        foreach ($items as $item) {
            $id = $item['file'] . '|' . $item['token'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $deduped[] = $item;
            if (count($deduped) >= 20) break;
        }
        $references[$key] = $deduped;
    }
    return $references;
}

function mg_legacy_file_diag_status(array $candidate, bool $exists, int $referenceCount, ?string $checksum, array $allChecksums): string
{
    if (($candidate['classification'] ?? '') === 'protected_active') {
        return $exists ? 'protected' : 'critical';
    }
    if (!$exists) return 'not_present';
    if ($referenceCount > 0) return 'referenced_review_required';
    if ($checksum !== null) {
        foreach ($allChecksums as $path => $hash) {
            if ($path !== $candidate['path'] && $hash === $checksum) return 'duplicate_candidate';
        }
    }
    return 'unreferenced_review_required';
}

function mg_legacy_file_diag_run(): array
{
    $root = realpath(mg_legacy_file_diag_root());
    if ($root === false) {
        mg_fail('Unable to inspect project root.', 500);
    }

    $candidates = mg_legacy_file_diag_candidate_catalog();
    $references = mg_legacy_file_diag_scan_references($root, $candidates);
    $checksums = [];
    $items = [];

    foreach ($candidates as $candidate) {
        $relative = mg_legacy_file_diag_normalize((string)$candidate['path']);
        $absolute = realpath($root . DIRECTORY_SEPARATOR . $relative);
        $exists = $absolute !== false && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR) && (($candidate['type'] ?? 'file') === 'directory' ? is_dir($absolute) : is_file($absolute));
        $checksum = $exists ? mg_legacy_file_diag_hash_path((string)$absolute, (string)($candidate['type'] ?? 'file')) : null;
        if ($checksum !== null) $checksums[$relative] = $checksum;
        $items[] = [
            'key' => $candidate['key'],
            'path' => $relative,
            'type' => $candidate['type'],
            'classification' => $candidate['classification'],
            'exists' => $exists,
            'checksum_sha256' => $checksum,
            'size_bytes' => ($exists && ($candidate['type'] ?? 'file') === 'file') ? (filesize((string)$absolute) ?: 0) : null,
            'directory_summary' => ($exists && ($candidate['type'] ?? 'file') === 'directory') ? mg_legacy_file_diag_dir_summary((string)$absolute) : null,
            'references' => $references[$candidate['key']] ?? [],
            'reference_count' => count($references[$candidate['key']] ?? []),
            'reason' => $candidate['reason'],
        ];
    }

    foreach ($items as &$item) {
        $item['status'] = mg_legacy_file_diag_status($item, (bool)$item['exists'], (int)$item['reference_count'], $item['checksum_sha256'], $checksums);
    }
    unset($item);

    $statusCounts = [];
    foreach ($items as $item) {
        $status = (string)$item['status'];
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    $deleteReady = array_values(array_filter($items, static function (array $item): bool {
        return false;
    }));

    return [
        'status' => 'review_required',
        'summary' => 'Legacy file diagnostics are read-only. No file is marked safe to delete automatically.',
        'generated_at' => gmdate('c'),
        'root' => $root,
        'items' => $items,
        'counts' => [
            'candidates' => count($items),
            'protected' => $statusCounts['protected'] ?? 0,
            'missing_protected' => $statusCounts['critical'] ?? 0,
            'referenced_review_required' => $statusCounts['referenced_review_required'] ?? 0,
            'duplicate_candidate' => $statusCounts['duplicate_candidate'] ?? 0,
            'unreferenced_review_required' => $statusCounts['unreferenced_review_required'] ?? 0,
            'not_present' => $statusCounts['not_present'] ?? 0,
            'delete_ready' => count($deleteReady),
        ],
        'delete_ready' => $deleteReady,
        'read_only' => true,
        'catalog_version' => '2026-07-05.legacy-file-diagnostics-v2',
    ];
}

try {
    mg_rate_limit('admin.legacy_file_diagnostics.read', 'user:' . (int)$user['id'], 30, 60);
    $data = mg_legacy_file_diag_run();
    mg_security_log('info', 'admin.legacy_file_diagnostics.viewed', 'Legacy file diagnostics viewed.', [
        'candidates' => $data['counts']['candidates'] ?? 0,
        'delete_ready' => $data['counts']['delete_ready'] ?? 0,
        'catalog_version' => $data['catalog_version'] ?? null,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.legacy_file_diagnostics.failed', 'Legacy file diagnostics request failed.', [
        'exception_class' => $error::class,
        'message' => mb_substr($error->getMessage(), 0, 240),
    ], (int)$user['id']);
    mg_fail('Unable to run legacy file diagnostics.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
mg_ok($data, 'Legacy file diagnostics loaded.');
