<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('GET');
$user = mg_screen_recordings_require_api(true);
$pdo = mg_db();

if (function_exists('mg_rate_limit')) {
    mg_rate_limit('admin.screen_recordings.diagnostics', 'user:' . (int)$user['id'], 30, 300);
}

function mg_admin_recording_diag_disabled_functions(): array
{
    $raw = (string)ini_get('disable_functions');
    if ($raw === '') return [];
    return array_values(array_filter(array_map(static fn($name) => strtolower(trim($name)), explode(',', $raw))));
}

function mg_admin_recording_diag_function_enabled(string $name): bool
{
    return function_exists($name) && !in_array(strtolower($name), mg_admin_recording_diag_disabled_functions(), true);
}

function mg_admin_recording_diag_ini_bytes(string $key): ?int
{
    $value = trim((string)ini_get($key));
    if ($value === '' || $value === '-1') return null;
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    switch ($unit) {
        case 'g': $number *= 1024;
        case 'm': $number *= 1024;
        case 'k': $number *= 1024;
    }
    return (int)round($number);
}

function mg_admin_recording_diag_format_bytes(?int $bytes): string
{
    if ($bytes === null) return 'Unlimited / server default';
    if ($bytes < 1) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes;
    $index = 0;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return rtrim(rtrim(number_format($value, $value >= 10 || $index === 0 ? 0 : 1), '0'), '.') . ' ' . $units[$index];
}

function mg_admin_recording_diag_first_line(string $output): string
{
    foreach (preg_split('/\r\n|\r|\n/', trim($output)) ?: [] as $line) {
        $line = trim((string)$line);
        if ($line !== '') return substr($line, 0, 220);
    }
    return '';
}

function mg_admin_recording_diag_find_binary(string $binary, array $absoluteCandidates): array
{
    $result = [
        'available' => false,
        'binary' => $binary,
        'path' => '',
        'version' => '',
        'detail' => 'Not detected.',
    ];

    if (!mg_admin_recording_diag_function_enabled('shell_exec') || !mg_admin_recording_diag_function_enabled('escapeshellarg')) {
        $result['detail'] = 'PHP shell_exec or escapeshellarg is disabled.';
        return $result;
    }

    $paths = [];
    foreach ($absoluteCandidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            $paths[] = $candidate;
        }
    }

    foreach (['command -v ', 'which '] as $prefix) {
        $path = trim((string)@shell_exec($prefix . escapeshellarg($binary) . ' 2>/dev/null'));
        if ($path !== '' && !in_array($path, $paths, true)) $paths[] = $path;
    }

    foreach ($paths as $path) {
        if ($path === '' || str_contains($path, "\n") || str_contains($path, "\r")) continue;
        $versionOutput = (string)@shell_exec(escapeshellarg($path) . ' -version 2>&1');
        $line = mg_admin_recording_diag_first_line($versionOutput);
        if ($line !== '') {
            return [
                'available' => true,
                'binary' => $binary,
                'path' => $path,
                'version' => $line,
                'detail' => 'Detected and executable.',
            ];
        }
    }

    return $result;
}

$schema = mg_screen_recordings_schema_ready($pdo);
$baseDir = mg_screen_recordings_base_dir();
$storageParent = dirname($baseDir);
$buckets = ['originals', 'edited', 'thumbnails', 'temp'];
$storageBuckets = [];
$storageReady = true;

foreach ($buckets as $bucket) {
    $path = $baseDir . '/' . $bucket;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    $canCreate = !$exists && ((is_dir($baseDir) && is_writable($baseDir)) || (!is_dir($baseDir) && is_dir($storageParent) && is_writable($storageParent)));
    $storageBuckets[$bucket] = [
        'path' => $path,
        'exists' => $exists,
        'writable' => $writable,
        'can_create' => $canCreate,
        'status' => $writable ? 'ready' : ($canCreate ? 'can_create' : 'missing_or_not_writable'),
    ];
    if (!$writable && !$canCreate) $storageReady = false;
}

