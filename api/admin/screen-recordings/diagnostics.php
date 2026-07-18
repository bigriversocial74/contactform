<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recording-stage3.php';

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
    unset($absoluteCandidates);
    $path = mg_runtime_process_resolve($binary);
    if ($path === null) {
        return [
            'available' => false,
            'binary' => $binary,
            'path' => '',
            'version' => '',
            'detail' => 'Not detected in the configured allowlist.',
        ];
    }
    $probe = mg_runtime_process_run($binary, ['-version'], 5, 65536);
    $line = mg_admin_recording_diag_first_line((string)$probe['stdout'] . "\n" . (string)$probe['stderr']);
    return [
        'available' => (int)$probe['code'] === 0,
        'binary' => $binary,
        'path' => $path,
        'version' => $line,
        'detail' => (int)$probe['code'] === 0 ? 'Detected through the allowlisted process gateway.' : 'Binary exists but the version probe failed.',
    ];
}

$schema = mg_screen_recordings_schema_ready($pdo);
$stage3Schema = mg_screen_recording_stage3_schema_ready($pdo);
$baseDir = mg_screen_recordings_base_dir();
$storageParent = dirname($baseDir);
$buckets = ['originals', 'edited', 'thumbnails', 'temp', 'audio', 'logs'];
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
    'process_gateway' => function_exists('mg_runtime_process_run'),
    'proc_open' => mg_admin_recording_diag_function_enabled('proc_open'),
];
$ffmpeg = mg_admin_recording_diag_find_binary('ffmpeg', []);
$ffprobe = mg_admin_recording_diag_find_binary('ffprobe', []);
$rendererReady = $ffmpeg['available'] && $functions['process_gateway'] && $functions['proc_open'];
$warnings = [];

if (!$schema['ready']) $warnings[] = 'Base SQL migration is not ready. Run database/admin_screen_recordings.sql before using recordings.';
if (!$stage3Schema['ready']) $warnings[] = 'Stage 3 SQL migration is not ready. Run database/admin_screen_recording_renderer_tutorials.sql before rendering, voiceover, or publishing tutorials.';
if (!$functions['process_gateway'] || !$functions['proc_open']) $warnings[] = 'The allowlisted process gateway is unavailable. Server-side media rendering cannot run until proc_open is enabled.';
if (!$ffmpeg['available']) $warnings[] = 'FFmpeg was not detected. Rendered exports will fail until FFmpeg is available.';
if (!$ffprobe['available']) $warnings[] = 'FFprobe was not detected. Duration/metadata probing for rendered exports will be limited.';
if (!$storageReady) $warnings[] = 'One or more recording storage folders are missing or not writable.';
if (!filter_var((string)ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN)) $warnings[] = 'PHP file uploads are disabled.';
if ($uploadMaxBytes !== null && $uploadMaxBytes < 100 * 1024 * 1024) $warnings[] = 'upload_max_filesize is below 100MB. Longer screen recordings or audio uploads may fail on shared hosting.';
if ($postMaxBytes !== null && $uploadMaxBytes !== null && $postMaxBytes < $uploadMaxBytes) $warnings[] = 'post_max_size is smaller than upload_max_filesize. Uploads may fail before reaching the configured file limit.';

$extensions = [];
foreach (['fileinfo', 'json', 'pdo', 'mbstring', 'openssl', 'curl', 'zip'] as $extension) {
    $extensions[$extension] = extension_loaded($extension);
}

mg_ok([
    'checked_at' => gmdate('c'),
    'schema' => $schema,
    'stage3_schema' => $stage3Schema,
    'renderer_ready' => $rendererReady,
    'recommended_renderer' => $rendererReady ? 'ffmpeg' : 'server_upgrade_required',
    'recommended_renderer_label' => $rendererReady ? 'FFmpeg server renderer ready' : 'Server upgrade / FFmpeg required for rendered exports',
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
