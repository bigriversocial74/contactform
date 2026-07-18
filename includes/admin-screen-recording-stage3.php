<?php
/**
 * Stage 3 helpers for admin screen recording renderer, voiceover, and tutorials.
 */
declare(strict_types=1);

require_once __DIR__ . '/admin-screen-recordings.php';
require_once __DIR__ . '/runtime-process.php';

function mg_screen_recording_stage3_tables(): array
{
    return [
        'admin_screen_recording_export_jobs',
        'admin_screen_recording_audio_tracks',
        'public_tutorials',
    ];
}

function mg_screen_recording_stage3_schema_ready(PDO $pdo): array
{
    $tables = [];
    foreach (mg_screen_recording_stage3_tables() as $table) {
        $tables[$table] = mg_screen_recordings_table_exists($pdo, $table);
    }
    return [
        'ready' => !in_array(false, $tables, true),
        'tables' => $tables,
        'migration' => 'database/admin_screen_recording_renderer_tutorials.sql',
    ];
}

function mg_screen_recording_stage3_require_schema(PDO $pdo): void
{
    $schema = mg_screen_recording_stage3_schema_ready($pdo);
    if (!$schema['ready']) {
        mg_fail('Stage 3 screen recording SQL migration is required: database/admin_screen_recording_renderer_tutorials.sql', 503, $schema);
    }
}

function mg_screen_recording_stage3_prepare_storage(): void
{
    mg_screen_recordings_prepare_storage();
    $base = mg_screen_recordings_base_dir();
    foreach (['audio', 'logs'] as $dir) {
        $path = $base . '/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to prepare admin recording Stage 3 storage.');
        }
    }
}

function mg_screen_recording_stage3_relative_path(string $bucket, string $filename): string
{
    $bucket = trim($bucket, '/');
    if (!in_array($bucket, ['audio', 'edited', 'thumbnails', 'logs'], true)) {
        throw new InvalidArgumentException('Invalid Stage 3 storage bucket.');
    }
    $filename = basename($filename);
    if ($filename === '' || preg_match('/^[a-z0-9._-]+$/i', $filename) !== 1) {
        throw new InvalidArgumentException('Invalid Stage 3 recording filename.');
    }
    return '/uploads/admin-recordings/' . $bucket . '/' . $filename;
}

