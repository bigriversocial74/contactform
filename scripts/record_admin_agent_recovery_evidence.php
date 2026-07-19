<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-agent-phase5.php';

$options = getopt('', ['file:', 'environment::', 'scope::', 'source::']);
$file = trim((string) ($options['file'] ?? ''));
if ($file === '') {
    fwrite(STDERR, "Usage: php scripts/record_admin_agent_recovery_evidence.php --file=<validation.json> [--environment=production] [--scope=database]\n");
    exit(2);
}
$path = str_starts_with($file, DIRECTORY_SEPARATOR) ? $file : dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Recovery evidence file is not readable.\n");
    exit(2);
}

try {
    $raw = file_get_contents($path);
    if (!is_string($raw)) throw new RuntimeException('Unable to read recovery evidence file.');
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) throw new RuntimeException('Recovery evidence must be a JSON object.');
    $payload['environment_key'] = mg_admin_agent_phase5_clean_environment((string) ($options['environment'] ?? 'production'));
    $payload['scope_key'] = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($options['scope'] ?? 'database'))) ?: 'database';
    $payload['source_key'] = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($options['source'] ?? 'database_backup_restore_validator'))) ?: 'database_backup_restore_validator';
    $payload['report_path'] = $file;
    $payload['details'] = [
        'source_database' => $payload['source_database'] ?? null,
        'restore_database_ephemeral' => !empty($payload['restore_database']),
        'backup_retained' => (bool) ($payload['backup_retained'] ?? false),
        'imported_by' => 'record_admin_agent_recovery_evidence.php',
    ];
    $result = mg_admin_agent_phase5_record_backup_evidence(mg_db(), null, $payload);
    fwrite(STDOUT, json_encode(['ok' => true, 'data' => $result, 'generated_at' => gmdate('c')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) mg_security_log('error', 'admin_agent.phase5_evidence_import_failed', 'Main Admin Agent recovery evidence import failed.', ['exception_class' => $error::class], null);
    fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Recovery evidence import failed.', 'exception_class' => $error::class, 'generated_at' => gmdate('c')], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