$uploadMaxBytes = mg_admin_recording_diag_ini_bytes('upload_max_filesize');
$postMaxBytes = mg_admin_recording_diag_ini_bytes('post_max_size');
$memoryLimitBytes = mg_admin_recording_diag_ini_bytes('memory_limit');
$functions = [
    'shell_exec' => mg_admin_recording_diag_function_enabled('shell_exec'),
    'exec' => mg_admin_recording_diag_function_enabled('exec'),
    'proc_open' => mg_admin_recording_diag_function_enabled('proc_open'),
    'escapeshellarg' => mg_admin_recording_diag_function_enabled('escapeshellarg'),
];
$ffmpeg = mg_admin_recording_diag_find_binary('ffmpeg', ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/ffmpeg/bin/ffmpeg', '/opt/cpanel/ea-ffmpeg/bin/ffmpeg']);
$ffprobe = mg_admin_recording_diag_find_binary('ffprobe', ['/usr/bin/ffprobe', '/usr/local/bin/ffprobe', '/opt/ffmpeg/bin/ffprobe', '/opt/cpanel/ea-ffmpeg/bin/ffprobe']);
$rendererReady = $ffmpeg['available'] && $ffprobe['available'] && $functions['shell_exec'] && $functions['escapeshellarg'];
$warnings = [];

if (!$schema['ready']) $warnings[] = 'SQL migration is not ready. Run database/admin_screen_recordings.sql before using recordings.';
if (!$functions['shell_exec'] || !$functions['escapeshellarg']) $warnings[] = 'PHP shell execution is disabled or restricted. Server-side FFmpeg rendering cannot run from PHP until this is enabled or moved to a worker/server.';
if (!$ffmpeg['available']) $warnings[] = 'FFmpeg was not detected. Use browser-render fallback now, or upgrade to a server with FFmpeg for rendered exports.';
if (!$ffprobe['available']) $warnings[] = 'FFprobe was not detected. Duration/metadata probing for rendered exports will be limited.';
if (!$storageReady) $warnings[] = 'One or more recording storage folders are missing or not writable.';
if (!filter_var((string)ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN)) $warnings[] = 'PHP file uploads are disabled.';
if ($uploadMaxBytes !== null && $uploadMaxBytes < 100 * 1024 * 1024) $warnings[] = 'upload_max_filesize is below 100MB. Longer screen recordings may fail on shared hosting.';
if ($postMaxBytes !== null && $uploadMaxBytes !== null && $postMaxBytes < $uploadMaxBytes) $warnings[] = 'post_max_size is smaller than upload_max_filesize. Uploads may fail before reaching the configured file limit.';

$extensions = [];
foreach (['fileinfo', 'json', 'pdo', 'mbstring', 'openssl', 'curl', 'zip'] as $extension) {
    $extensions[$extension] = extension_loaded($extension);
}

mg_ok([
    'checked_at' => gmdate('c'),
    'schema' => $schema,
    'renderer_ready' => $rendererReady,
    'recommended_renderer' => $rendererReady ? 'ffmpeg' : 'browser_fallback',
    'recommended_renderer_label' => $rendererReady ? 'FFmpeg server renderer ready' : 'Browser fallback / server upgrade recommended',
    'ffmpeg' => $ffmpeg,
    'ffprobe' => $ffprobe,
    'php' => [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'file_uploads' => filter_var((string)ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN),
        'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
        'upload_max_filesize_bytes' => $uploadMaxBytes,
        'upload_max_filesize_human' => mg_admin_recording_diag_format_bytes($uploadMaxBytes),
        'post_max_size' => (string)ini_get('post_max_size'),
        'post_max_size_bytes' => $postMaxBytes,
        'post_max_size_human' => mg_admin_recording_diag_format_bytes($postMaxBytes),
        'memory_limit' => (string)ini_get('memory_limit'),
        'memory_limit_bytes' => $memoryLimitBytes,
        'memory_limit_human' => mg_admin_recording_diag_format_bytes($memoryLimitBytes),
        'max_execution_time' => (int)ini_get('max_execution_time'),
        'max_input_time' => (int)ini_get('max_input_time'),
        'open_basedir' => (string)ini_get('open_basedir'),
        'disabled_functions' => mg_admin_recording_diag_disabled_functions(),
        'functions' => $functions,
        'extensions' => $extensions,
    ],
    'storage' => [
        'base_dir' => $baseDir,
        'base_exists' => is_dir($baseDir),
        'base_writable' => is_dir($baseDir) && is_writable($baseDir),
        'parent_dir' => $storageParent,
        'parent_writable' => is_dir($storageParent) && is_writable($storageParent),
        'ready' => $storageReady,
        'buckets' => $storageBuckets,
    ],
    'warnings' => $warnings,
], 'Screen recording diagnostics loaded.');
