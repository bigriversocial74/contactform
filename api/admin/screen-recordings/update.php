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
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.update', 'user:' . (int)$user['id'], 60, 60);

$recordingId = max(0, (int)($input['recording_id'] ?? $input['id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
mg_screen_recordings_fetch($pdo, $recordingId);

$title = trim((string)($input['title'] ?? ''));
$description = trim((string)($input['description'] ?? ''));
$status = trim((string)($input['status'] ?? ''));
$allowedStatus = ['recording','processing','saved','edited','export_pending','exported','failed','archived'];
if ($title === '') mg_fail('Title is required.', 422);
if ($status !== '' && !in_array($status, $allowedStatus, true)) mg_fail('Invalid recording status.', 422);

$stmt = $pdo->prepare('UPDATE admin_screen_recordings SET title = ?, description = ?, status = CASE WHEN ? = ? THEN status ELSE ? END, updated_at = NOW() WHERE id = ? LIMIT 1');
$stmt->execute([substr($title, 0, 180), $description !== '' ? $description : null, $status, '', $status, $recordingId]);
$row = mg_screen_recordings_fetch($pdo, $recordingId);
mg_audit('admin_screen_recording.update', 'admin_screen_recording', ['recording_id' => $recordingId], (int)$user['id']);
mg_ok(['recording' => mg_screen_recordings_public_record($row)], 'Recording updated.');