function mg_screen_recording_stage3_allowed_audio_mime(string $mime): bool
{
    return in_array($mime, ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/x-wav', 'application/octet-stream', ''], true);
}

function mg_screen_recording_stage3_allowed_audio_extension(string $extension): bool
{
    return in_array(strtolower($extension), ['webm', 'ogg', 'mp3', 'm4a', 'wav', 'aac'], true);
}

function mg_screen_recording_stage3_public_job(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'recording_id' => (int)$row['recording_id'],
        'version_id' => $row['version_id'] !== null ? (int)$row['version_id'] : null,
        'job_type' => (string)$row['job_type'],
        'renderer' => (string)$row['renderer'],
        'requested_format' => (string)$row['requested_format'],
        'burn_overlays' => (bool)$row['burn_overlays'],
        'include_audio' => (bool)$row['include_audio'],
        'mute_original_audio' => (bool)$row['mute_original_audio'],
        'original_audio_volume' => (float)$row['original_audio_volume'],
        'voiceover_volume' => (float)$row['voiceover_volume'],
        'status' => (string)$row['status'],
        'output_path' => (string)($row['output_path'] ?? ''),
        'thumbnail_path' => (string)($row['thumbnail_path'] ?? ''),
        'error_message' => (string)($row['error_message'] ?? ''),
        'started_at' => (string)($row['started_at'] ?? ''),
        'completed_at' => (string)($row['completed_at'] ?? ''),
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_screen_recording_stage3_public_audio(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'recording_id' => (int)$row['recording_id'],
        'track_type' => (string)$row['track_type'],
        'title' => (string)$row['title'],
        'original_filename' => (string)($row['original_filename'] ?? ''),
        'mime_type' => (string)($row['mime_type'] ?? ''),
        'file_size' => (int)($row['file_size'] ?? 0),
        'duration_seconds' => $row['duration_seconds'] !== null ? (float)$row['duration_seconds'] : null,
        'start_seconds' => (float)$row['start_seconds'],
        'volume' => (float)$row['volume'],
        'status' => (string)$row['status'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_screen_recording_stage3_public_tutorial(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'recording_id' => $row['recording_id'] !== null ? (int)$row['recording_id'] : null,
        'version_id' => $row['version_id'] !== null ? (int)$row['version_id'] : null,
        'export_job_id' => $row['export_job_id'] !== null ? (int)$row['export_job_id'] : null,
        'title' => (string)$row['title'],
        'slug' => (string)$row['slug'],
        'summary' => (string)($row['summary'] ?? ''),
        'body' => (string)($row['body'] ?? ''),
        'category' => (string)($row['category'] ?? ''),
        'difficulty' => (string)$row['difficulty'],
        'status' => (string)$row['status'],
        'featured' => (bool)$row['featured'],
        'video_path' => (string)($row['video_path'] ?? ''),
        'thumbnail_path' => (string)($row['thumbnail_path'] ?? ''),
        'duration_seconds' => $row['duration_seconds'] !== null ? (float)$row['duration_seconds'] : null,
        'published_at' => (string)($row['published_at'] ?? ''),
        'public_url' => '/tutorial.php?slug=' . rawurlencode((string)$row['slug']),
        'video_url' => '/api/tutorial-video.php?slug=' . rawurlencode((string)$row['slug']),
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function mg_screen_recording_stage3_list_jobs(PDO $pdo, int $recordingId, array $user): array
{
    $row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, false);
    $stmt = $pdo->prepare('SELECT * FROM admin_screen_recording_export_jobs WHERE recording_id = ? ORDER BY created_at DESC, id DESC LIMIT 20');
    $stmt->execute([(int)$row['id']]);
    return array_map('mg_screen_recording_stage3_public_job', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_screen_recording_stage3_latest_job(PDO $pdo, int $recordingId, array $user): ?array
{
    $jobs = mg_screen_recording_stage3_list_jobs($pdo, $recordingId, $user);
    return $jobs[0] ?? null;
}

function mg_screen_recording_stage3_fetch_job_for_user(PDO $pdo, int $jobId, array $user, bool $manage = false): array
{
    $stmt = $pdo->prepare('SELECT j.* FROM admin_screen_recording_export_jobs j INNER JOIN admin_screen_recordings r ON r.id = j.recording_id WHERE j.id = ? AND r.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) mg_fail('Export job not found.', 404);
    mg_screen_recordings_fetch_for_user($pdo, (int)$job['recording_id'], $user, $manage);
    return $job;
}

function mg_screen_recording_stage3_list_audio_tracks(PDO $pdo, int $recordingId, array $user): array
{
    $row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, false);
    $stmt = $pdo->prepare('SELECT * FROM admin_screen_recording_audio_tracks WHERE recording_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 50');
    $stmt->execute([(int)$row['id']]);
    return array_map('mg_screen_recording_stage3_public_audio', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_screen_recording_stage3_latest_tutorial(PDO $pdo, int $recordingId, array $user): ?array
{
    $row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, false);
    $stmt = $pdo->prepare('SELECT * FROM public_tutorials WHERE recording_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT 1');
    $stmt->execute([(int)$row['id']]);
    $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);
    return $tutorial ? mg_screen_recording_stage3_public_tutorial($tutorial) : null;
}

function mg_screen_recording_stage3_store_audio(PDO $pdo, int $recordingId, array $user, array $file, array $input = []): array
{
    mg_screen_recording_stage3_require_schema($pdo);
    mg_screen_recording_stage3_prepare_storage();
    $row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) mg_fail('Unable to upload voiceover audio.', 422);
    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) mg_fail('Voiceover upload is invalid.', 422);
    if ($size < 1) mg_fail('Voiceover audio is empty.', 422);
    if ($size > 100 * 1024 * 1024) mg_fail('Voiceover audio is too large.', 422);

    $originalName = trim((string)($file['name'] ?? 'voiceover.webm')) ?: 'voiceover.webm';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!mg_screen_recording_stage3_allowed_audio_extension($extension)) $extension = 'webm';
    $mime = mg_screen_recordings_detect_mime($tmp);
    if (!mg_screen_recording_stage3_allowed_audio_mime($mime)) mg_fail('Unsupported voiceover audio type.', 422);

    $filename = mg_screen_recordings_safe_slug((string)$row['title']) . '-voiceover-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
    $relative = mg_screen_recording_stage3_relative_path('audio', $filename);
    $target = mg_screen_recordings_abs_path($relative);
    if (!$target || !move_uploaded_file($tmp, $target)) mg_fail('Unable to save voiceover audio.', 500);
    @chmod($target, 0640);

    $title = substr(trim((string)($input['title'] ?? 'Voiceover')) ?: 'Voiceover', 0, 180);
    $start = max(0, (float)($input['start_seconds'] ?? 0));
    $volume = min(3, max(0, (float)($input['volume'] ?? 1)));
    $duration = isset($input['duration_seconds']) ? max(0, (float)$input['duration_seconds']) : null;
    $trackType = in_array((string)($input['track_type'] ?? 'voiceover'), ['voiceover', 'uploaded_audio'], true) ? (string)$input['track_type'] : 'voiceover';

    $stmt = $pdo->prepare('INSERT INTO admin_screen_recording_audio_tracks (public_id, recording_id, admin_user_id, track_type, title, file_path, original_filename, mime_type, file_size, duration_seconds, start_seconds, volume, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        mg_screen_recordings_public_id(),
        (int)$row['id'],
        (int)$user['id'],
        $trackType,
        $title,
        $relative,
        substr($originalName, 0, 255),
        substr($mime ?: 'audio/webm', 0, 120),
        $size,
        $duration,
        $start,
        $volume,
        'ready',
    ]);

    $id = (int)$pdo->lastInsertId();
    $track = $pdo->query('SELECT * FROM admin_screen_recording_audio_tracks WHERE id = ' . $id . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return mg_screen_recording_stage3_public_audio($track ?: []);
}

function mg_screen_recording_stage3_create_export_job(PDO $pdo, int $recordingId, array $user, array $manifest, array $input = []): array
{
    mg_screen_recording_stage3_require_schema($pdo);
    $row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);
    if (empty($row['original_path'])) mg_fail('Original recording file is required before export.', 422);

    $saved = mg_screen_recordings_save_manifest($pdo, $recordingId, $manifest);
    $manifest = mg_screen_recordings_decode_manifest($saved['edit_manifest_json'] ?? null);
    $format = in_array((string)($input['format'] ?? 'webm'), ['webm', 'mp4'], true) ? (string)$input['format'] : 'webm';
    $burn = array_key_exists('burn_overlays', $input) ? !empty($input['burn_overlays']) : true;
    $includeAudio = array_key_exists('include_audio', $input) ? !empty($input['include_audio']) : true;
    $muteOriginal = !empty($input['mute_original_audio']);
    $originalVolume = min(3, max(0, (float)($input['original_audio_volume'] ?? 1)));
    $voiceoverVolume = min(3, max(0, (float)($input['voiceover_volume'] ?? 1)));

    $manifest['export'] = [
        'format' => $format,
        'burn_overlays' => $burn,
        'include_audio' => $includeAudio,
        'mute_original_audio' => $muteOriginal,
        'original_audio_volume' => $originalVolume,
        'voiceover_volume' => $voiceoverVolume,
        'requested_at' => gmdate('c'),
        'renderer' => 'ffmpeg',
        'status' => 'queued',
    ];
    $json = json_encode(array_replace_recursive(mg_screen_recordings_manifest_default(), $manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE admin_screen_recordings SET edit_manifest_json = ?, status = 'export_pending', updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$json, $recordingId]);
        $version = $pdo->prepare("INSERT INTO admin_screen_recording_versions (recording_id, admin_user_id, version_label, edit_manifest_json, status, created_at) VALUES (?, ?, ?, ?, 'export_pending', NOW())");
        $version->execute([$recordingId, (int)$user['id'], strtoupper($format) . ' render request', $json]);
        $versionId = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO admin_screen_recording_export_jobs (public_id, recording_id, version_id, admin_user_id, job_type, renderer, requested_format, burn_overlays, include_audio, mute_original_audio, original_audio_volume, voiceover_volume, status, input_path, edit_manifest_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $insert->execute([
            mg_screen_recordings_public_id(),
            $recordingId,
            $versionId,
            (int)$user['id'],
            'render',
            'ffmpeg',
            $format,
            $burn ? 1 : 0,
            $includeAudio ? 1 : 0,
            $muteOriginal ? 1 : 0,
            $originalVolume,
            $voiceoverVolume,
            'queued',
            (string)$row['original_path'],
            $json,
        ]);
        $jobId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    $job = mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, true);
    return mg_screen_recording_stage3_public_job($job);
}

function mg_screen_recording_stage3_ffmpeg_path(): ?string
{
    return mg_runtime_process_resolve('ffmpeg');
}

function mg_screen_recording_stage3_ffmpeg_color(string $value, string $fallback = '0xffffff'): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-f]{6}$/i', $value) === 1) {
        return '0x' . substr($value, 1);
    }
    return $fallback;
}

