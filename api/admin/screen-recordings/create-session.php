<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.create', 'user:' . (int)$user['id'], 30, 60);

try {
    $row = mg_screen_recordings_create($pdo, (int)$user['id'], $input);
    mg_audit('admin_screen_recording.create', 'admin_screen_recording', ['recording_id' => (int)$row['id']], (int)$user['id']);
    mg_ok(['recording' => mg_screen_recordings_public_record($row)], 'Recording session created.');
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.create_failed', 'Unable to create screen recording session.', ['message' => $error->getMessage()], (int)$user['id']);
    mg_fail('Unable to create recording session.', 422);
}
