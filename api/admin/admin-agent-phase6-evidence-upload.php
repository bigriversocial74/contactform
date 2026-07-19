<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__, 2) . '/includes/admin-agent-phase6.php';

$actor = mg_require_api_user();
$actorId = (int) $actor['id'];
$pdo = mg_db();

$allowed = mg_admin_account_actor_has($actor, 'admin.admin_agent.evidence')
    || mg_admin_account_actor_has($actor, 'admin.operations_command.manage')
    || mg_admin_account_actor_has($actor, 'admin.settings.manage')
    || mg_admin_account_actor_has($actor, 'admin.users.manage');
if (!$allowed) {
    mg_audit('permission_denied', 'security', ['permission' => 'admin.admin_agent.evidence', 'area' => 'main_admin_agent_phase6_evidence_upload'], $actorId);
    mg_fail('Permission denied.', 403);
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        mg_fail('Method not allowed.', 405);
    }
    mg_rate_limit('admin.agent.phase6.evidence_upload', 'user:' . $actorId, 20, 3600);
    mg_require_csrf_for_write($_POST);
    if (!mg_admin_agent_phase5_ready($pdo)) {
        mg_fail('Main Admin Agent Phase 5 SQL migration is required.', 409, ['schema' => mg_admin_agent_phase5_schema_state($pdo)]);
    }
    $file = $_FILES['evidence_file'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Choose a validator JSON file to upload.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 2 || $size > 1024 * 1024) {
        throw new InvalidArgumentException('Validator JSON must be between 2 bytes and 1 MB.');
    }
    $name = (string) ($file['name'] ?? 'evidence.json');
    if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
        throw new InvalidArgumentException('Validator evidence must be a .json file.');
    }
    $temporary = (string) ($file['tmp_name'] ?? '');
    if ($temporary === '' || !is_uploaded_file($temporary)) {
        throw new InvalidArgumentException('The uploaded validator file could not be verified.');
    }
    $raw = file_get_contents($temporary);
    if (!is_string($raw)) {
        throw new RuntimeException('Unable to read the uploaded validator JSON.');
    }
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Validator JSON must contain one JSON object.');
    }
    foreach (['run_id', 'status'] as $required) {
        if (trim((string) ($payload[$required] ?? '')) === '') {
            throw new InvalidArgumentException('Validator JSON is missing required field: ' . $required . '.');
        }
    }
    $payload['environment_key'] = mg_admin_agent_phase6_environment((string) ($_POST['environment_key'] ?? 'production'));
    $payload['scope_key'] = 'database';
    $payload['source_key'] = 'database_backup_restore_validator';
    $payload['report_path'] = 'browser-upload:' . mb_substr(basename($name), 0, 160);
    $payload['details'] = [
        'source_database' => $payload['source_database'] ?? null,
        'restore_database_ephemeral' => !empty($payload['restore_database']),
        'backup_retained' => (bool) ($payload['backup_retained'] ?? false),
        'imported_by' => 'admin_agent_phase6_browser_upload',
        'uploaded_filename' => mb_substr(basename($name), 0, 160),
    ];
    $result = mg_admin_agent_phase5_record_backup_evidence($pdo, $actorId, $payload);
    if (mg_admin_agent_phase6_ready($pdo)) {
        $result['readiness_run'] = mg_admin_agent_phase6_run($pdo, ['trigger_source' => 'setup', 'initiated_by_user_id' => $actorId, 'environment_key' => $payload['environment_key']]);
    }
    mg_audit('admin_agent_phase6_evidence_uploaded', 'system', ['evidence_id' => $result['id'] ?? null, 'filename' => mb_substr(basename($name), 0, 160), 'size_bytes' => $size], $actorId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($result, 'Validator evidence uploaded and analyzed.');
} catch (JsonException $error) {
    mg_fail('The uploaded file is not valid JSON.', 422);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'admin_agent.phase6_evidence_upload_failed', 'Main Admin Agent evidence upload failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to upload validator evidence.', 500);
}
