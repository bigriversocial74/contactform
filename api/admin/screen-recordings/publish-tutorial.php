<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recording-stage3.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
mg_screen_recording_stage3_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.publish_tutorial', 'user:' . (int)$user['id'], 30, 300);

$recordingId = max(0, (int)($input['recording_id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);

try {
    $tutorial = mg_screen_recording_stage3_publish_tutorial($pdo, $recordingId, $user, $input);
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.publish_tutorial_failed', 'Unable to publish tutorial.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to publish tutorial. Export the recording first and try again.', 422);
}

mg_ok(['tutorial' => $tutorial], 'Tutorial saved.');
