<?php
 declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/training-storage.php';
require __DIR__ . '/training-permissions.php';

$config = lqr_config();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$file = basename((string)($_GET['file'] ?? ''));
if ($file === '') { http_response_code(404); exit('File not found.'); }

$allowed = false;
$storagePath = '';
$original = $file;

if (tcl_runtime_sql()) {
    $pdo = tcl_storage_pdo();
    $stmt = $pdo->prepare('SELECT f.*,p.user_id FROM training_files f LEFT JOIN training_participants p ON p.id=f.participant_id WHERE f.stored_filename=:f LIMIT 1');
    $stmt->execute(['f' => $file]);
    $row = $stmt->fetch();
    if ($row) {
        $storagePath = (string)$row['storage_path'];
        $original = (string)$row['original_filename'];
        $allowed = tcl_is_training_admin($user) || ((string)$row['user_id'] === (string)$user['id']);
    }
} else {
    $runtime = tcl_state_load();
    foreach ($runtime['submissions'] as $submission) {
        $meta = is_array($submission['file'] ?? null) ? $submission['file'] : [];
        if (($meta['stored_filename'] ?? '') !== $file) continue;
        $storagePath = (string)($meta['storage_path'] ?? '');
        $original = (string)($meta['original_filename'] ?? $file);
        $participant = null;
        foreach ($runtime['participants'] as $p) {
            if (($p['id'] ?? '') === ($submission['participant_id'] ?? '')) { $participant = $p; break; }
        }
        $allowed = $participant ? tcl_can_view_participant_resource($user, $participant) : tcl_is_training_admin($user);
        break;
    }
}

if (!$allowed) { http_response_code(403); exit('Training proof access denied.'); }
$path = realpath(__DIR__ . '/' . ltrim($storagePath, '/'));
$base = realpath(__DIR__ . '/uploads/training-proof');
if (!$path || !$base || !str_starts_with($path, $base) || !is_file($path)) { http_response_code(404); exit('File not found.'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $original) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
