<?php
/**
 * Admin screen recording helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/admin-permission-matrix.php';

function mg_screen_recordings_user_can_view(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('super_admin', $roles, true)) return true;

    if (function_exists('mg_api_user_has_permission')) {
        return mg_api_user_has_permission($user, 'admin.screen_recordings.view')
            || mg_api_user_has_permission($user, 'admin.screen_recordings.manage')
            || mg_api_user_has_permission($user, 'admin.health.view')
            || mg_api_user_has_permission($user, 'admin.access');
    }

    return mg_admin_permission_user_has($user, 'admin.screen_recordings.view')
        || mg_admin_permission_user_has($user, 'admin.screen_recordings.manage')
        || mg_admin_permission_user_has($user, 'admin.health.view');
}

function mg_screen_recordings_user_can_manage(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('super_admin', $roles, true)) return true;

    if (function_exists('mg_api_user_has_permission')) {
        return mg_api_user_has_permission($user, 'admin.screen_recordings.manage')
            || mg_api_user_has_permission($user, 'admin.health.view')
            || mg_api_user_has_permission($user, 'admin.access');
    }

    return mg_admin_permission_user_has($user, 'admin.screen_recordings.manage')
        || mg_admin_permission_user_has($user, 'admin.health.view');
}

function mg_screen_recordings_require_api(bool $manage = false): array
{
    $user = mg_require_api_user();
    $allowed = $manage ? mg_screen_recordings_user_can_manage($user) : mg_screen_recordings_user_can_view($user);
    if (!$allowed) mg_fail('Admin screen recording permission is required.', 403);
    return $user;
}

function mg_screen_recordings_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_screen_recordings_schema_ready(PDO $pdo): array
{
    $tables = [
        'admin_screen_recordings' => mg_screen_recordings_table_exists($pdo, 'admin_screen_recordings'),
        'admin_screen_recording_versions' => mg_screen_recordings_table_exists($pdo, 'admin_screen_recording_versions'),
        'admin_screen_recording_text_overlays' => mg_screen_recordings_table_exists($pdo, 'admin_screen_recording_text_overlays'),
    ];
    return [
        'ready' => !in_array(false, $tables, true),
        'tables' => $tables,
        'migration' => 'database/admin_screen_recordings.sql',
    ];
}

function mg_screen_recordings_require_schema(PDO $pdo): void
{
    $schema = mg_screen_recordings_schema_ready($pdo);
    if (!$schema['ready']) {
        mg_fail('Screen recordings SQL migration is required: database/admin_screen_recordings.sql', 503, $schema);
    }
}

function mg_screen_recordings_base_dir(): string
{
    return dirname(__DIR__) . '/uploads/admin-recordings';
}

function mg_screen_recordings_prepare_storage(): void
{
    $base = mg_screen_recordings_base_dir();
    foreach (['originals', 'edited', 'thumbnails', 'temp'] as $dir) {
        $path = $base . '/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to prepare admin recording storage.');
        }
    }
    $htaccess = $base . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \".*\">\n  Require all denied\n</FilesMatch>\n", LOCK_EX);
    }
}

function mg_screen_recordings_detect_mime(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mime;
        }
    }
    return '';
}

function mg_screen_recordings_public_id(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mg_screen_recordings_safe_slug(string $value): string
{
    $value = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
    return $value !== '' ? substr($value, 0, 80) : 'recording';
}

function mg_screen_recordings_relative_path(string $bucket, string $filename): string
{
    $bucket = trim($bucket, '/');
    if (!in_array($bucket, ['originals', 'edited', 'thumbnails', 'temp'], true)) {
        throw new InvalidArgumentException('Invalid recording storage bucket.');
    }
    $filename = basename($filename);
    if ($filename === '' || preg_match('/^[a-z0-9._-]+$/i', $filename) !== 1) {
        throw new InvalidArgumentException('Invalid recording filename.');
    }
    return '/uploads/admin-recordings/' . $bucket . '/' . $filename;
}

function mg_screen_recordings_abs_path(?string $relativePath): ?string
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return null;
    $prefix = '/uploads/admin-recordings/';
    if (!str_starts_with($relativePath, $prefix) || str_contains($relativePath, '..')) return null;
    $suffix = substr($relativePath, strlen($prefix));
    return mg_screen_recordings_base_dir() . '/' . $suffix;
}

function mg_screen_recordings_allowed_mime(string $mime): bool
{
    return in_array($mime, ['video/webm', 'video/mp4', 'video/quicktime', 'video/x-matroska', 'application/octet-stream', ''], true);
}

function mg_screen_recordings_manifest_default(): array
{
    return [
        'version' => 1,
        'trim' => ['start' => 0, 'end' => null],
        'segments' => [],
        'deleted_segments' => [],
        'text_overlays' => [],
        'export' => ['format' => 'webm', 'renderer' => 'browser_manifest'],
    ];
}

function mg_screen_recordings_decode_manifest(?string $json): array
{
    if (!$json) return mg_screen_recordings_manifest_default();
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return mg_screen_recordings_manifest_default();
    return array_replace_recursive(mg_screen_recordings_manifest_default(), $decoded);
}

function mg_screen_recordings_public_record(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'admin_user_id' => (int)$row['admin_user_id'],
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'original_filename' => (string)($row['original_filename'] ?? ''),
        'edited_filename' => (string)($row['edited_filename'] ?? ''),
        'thumbnail_path' => (string)($row['thumbnail_path'] ?? ''),
        'mime_type' => (string)($row['mime_type'] ?? ''),
        'file_size' => (int)($row['file_size'] ?? 0),
        'duration_seconds' => $row['duration_seconds'] !== null ? (float)$row['duration_seconds'] : null,
        'width' => $row['width'] !== null ? (int)$row['width'] : null,
        'height' => $row['height'] !== null ? (int)$row['height'] : null,
        'capture_surface' => (string)($row['capture_surface'] ?? ''),
        'status' => (string)$row['status'],
        'edit_manifest' => mg_screen_recordings_decode_manifest($row['edit_manifest_json'] ?? null),
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'download_original_url' => '/api/admin/screen-recordings/download.php?id=' . (int)$row['id'] . '&type=original',
        'download_edited_url' => !empty($row['edited_path']) ? '/api/admin/screen-recordings/download.php?id=' . (int)$row['id'] . '&type=edited' : '',
        'editor_url' => '/admin/screen-recording-editor.php?id=' . (int)$row['id'],
    ];
}

function mg_screen_recordings_fetch(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM admin_screen_recordings WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Recording not found.', 404);
    return $row;
}

function mg_screen_recordings_list(PDO $pdo, string $query = '', string $status = ''): array
{
    $where = ['deleted_at IS NULL'];
    $args = [];
    if ($query !== '') {
        $where[] = '(title LIKE ? OR description LIKE ? OR original_filename LIKE ?)';
        $like = '%' . $query . '%';
        array_push($args, $like, $like, $like);
    }
    if ($status !== '' && in_array($status, ['recording','processing','saved','edited','export_pending','exported','failed','archived'], true)) {
        $where[] = 'status = ?';
        $args[] = $status;
    }
    $sql = 'SELECT * FROM admin_screen_recordings WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return array_map('mg_screen_recordings_public_record', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_screen_recordings_create(PDO $pdo, int $adminUserId, array $input): array
{
    $title = trim((string)($input['title'] ?? '')) ?: 'Untitled screen recording';
    $description = trim((string)($input['description'] ?? ''));
    $mimeType = substr(trim((string)($input['mime_type'] ?? 'video/webm')), 0, 120);
    $duration = isset($input['duration_seconds']) ? max(0, (float)$input['duration_seconds']) : null;
    $width = isset($input['width']) ? max(0, (int)$input['width']) : null;
    $height = isset($input['height']) ? max(0, (int)$input['height']) : null;
    $captureSurface = substr(trim((string)($input['capture_surface'] ?? '')), 0, 80);

    $stmt = $pdo->prepare('INSERT INTO admin_screen_recordings (public_id, admin_user_id, title, description, mime_type, duration_seconds, width, height, capture_surface, status, edit_manifest_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        mg_screen_recordings_public_id(),
        $adminUserId,
        substr($title, 0, 180),
        $description !== '' ? $description : null,
        $mimeType,
        $duration,
        $width,
        $height,
        $captureSurface !== '' ? $captureSurface : null,
        'recording',
        json_encode(mg_screen_recordings_manifest_default(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    return mg_screen_recordings_fetch($pdo, (int)$pdo->lastInsertId());
}

function mg_screen_recordings_store_original(PDO $pdo, int $recordingId, array $file, array $metadata = []): array
{
    mg_screen_recordings_prepare_storage();
    $row = mg_screen_recordings_fetch($pdo, $recordingId);
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) mg_fail('Unable to upload recording video.', 422);
    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) mg_fail('Recording upload is invalid.', 422);
    if ($size < 1) mg_fail('Recording file is empty.', 422);
    if ($size > 700 * 1024 * 1024) mg_fail('Recording file is too large for this upload endpoint.', 422);

    $mime = mg_screen_recordings_detect_mime($tmp);
    if (!mg_screen_recordings_allowed_mime($mime)) mg_fail('Unsupported recording file type.', 422);

    $originalName = trim((string)($file['name'] ?? 'recording.webm')) ?: 'recording.webm';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['webm','mp4','mov','mkv'], true)) $extension = 'webm';
    $filename = mg_screen_recordings_safe_slug((string)$row['title']) . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
    $relative = mg_screen_recordings_relative_path('originals', $filename);
    $target = mg_screen_recordings_abs_path($relative);
    if (!$target || !move_uploaded_file($tmp, $target)) mg_fail('Unable to save recording video.', 500);
    @chmod($target, 0640);

    $duration = isset($metadata['duration_seconds']) ? max(0, (float)$metadata['duration_seconds']) : ($row['duration_seconds'] !== null ? (float)$row['duration_seconds'] : null);
    $width = isset($metadata['width']) ? max(0, (int)$metadata['width']) : ($row['width'] !== null ? (int)$row['width'] : null);
    $height = isset($metadata['height']) ? max(0, (int)$metadata['height']) : ($row['height'] !== null ? (int)$row['height'] : null);

    $stmt = $pdo->prepare('UPDATE admin_screen_recordings SET original_filename = ?, original_path = ?, mime_type = ?, file_size = ?, duration_seconds = ?, width = ?, height = ?, status = ?, error_message = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->execute([$filename, $relative, $mime ?: (string)($row['mime_type'] ?? 'video/webm'), $size, $duration, $width, $height, 'saved', $recordingId]);

    return mg_screen_recordings_fetch($pdo, $recordingId);
}

function mg_screen_recordings_save_manifest(PDO $pdo, int $recordingId, array $manifest): array
{
    $row = mg_screen_recordings_fetch($pdo, $recordingId);
    $manifest = array_replace_recursive(mg_screen_recordings_manifest_default(), $manifest);
    $overlays = is_array($manifest['text_overlays'] ?? null) ? array_values($manifest['text_overlays']) : [];
    $manifest['text_overlays'] = $overlays;
    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $pdo->beginTransaction();
    try {
        $status = in_array((string)$row['status'], ['exported','archived'], true) ? (string)$row['status'] : 'edited';
        $stmt = $pdo->prepare('UPDATE admin_screen_recordings SET edit_manifest_json = ?, status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->execute([$json, $status, $recordingId]);

        $pdo->prepare('DELETE FROM admin_screen_recording_text_overlays WHERE recording_id = ?')->execute([$recordingId]);
        $insert = $pdo->prepare('INSERT INTO admin_screen_recording_text_overlays (recording_id, overlay_key, overlay_text, start_seconds, end_seconds, x_percent, y_percent, font_size, text_color, background_color, font_weight, text_align, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        foreach ($overlays as $index => $overlay) {
            if (!is_array($overlay)) continue;
            $text = trim((string)($overlay['text'] ?? ''));
            if ($text === '') continue;
            $key = trim((string)($overlay['id'] ?? 'overlay-' . ($index + 1)));
            $key = preg_replace('/[^a-z0-9._-]+/i', '-', $key) ?: ('overlay-' . ($index + 1));
            $start = max(0, (float)($overlay['start'] ?? 0));
            $end = max($start, (float)($overlay['end'] ?? ($start + 5)));
            $x = min(100, max(0, (float)($overlay['x'] ?? 50)));
            $y = min(100, max(0, (float)($overlay['y'] ?? 50)));
            $size = min(120, max(10, (int)($overlay['fontSize'] ?? 28)));
            $color = substr((string)($overlay['color'] ?? '#ffffff'), 0, 24);
            $bg = trim((string)($overlay['background'] ?? ''));
            $weight = substr((string)($overlay['fontWeight'] ?? '700'), 0, 24);
            $align = in_array((string)($overlay['align'] ?? 'center'), ['left','center','right'], true) ? (string)$overlay['align'] : 'center';
            $insert->execute([$recordingId, substr($key, 0, 80), substr($text, 0, 500), $start, $end, $x, $y, $size, $color, $bg !== '' ? substr($bg, 0, 32) : null, $weight, $align]);
        }

        $version = $pdo->prepare('INSERT INTO admin_screen_recording_versions (recording_id, admin_user_id, version_label, edit_manifest_json, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $version->execute([$recordingId, (int)$row['admin_user_id'], 'Editor draft', $json, 'draft']);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return mg_screen_recordings_fetch($pdo, $recordingId);
}

function mg_screen_recordings_soft_delete(PDO $pdo, int $recordingId): void
{
    $stmt = $pdo->prepare("UPDATE admin_screen_recordings SET status = 'archived', deleted_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1");
    $stmt->execute([$recordingId]);
}