function mg_screen_recording_stage3_ffmpeg_text(string $value): string
{
    $value = str_replace(["\\", "\r", "\n", ':', '%', "'"], ['\\\\', '', '\\n', '\\:', '\\%', "\\'"], $value);
    return substr($value, 0, 500);
}

function mg_screen_recording_stage3_build_video_filter(array $manifest, bool $burnOverlays): string
{
    $filters = ['scale=trunc(iw/2)*2:trunc(ih/2)*2'];
    if ($burnOverlays) {
        $overlays = is_array($manifest['text_overlays'] ?? null) ? $manifest['text_overlays'] : [];
        foreach ($overlays as $overlay) {
            if (!is_array($overlay)) continue;
            $text = trim((string)($overlay['text'] ?? ''));
            if ($text === '') continue;
            $start = max(0, (float)($overlay['start'] ?? 0));
            $end = max($start + 0.1, (float)($overlay['end'] ?? ($start + 5)));
            $x = min(100, max(0, (float)($overlay['x'] ?? 50))) / 100;
            $y = min(100, max(0, (float)($overlay['y'] ?? 50))) / 100;
            $fontSize = min(120, max(10, (int)($overlay['fontSize'] ?? 28)));
            $color = mg_screen_recording_stage3_ffmpeg_color((string)($overlay['color'] ?? '#ffffff'));
            $filters[] = "drawtext=text='" . mg_screen_recording_stage3_ffmpeg_text($text) . "':x=(w-text_w)*" . number_format($x, 4, '.', '') . ":y=(h-text_h)*" . number_format($y, 4, '.', '') . ":fontsize=" . $fontSize . ":fontcolor=" . $color . ":box=1:boxcolor=black@0.55:boxborderw=12:enable='between(t," . number_format($start, 3, '.', '') . ',' . number_format($end, 3, '.', '') . ")'";
        }
    }
    return implode(',', $filters);
}

