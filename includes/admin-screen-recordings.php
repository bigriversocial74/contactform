<?php
/**
 * Admin screen recording helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/admin-permission-matrix.php';

function mg_screen_recordings_user_is_super_admin(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('super_admin', $roles, true);
}

function mg_screen_recordings_user_has_permission(array $user, string $permission): bool
{
    if (mg_screen_recordings_user_is_super_admin($user)) return true;

    if (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, $permission)) {
        return true;
    }

    return mg_admin_permission_user_has($user, $permission);
}

function mg_screen_recordings_user_can_view(array $user): bool
{
    return mg_screen_recordings_user_has_permission($user, 'admin.screen_recordings.view')
        || mg_screen_recordings_user_has_permission($user, 'admin.screen_recordings.manage');
}

function mg_screen_recordings_user_can_manage(array $user): bool
{
    return mg_screen_recordings_user_has_permission($user, 'admin.screen_recordings.manage');
}

function mg_screen_recordings_user_can_manage_all(array $user): bool
{
    return mg_screen_recordings_user_is_super_admin($user)
        || mg_screen_recordings_user_has_permission($user, 'admin.screen_recordings.manage_all');
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

function mg_screen_recordings_max_file_bytes(): int
{
    return 700 * 1024 * 1024;
}

function mg_screen_recordings_max_chunk_bytes(): int
{
    return 40 * 1024 * 1024;
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

function mg_screen_recordings_allowed_extension(string $extension): bool
{
    return in_array(strtolower($extension), ['webm', 'mp4', 'mov', 'mkv'], true);
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

function mg_screen_recordings_normalize_hex_color(string $value, string $fallback): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-f]{6}$/i', $value) === 1) return strtolower($value);
    return $fallback;
}

function mg_screen_recordings_normalize_background(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (preg_match('/^#[0-9a-f]{6}$/i', $value) === 1) return strtolower($value);
    if (preg_match('/^rgba\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3}),\s*(0|0?\.\d+|1(?:\.0+)?)\)$/i', $value, $matches) === 1) {
        $r = min(255, max(0, (int)$matches[1]));
        $g = min(255, max(0, (int)$matches[2]));
        $b = min(255, max(0, (int)$matches[3]));
        $a = min(1, max(0, (float)$matches[4]));
        return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . rtrim(rtrim(number_format($a, 3, '.', ''), '0'), '.') . ')';
    }
    return null;
}

function mg_screen_recordings_normalize_font_weight(string $value): string
{
    $value = trim($value);
    return in_array($value, ['400', '500', '600', '700', '800', '900'], true) ? $value : '700';
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

function mg_screen_recordings_fetch_for_user(PDO $pdo, int $id, array $user, bool $manage = false): array
{
    if ($manage && !mg_screen_recordings_user_can_manage($user)) mg_fail('Admin screen recording manage permission is required.', 403);
    if (!$manage && !mg_screen_recordings_user_can_view($user)) mg_fail('Admin screen recording permission is required.', 403);

    if (mg_screen_recordings_user_can_manage_all($user)) {
        return mg_screen_recordings_fetch($pdo, $id);
    }

    $stmt = $pdo->prepare('SELECT * FROM admin_screen_recordings WHERE id = ? AND admin_user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id, (int)$user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Recording not found.', 404);
    return $row;
}

function mg_screen_recordings_list(PDO $pdo, string $query = '', string $status = '', ?array $user = null): array
{
    $where = ['deleted_at IS NULL'];
    $args = [];
    if ($user !== null && !mg_screen_recordings_user_can_manage_all($user)) {
        $where[] = 'admin_user_id = ?';
        $args[] = (int)$user['id'];
    }
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
    if ($size > mg_screen_recordings_max_file_bytes()) mg_fail('Recording file is too large for this upload endpoint.', 422);

    $originalName = trim((string)($file['name'] ?? 'recording.webm')) ?: 'recording.webm';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!mg_screen_recordings_allowed_extension($extension)) $extension = 'webm';

    $mime = mg_screen_recordings_detect_mime($tmp);
    if (!mg_screen_recordings_allowed_mime($mime)) mg_fail('Unsupported recording file type.', 422);
    if (($mime === 'application/octet-stream' || $mime === '') && !mg_screen_recordings_allowed_extension($extension)) {
        mg_fail('Unsupported recording file type.', 422);
    }

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

function mg_screen_recordings_chunk_dir(int $recordingId): string
{
    return mg_screen_recordings_base_dir() . '/temp/' . $recordingId;
}

function mg_screen_recordings_chunk_path(int $recordingId, int $chunkIndex): string
{
    return mg_screen_recordings_chunk_dir($recordingId) . '/chunk-' . str_pad((string)$chunkIndex, 6, '0', STR_PAD_LEFT) . '.webm';
}

function mg_screen_recordings_delete_tree(string $dir): void
{
    $base = realpath(mg_screen_recordings_base_dir() . '/temp');
    $real = realpath($dir);
    if (!$base || !$real || !str_starts_with($real, $base)) return;
    $items = scandir($real);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $real . '/' . $item;
        if (is_dir($path)) mg_screen_recordings_delete_tree($path);
        else @unlink($path);
    }
    @rmdir($real);
}

function mg_screen_recordings_store_chunk(PDO $pdo, int $recordingId, array $file, int $chunkIndex): void
{
    mg_screen_recordings_prepare_storage();
    if ($recordingId < 1 || $chunkIndex < 0 || $chunkIndex > 2000) mg_fail('Invalid recording chunk.', 422);
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) mg_fail('Unable to upload recording chunk.', 422);
    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) mg_fail('Recording chunk upload is invalid.', 422);
    if ($size < 1) mg_fail('Recording chunk is empty.', 422);
    if ($size > mg_screen_recordings_max_chunk_bytes()) mg_fail('Recording chunk is too large.', 422);

    $mime = mg_screen_recordings_detect_mime($tmp);
    if (!mg_screen_recordings_allowed_mime($mime)) mg_fail('Unsupported recording chunk type.', 422);

    $dir = mg_screen_recordings_chunk_dir($recordingId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) mg_fail('Unable to prepare chunk storage.', 500);
    $target = mg_screen_recordings_chunk_path($recordingId, $chunkIndex);
    if (!move_uploaded_file($tmp, $target)) mg_fail('Unable to store recording chunk.', 500);
    @chmod($target, 0640);
    $pdo->prepare("UPDATE admin_screen_recordings SET status = 'processing', updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$recordingId]);
}

function mg_screen_recordings_assemble_chunks(PDO $pdo, int $recordingId, int $chunkCount, array $metadata = []): array
{
    mg_screen_recordings_prepare_storage();
    $row = mg_screen_recordings_fetch($pdo, $recordingId);
    if ($chunkCount < 1 || $chunkCount > 2000) mg_fail('Invalid recording chunk count.', 422);

    $dir = mg_screen_recordings_chunk_dir($recordingId);
    if (!is_dir($dir)) mg_fail('Recording chunks are unavailable.', 422);

    $extension = 'webm';
    $filename = mg_screen_recordings_safe_slug((string)$row['title']) . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
    $relative = mg_screen_recordings_relative_path('originals', $filename);
    $target = mg_screen_recordings_abs_path($relative);
    if (!$target) mg_fail('Unable to prepare recording output.', 500);
    $partial = $target . '.part';
    $out = @fopen($partial, 'wb');
    if (!$out) mg_fail('Unable to prepare recording output.', 500);

    $total = 0;
    try {
        for ($i = 0; $i < $chunkCount; $i++) {
            $chunk = mg_screen_recordings_chunk_path($recordingId, $i);
            if (!is_file($chunk) || !is_readable($chunk)) {
                throw new RuntimeException('Missing recording chunk.');
            }
            $size = filesize($chunk) ?: 0;
            $total += (int)$size;
            if ($total > mg_screen_recordings_max_file_bytes()) {
                throw new RuntimeException('Recording file is too large for this upload endpoint.');
            }
            $in = @fopen($chunk, 'rb');
            if (!$in) throw new RuntimeException('Unable to read recording chunk.');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fflush($out);
        fclose($out);
        $out = null;
        if (!rename($partial, $target)) throw new RuntimeException('Unable to save assembled recording.');
        @chmod($target, 0640);
    } catch (Throwable $error) {
        if (is_resource($out)) fclose($out);
        @unlink($partial);
        throw $error;
    }

    $mime = mg_screen_recordings_detect_mime($target) ?: 'video/webm';
    if (!mg_screen_recordings_allowed_mime($mime)) {
        @unlink($target);
        mg_fail('Unsupported assembled recording file type.', 422);
    }

    $duration = isset($metadata['duration_seconds']) ? max(0, (float)$metadata['duration_seconds']) : ($row['duration_seconds'] !== null ? (float)$row['duration_seconds'] : null);
    $width = isset($metadata['width']) ? max(0, (int)$metadata['width']) : ($row['width'] !== null ? (int)$row['width'] : null);
    $height = isset($metadata['height']) ? max(0, (int)$metadata['height']) : ($row['height'] !== null ? (int)$row['height'] : null);

    $stmt = $pdo->prepare('UPDATE admin_screen_recordings SET original_filename = ?, original_path = ?, mime_type = ?, file_size = ?, duration_seconds = ?, width = ?, height = ?, status = ?, error_message = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
    $stmt->execute([$filename, $relative, $mime, $total, $duration, $width, $height, 'saved', $recordingId]);
    mg_screen_recordings_delete_tree($dir);

    return mg_screen_recordings_fetch($pdo, $recordingId);
}

function mg_screen_recordings_save_manifest(PDO $pdo, int $recordingId, array $manifest): array
{
    $row = mg_screen_recordings_fetch($pdo, $recordingId);
    $manifest = array_replace_recursive(mg_screen_recordings_manifest_default(), $manifest);
    $overlays = is_array($manifest['text_overlays'] ?? null) ? array_values($manifest['text_overlays']) : [];
    $cleanOverlays = [];
    foreach ($overlays as $index => $overlay) {
        if (!is_array($overlay)) continue;
        $text = trim((string)($overlay['text'] ?? ''));
        if ($text === '') continue;
        $start = max(0, (float)($overlay['start'] ?? 0));
        $end = max($start, (float)($overlay['end'] ?? ($start + 5)));
        $cleanOverlays[] = [
            'id' => preg_replace('/[^a-z0-9._-]+/i', '-', trim((string)($overlay['id'] ?? 'overlay-' . ($index + 1)))) ?: ('overlay-' . ($index + 1)),
            'text' => substr($text, 0, 500),
            'start' => $start,
            'end' => $end,
            'x' => min(100, max(0, (float)($overlay['x'] ?? 50))),
            'y' => min(100, max(0, (float)($overlay['y'] ?? 50))),
            'fontSize' => min(120, max(10, (int)($overlay['fontSize'] ?? 28))),
            'color' => mg_screen_recordings_normalize_hex_color((string)($overlay['color'] ?? '#ffffff'), '#ffffff'),
            'background' => mg_screen_recordings_normalize_background($overlay['background'] ?? '') ?? 'rgba(17, 24, 39, 0.72)',
            'fontWeight' => mg_screen_recordings_normalize_font_weight((string)($overlay['fontWeight'] ?? '700')),
            'align' => in_array((string)($overlay['align'] ?? 'center'), ['left','center','right'], true) ? (string)$overlay['align'] : 'center',
        ];
    }
    $manifest['text_overlays'] = $cleanOverlays;
    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $pdo->beginTransaction();
    try {
        $status = in_array((string)$row['status'], ['exported','archived'], true) ? (string)$row['status'] : 'edited';
        $stmt = $pdo->prepare('UPDATE admin_screen_recordings SET edit_manifest_json = ?, status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->execute([$json, $status, $recordingId]);

        $pdo->prepare('DELETE FROM admin_screen_recording_text_overlays WHERE recording_id = ?')->execute([$recordingId]);
        $insert = $pdo->prepare('INSERT INTO admin_screen_recording_text_overlays (recording_id, overlay_key, overlay_text, start_seconds, end_seconds, x_percent, y_percent, font_size, text_color, background_color, font_weight, text_align, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        foreach ($cleanOverlays as $overlay) {
            $insert->execute([$recordingId, substr((string)$overlay['id'], 0, 80), (string)$overlay['text'], (float)$overlay['start'], (float)$overlay['end'], (float)$overlay['x'], (float)$overlay['y'], (int)$overlay['fontSize'], (string)$overlay['color'], (string)$overlay['background'], (string)$overlay['fontWeight'], (string)$overlay['align']]);
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