function mg_screen_recording_stage3_latest_audio_file(PDO $pdo, int $recordingId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM admin_screen_recording_audio_tracks WHERE recording_id = ? AND status IN ('ready','used') AND deleted_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 1");
    $stmt->execute([$recordingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_screen_recording_stage3_process_export_job(PDO $pdo, int $jobId, array $user): array
{
    mg_screen_recording_stage3_require_schema($pdo);
    mg_screen_recording_stage3_prepare_storage();
    $job = mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, true);
    $recording = mg_screen_recordings_fetch_for_user($pdo, (int)$job['recording_id'], $user, true);

    if (!in_array((string)$job['status'], ['queued', 'failed'], true)) {
        return mg_screen_recording_stage3_public_job($job);
    }

    $ffmpeg = mg_screen_recording_stage3_ffmpeg_path();
    if ($ffmpeg === null) {
        $pdo->prepare("UPDATE admin_screen_recording_export_jobs SET status = 'failed', error_message = ?, updated_at = NOW(), completed_at = NOW() WHERE id = ? LIMIT 1")->execute(['FFmpeg is not available on this server.', $jobId]);
        $pdo->prepare("UPDATE admin_screen_recordings SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ? LIMIT 1")->execute(['FFmpeg is not available on this server.', (int)$recording['id']]);
        return mg_screen_recording_stage3_public_job(mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, true));
    }

    $inputPath = mg_screen_recordings_abs_path((string)($recording['original_path'] ?? ''));
    if (!$inputPath || !is_file($inputPath) || !is_readable($inputPath)) {
        $pdo->prepare("UPDATE admin_screen_recording_export_jobs SET status = 'failed', error_message = ?, updated_at = NOW(), completed_at = NOW() WHERE id = ? LIMIT 1")->execute(['Original recording file is unavailable.', $jobId]);
        return mg_screen_recording_stage3_public_job(mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, true));
    }

    $format = in_array((string)$job['requested_format'], ['webm', 'mp4'], true) ? (string)$job['requested_format'] : 'webm';
    $extension = $format === 'mp4' ? 'mp4' : 'webm';
    $baseName = mg_screen_recordings_safe_slug((string)$recording['title']) . '-edited-' . date('Ymd-His') . '-' . substr((string)$job['public_id'], 0, 8) . '.' . $extension;
    $outputRelative = mg_screen_recording_stage3_relative_path('edited', $baseName);
    $outputPath = mg_screen_recordings_abs_path($outputRelative);
    $logRelative = mg_screen_recording_stage3_relative_path('logs', pathinfo($baseName, PATHINFO_FILENAME) . '.log');
    $logPath = mg_screen_recordings_abs_path($logRelative);
    if (!$outputPath || !$logPath) mg_fail('Unable to prepare export output.', 500);

    $manifest = mg_screen_recordings_decode_manifest((string)($job['edit_manifest_json'] ?? $recording['edit_manifest_json'] ?? ''));
    $trimStart = max(0, (float)($manifest['trim']['start'] ?? 0));
    $trimEnd = isset($manifest['trim']['end']) && $manifest['trim']['end'] !== null ? max($trimStart, (float)$manifest['trim']['end']) : null;
    $trimDuration = $trimEnd !== null && $trimEnd > $trimStart ? $trimEnd - $trimStart : null;
    $videoFilter = mg_screen_recording_stage3_build_video_filter($manifest, (bool)$job['burn_overlays']);
    $audioTrack = mg_screen_recording_stage3_latest_audio_file($pdo, (int)$recording['id']);
    $audioPath = $audioTrack ? mg_screen_recordings_abs_path((string)$audioTrack['file_path']) : null;
    if ($audioPath && !is_file($audioPath)) $audioPath = null;

    $pdo->prepare("UPDATE admin_screen_recording_export_jobs SET status = 'processing', error_message = NULL, started_at = NOW(), updated_at = NOW(), output_path = ?, log_path = ? WHERE id = ? LIMIT 1")->execute([$outputRelative, $logRelative, $jobId]);

    $arguments = ['-y'];
    if ($trimStart > 0) {
        $arguments[] = '-ss';
        $arguments[] = (string)$trimStart;
    }
    $arguments[] = '-i';
    $arguments[] = $inputPath;
    if ($audioPath) {
        $arguments[] = '-i';
        $arguments[] = $audioPath;
    }
    if ($trimDuration !== null) {
        $arguments[] = '-t';
        $arguments[] = (string)$trimDuration;
    }

    $filterParts = ['[0:v]' . $videoFilter . '[vout]'];
    $mapArguments = ['-map', '[vout]'];
    $includeAudio = (bool)$job['include_audio'];
    $muteOriginal = (bool)$job['mute_original_audio'];
    if ($includeAudio && $audioPath && $muteOriginal) {
        $voiceVolume = max(0, (float)$job['voiceover_volume']);
        $filterParts[] = '[1:a]volume=' . number_format($voiceVolume, 2, '.', '') . '[aout]';
        array_push($mapArguments, '-map', '[aout]');
    } elseif ($includeAudio && $audioPath) {
        $origVolume = max(0, (float)$job['original_audio_volume']);
        $voiceVolume = max(0, (float)$job['voiceover_volume']);
        $filterParts[] = '[0:a]volume=' . number_format($origVolume, 2, '.', '') . '[a0];[1:a]volume=' . number_format($voiceVolume, 2, '.', '') . '[a1];[a0][a1]amix=inputs=2:duration=first:dropout_transition=0[aout]';
        array_push($mapArguments, '-map', '[aout]');
    } elseif ($includeAudio && !$muteOriginal) {
        array_push($mapArguments, '-map', '0:a?');
    } else {
        $mapArguments[] = '-an';
    }

    $arguments[] = '-filter_complex';
    $arguments[] = implode(';', $filterParts);
    foreach ($mapArguments as $argument) $arguments[] = $argument;
    if ($format === 'mp4') {
        array_push($arguments, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-movflags', '+faststart');
        if ($includeAudio) array_push($arguments, '-c:a', 'aac', '-b:a', '128k');
    } else {
        array_push($arguments, '-c:v', 'libvpx-vp9', '-b:v', '2M');
        if ($includeAudio) array_push($arguments, '-c:a', 'libopus', '-b:a', '96k');
    }
    $arguments[] = $outputPath;
    $commandHash = hash('sha256', json_encode(['binary'=>$ffmpeg, 'arguments'=>$arguments], JSON_THROW_ON_ERROR));
    $pdo->prepare('UPDATE admin_screen_recording_export_jobs SET ffmpeg_command_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1')->execute([$commandHash, $jobId]);

    $result = mg_runtime_process_run('ffmpeg', $arguments, 3600, 8 * 1024 * 1024);
    $output = trim((string)$result['stdout'] . ((string)$result['stderr'] !== '' ? "\n" . (string)$result['stderr'] : ''));
    $code = (int)$result['code'];
    @file_put_contents($logPath, $output, LOCK_EX);
    @chmod($logPath, 0640);

    if ($code !== 0 || !is_file($outputPath) || filesize($outputPath) === 0) {
        @unlink($outputPath);
        $message = 'FFmpeg export failed. Check renderer diagnostics and export logs.';
        $pdo->prepare("UPDATE admin_screen_recording_export_jobs SET status = 'failed', error_message = ?, updated_at = NOW(), completed_at = NOW() WHERE id = ? LIMIT 1")->execute([$message, $jobId]);
        $pdo->prepare("UPDATE admin_screen_recordings SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$message, (int)$recording['id']]);
        mg_security_log('warning', 'admin.screen_recordings.export_failed', $message, ['recording_id' => (int)$recording['id'], 'job_id' => $jobId, 'exit_code' => $code], (int)$user['id']);
        return mg_screen_recording_stage3_public_job(mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, true));
    }

    @chmod($outputPath, 0640);
    $editedMime = $format === 'mp4' ? 'video/mp4' : 'video/webm';
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE admin_screen_recording_export_jobs SET status = 'exported', output_path = ?, error_message = NULL, completed_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$outputRelative, $jobId]);
        $pdo->prepare("UPDATE admin_screen_recordings SET edited_filename = ?, edited_path = ?, mime_type = ?, status = 'exported', error_message = NULL, updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$baseName, $outputRelative, $editedMime, (int)$recording['id']]);
        $pdo->prepare("INSERT INTO admin_screen_recording_versions (recording_id, admin_user_id, version_label, edit_manifest_json, output_path, status, created_at) VALUES (?, ?, ?, ?, ?, 'exported', NOW())")->execute([(int)$recording['id'], (int)$user['id'], strtoupper($format) . ' rendered export', (string)$job['edit_manifest_json'], $outputRelative]);
        if ($audioTrack) {
            $pdo->prepare("UPDATE admin_screen_recording_audio_tracks SET status = 'used', updated_at = NOW() WHERE id = ? LIMIT 1")->execute([(int)$audioTrack['id']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    mg_audit('admin_screen_recording.export_rendered', 'admin_screen_recording', ['recording_id' => (int)$recording['id'], 'job_id' => $jobId, 'format' => $format], (int)$user['id']);
    return mg_screen_recording_stage3_public_job(mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, true));
}

function mg_screen_recording_stage3_safe_slug_unique(PDO $pdo, string $title, ?int $ignoreId = null): string
{
    $base = mg_screen_recordings_safe_slug($title);
    $slug = $base;
    for ($i = 2; $i < 100; $i++) {
        $sql = 'SELECT id FROM public_tutorials WHERE slug = ? AND deleted_at IS NULL';
        $args = [$slug];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $args[] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        if (!$stmt->fetchColumn()) return $slug;
        $slug = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

function mg_screen_recording_stage3_publish_tutorial(PDO $pdo, int $recordingId, array $user, array $input): array
{
    mg_screen_recording_stage3_require_schema($pdo);
    $recording = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);
    if ((string)$recording['status'] !== 'exported' || empty($recording['edited_path'])) {
        mg_fail('Only exported recordings can be published as tutorials.', 422);
    }
    $title = substr(trim((string)($input['title'] ?? $recording['title'] ?? 'Tutorial')), 0, 180);
    if ($title === '') $title = 'Tutorial';
    $requestedSlug = trim((string)($input['slug'] ?? ''));
    $existingStmt = $pdo->prepare('SELECT * FROM public_tutorials WHERE recording_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT 1');
    $existingStmt->execute([$recordingId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $slugSource = $requestedSlug !== '' ? $requestedSlug : ($existing['slug'] ?? $title);
    $slug = mg_screen_recording_stage3_safe_slug_unique($pdo, $slugSource, $existing ? (int)$existing['id'] : null);
    $status = in_array((string)($input['status'] ?? 'draft'), ['draft', 'published', 'unlisted', 'archived'], true) ? (string)$input['status'] : 'draft';
    $difficulty = in_array((string)($input['difficulty'] ?? 'beginner'), ['beginner', 'intermediate', 'advanced'], true) ? (string)$input['difficulty'] : 'beginner';
    $summary = trim((string)($input['summary'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));
    $category = substr(trim((string)($input['category'] ?? '')), 0, 120);
    $featured = !empty($input['featured']) ? 1 : 0;
    $stmt = $pdo->prepare("SELECT * FROM admin_screen_recording_export_jobs WHERE recording_id = ? AND status = 'exported' ORDER BY completed_at DESC, id DESC LIMIT 1");
    $stmt->execute([$recordingId]);
    $latestJob = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existing) {
        $sql = "UPDATE public_tutorials SET export_job_id = ?, title = ?, slug = ?, summary = ?, body = ?, category = ?, difficulty = ?, status = ?, featured = ?, video_path = ?, thumbnail_path = ?, duration_seconds = ?, published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN NOW() ELSE published_at END, updated_at = NOW() WHERE id = ? LIMIT 1";
        $pdo->prepare($sql)->execute([
            $latestJob ? (int)$latestJob['id'] : null,
            $title,
            $slug,
            $summary !== '' ? $summary : null,
            $body !== '' ? $body : null,
            $category !== '' ? $category : null,
            $difficulty,
            $status,
            $featured,
            (string)$recording['edited_path'],
            !empty($recording['thumbnail_path']) ? (string)$recording['thumbnail_path'] : null,
            $recording['duration_seconds'] !== null ? (float)$recording['duration_seconds'] : null,
            $status,
            (int)$existing['id'],
        ]);
        $tutorialId = (int)$existing['id'];
    } else {
        $sql = 'INSERT INTO public_tutorials (public_id, recording_id, version_id, export_job_id, admin_user_id, title, slug, summary, body, category, difficulty, status, featured, video_path, thumbnail_path, duration_seconds, published_at, created_at, updated_at) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($status === 'published' ? 'NOW()' : 'NULL') . ', NOW(), NOW())';
        $pdo->prepare($sql)->execute([
            mg_screen_recordings_public_id(),
            $recordingId,
            $latestJob ? (int)$latestJob['id'] : null,
            (int)$user['id'],
            $title,
            $slug,
            $summary !== '' ? $summary : null,
            $body !== '' ? $body : null,
            $category !== '' ? $category : null,
            $difficulty,
            $status,
            $featured,
            (string)$recording['edited_path'],
            !empty($recording['thumbnail_path']) ? (string)$recording['thumbnail_path'] : null,
            $recording['duration_seconds'] !== null ? (float)$recording['duration_seconds'] : null,
        ]);
        $tutorialId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT * FROM public_tutorials WHERE id = ? LIMIT 1');
    $stmt->execute([$tutorialId]);
    $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);
    mg_audit('admin_screen_recording.publish_tutorial', 'public_tutorial', ['recording_id' => $recordingId, 'tutorial_id' => $tutorialId, 'status' => $status], (int)$user['id']);
    return mg_screen_recording_stage3_public_tutorial($tutorial ?: []);
}
