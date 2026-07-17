#!/usr/bin/env python3
from __future__ import annotations

import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[1]


def replace_exact(path: str, old: str, new: str, label: str) -> None:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    if old not in source:
        raise RuntimeError(f"Expected {label} not found in {path}")
    target.write_text(source.replace(old, new, 1), encoding="utf-8")


# Centralize admin recording media execution.
replace_exact(
    "includes/admin-screen-recording-stage3.php",
    "require_once __DIR__ . '/admin-screen-recordings.php';\n",
    "require_once __DIR__ . '/admin-screen-recordings.php';\nrequire_once __DIR__ . '/runtime-process.php';\n",
    "stage3 runtime process include",
)
replace_exact(
    "includes/admin-screen-recording-stage3.php",
    """function mg_screen_recording_stage3_ffmpeg_path(): ?string
{
    if (!function_exists('exec') || !function_exists('escapeshellarg')) return null;
    $candidates = [];
    if (function_exists('shell_exec')) {
        $detected = trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($detected !== '') $candidates[] = $detected;
    }
    $candidates[] = 'ffmpeg';
    foreach ($candidates as $candidate) {
        $cmd = escapeshellarg($candidate) . ' -version';
        $out = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $out, $code);
        if ($code === 0) return $candidate;
    }
    return null;
}
""",
    """function mg_screen_recording_stage3_ffmpeg_path(): ?string
{
    return mg_runtime_process_resolve('ffmpeg');
}
""",
    "stage3 ffmpeg resolver",
)
replace_exact(
    "includes/admin-screen-recording-stage3.php",
    """    $parts = [escapeshellarg($ffmpeg), '-y'];
    if ($trimStart > 0) $parts[] = '-ss ' . escapeshellarg((string)$trimStart);
    $parts[] = '-i ' . escapeshellarg($inputPath);
    if ($audioPath) $parts[] = '-i ' . escapeshellarg($audioPath);
    if ($trimDuration !== null) $parts[] = '-t ' . escapeshellarg((string)$trimDuration);

    $filterParts = ['[0:v]' . $videoFilter . '[vout]'];
    $mapParts = ['-map [vout]'];
    $includeAudio = (bool)$job['include_audio'];
    $muteOriginal = (bool)$job['mute_original_audio'];
    if ($includeAudio && $audioPath && $muteOriginal) {
        $voiceVolume = max(0, (float)$job['voiceover_volume']);
        $filterParts[] = '[1:a]volume=' . number_format($voiceVolume, 2, '.', '') . '[aout]';
        $mapParts[] = '-map [aout]';
    } elseif ($includeAudio && $audioPath) {
        $origVolume = max(0, (float)$job['original_audio_volume']);
        $voiceVolume = max(0, (float)$job['voiceover_volume']);
        $filterParts[] = '[0:a]volume=' . number_format($origVolume, 2, '.', '') . '[a0];[1:a]volume=' . number_format($voiceVolume, 2, '.', '') . '[a1];[a0][a1]amix=inputs=2:duration=first:dropout_transition=0[aout]';
        $mapParts[] = '-map [aout]';
    } elseif ($includeAudio && !$muteOriginal) {
        $mapParts[] = '-map 0:a?';
    } else {
        $mapParts[] = '-an';
    }

    $parts[] = '-filter_complex ' . escapeshellarg(implode(';', $filterParts));
    $parts[] = implode(' ', $mapParts);
    if ($format === 'mp4') {
        $parts[] = '-c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -movflags +faststart';
        if ($includeAudio) $parts[] = '-c:a aac -b:a 128k';
    } else {
        $parts[] = '-c:v libvpx-vp9 -b:v 2M';
        if ($includeAudio) $parts[] = '-c:a libopus -b:a 96k';
    }
    $parts[] = escapeshellarg($outputPath);
    $command = implode(' ', $parts);
    $commandHash = hash('sha256', $command);
    $pdo->prepare('UPDATE admin_screen_recording_export_jobs SET ffmpeg_command_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1')->execute([$commandHash, $jobId]);

    $output = [];
    $code = 1;
    @exec($command . ' 2>&1', $output, $code);
    @file_put_contents($logPath, implode("\\n", $output), LOCK_EX);
    @chmod($logPath, 0640);
""",
    """    $arguments = ['-y'];
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
    $output = trim((string)$result['stdout'] . ((string)$result['stderr'] !== '' ? "\\n" . (string)$result['stderr'] : ''));
    $code = (int)$result['code'];
    @file_put_contents($logPath, $output, LOCK_EX);
    @chmod($logPath, 0640);
""",
    "stage3 ffmpeg execution",
)

# Route story duration probing through the same gateway.
replace_exact(
    "api/stories/upload.php",
    "require_once dirname(__DIR__) . '/social/_account_restrictions.php';\n",
    "require_once dirname(__DIR__) . '/social/_account_restrictions.php';\nrequire_once dirname(__DIR__, 2) . '/includes/runtime-process.php';\n",
    "stories runtime process include",
)
replace_exact(
    "api/stories/upload.php",
    "$width = null; $height = null; $durationMs = null;\n",
    "$width = null; $height = null; $durationMs = null; $durationSource = $kind === 'image' ? 'image_dimensions' : 'client';\n",
    "story duration source initialization",
)
replace_exact(
    "api/stories/upload.php",
    """    $durationSeconds = null;
    if (function_exists('shell_exec')) {
        $probe = @shell_exec('command -v ffprobe 2>/dev/null');
        if (is_string($probe) && trim($probe) !== '') {
            $cmd = trim($probe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($tmp) . ' 2>/dev/null';
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '' && is_numeric(trim($out))) $durationSeconds = (float)trim($out);
        }
    }
""",
    """    $durationSeconds = null;
    $probe = mg_runtime_process_run('ffprobe', [
        '-v', 'error',
        '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1',
        $tmp,
    ], 10, 65536);
    $probeValue = trim((string)$probe['stdout']);
    if ((int)$probe['code'] === 0 && $probeValue !== '' && is_numeric($probeValue)) {
        $durationSeconds = (float)$probeValue;
        $durationSource = 'ffprobe';
    }
""",
    "story ffprobe execution",
)
replace_exact(
    "api/stories/upload.php",
    "'max_duration_seconds' => MG_STORIES_MAX_VIDEO_SECONDS, 'uploaded_at' => gmdate('c')",
    "'max_duration_seconds' => MG_STORIES_MAX_VIDEO_SECONDS, 'duration_source' => $durationSource, 'uploaded_at' => gmdate('c')",
    "story metadata duration source",
)

# Remove direct shell discovery from the admin diagnostics endpoint.
replace_exact(
    "api/admin/screen-recordings/diagnostics.php",
    """function mg_admin_recording_diag_find_binary(string $binary, array $absoluteCandidates): array
{
    $result = [
        'available' => false,
        'binary' => $binary,
        'path' => '',
        'version' => '',
        'detail' => 'Not detected.',
    ];

    if (!mg_admin_recording_diag_function_enabled('shell_exec') || !mg_admin_recording_diag_function_enabled('escapeshellarg')) {
        $result['detail'] = 'PHP shell_exec or escapeshellarg is disabled.';
        return $result;
    }

    $paths = [];
    foreach ($absoluteCandidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            $paths[] = $candidate;
        }
    }

    foreach (['command -v ', 'which '] as $prefix) {
        $path = trim((string)@shell_exec($prefix . escapeshellarg($binary) . ' 2>/dev/null'));
        if ($path !== '' && !in_array($path, $paths, true)) $paths[] = $path;
    }

    foreach ($paths as $path) {
        if ($path === '' || str_contains($path, "\\n") || str_contains($path, "\\r")) continue;
        $versionOutput = (string)@shell_exec(escapeshellarg($path) . ' -version 2>&1');
        $line = mg_admin_recording_diag_first_line($versionOutput);
        if ($line !== '') {
            return [
                'available' => true,
                'binary' => $binary,
                'path' => $path,
                'version' => $line,
                'detail' => 'Detected and executable.',
            ];
        }
    }

    return $result;
}
""",
    """function mg_admin_recording_diag_find_binary(string $binary, array $absoluteCandidates): array
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
    $line = mg_admin_recording_diag_first_line((string)$probe['stdout'] . "\\n" . (string)$probe['stderr']);
    return [
        'available' => (int)$probe['code'] === 0,
        'binary' => $binary,
        'path' => $path,
        'version' => $line,
        'detail' => (int)$probe['code'] === 0 ? 'Detected through the allowlisted process gateway.' : 'Binary exists but the version probe failed.',
    ];
}
""",
    "diagnostics binary probe",
)
replace_exact(
    "api/admin/screen-recordings/diagnostics.php",
    """$functions = [
    'shell_exec' => mg_admin_recording_diag_function_enabled('shell_exec'),
    'exec' => mg_admin_recording_diag_function_enabled('exec'),
    'proc_open' => mg_admin_recording_diag_function_enabled('proc_open'),
    'escapeshellarg' => mg_admin_recording_diag_function_enabled('escapeshellarg'),
];
$ffmpeg = mg_admin_recording_diag_find_binary('ffmpeg', ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/ffmpeg/bin/ffmpeg', '/opt/cpanel/ea-ffmpeg/bin/ffmpeg']);
$ffprobe = mg_admin_recording_diag_find_binary('ffprobe', ['/usr/bin/ffprobe', '/usr/local/bin/ffprobe', '/opt/ffmpeg/bin/ffprobe', '/opt/cpanel/ea-ffmpeg/bin/ffprobe']);
$rendererReady = $ffmpeg['available'] && $functions['exec'] && $functions['escapeshellarg'];
""",
    """$functions = [
    'process_gateway' => function_exists('mg_runtime_process_run'),
    'proc_open' => mg_admin_recording_diag_function_enabled('proc_open'),
];
$ffmpeg = mg_admin_recording_diag_find_binary('ffmpeg', []);
$ffprobe = mg_admin_recording_diag_find_binary('ffprobe', []);
$rendererReady = $ffmpeg['available'] && $functions['process_gateway'] && $functions['proc_open'];
""",
    "diagnostics process readiness",
)
replace_exact(
    "api/admin/screen-recordings/diagnostics.php",
    "if (!$functions['exec'] || !$functions['escapeshellarg']) $warnings[] = 'PHP exec or escapeshellarg is disabled. Server-side FFmpeg rendering cannot run from PHP until this is enabled or moved to a worker/server.';\n",
    "if (!$functions['process_gateway'] || !$functions['proc_open']) $warnings[] = 'The allowlisted process gateway is unavailable. Server-side media rendering cannot run until proc_open is enabled.';\n",
    "diagnostics warning",
)

# Make the audit command-gateway and Throwable checks structural and exact.
audit_path = ROOT / "scripts/audit_repository_production_quality_v2.php"
audit = audit_path.read_text(encoding="utf-8")
anchor = """function qa_check(array &$categories, string $category, string $id, string $label, int $points, bool $passed, string $detail = ''): void
"""
helper = """function qa_throwable_catch_blocks(string $content): array
{
    $tokens = token_get_all($content);
    $blocks = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CATCH) continue;
        $signature = '';
        $variable = '';
        while (++$i < $count && $tokens[$i] !== '(') {}
        $depth = 1;
        while (++$i < $count && $depth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '(') $depth++;
            if ($text === ')') $depth--;
            if ($depth > 0) {
                $signature .= $text;
                if (is_array($token) && $token[0] === T_VARIABLE) $variable = $text;
            }
        }
        if ($variable === '' || preg_match('/(?:^|[|&\\s\\\\])Throwable(?:[|&\\s]|$)/i', $signature) !== 1) continue;
        while (++$i < $count && $tokens[$i] !== '{') {}
        if ($i >= $count) continue;
        $braceDepth = 1;
        $body = '';
        while (++$i < $count && $braceDepth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '{') $braceDepth++;
            if ($text === '}') $braceDepth--;
            if ($braceDepth > 0) $body .= $text;
        }
        $blocks[] = ['variable'=>$variable, 'body'=>$body];
    }
    return $blocks;
}

"""
if helper not in audit:
    if anchor not in audit:
        raise RuntimeError("Audit helper anchor not found")
    audit = audit.replace(anchor, helper + anchor, 1)
old_commands = """$evalFindings = $commandFindings = $unserializeFindings = $dynamicIncludeFindings = [];
foreach ($webPhp as $path) {
    $content = qa_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/\\beval\\s*\\(/i', $content) || preg_match('/\\bassert\\s*\\(\\s*[\"\\']/i', $content)) $evalFindings[] = $path;
    if (preg_match('/\\b(?:shell_exec|system|passthru|proc_open|popen|pcntl_exec)\\s*\\(/i', $content)) $commandFindings[] = $path;
    if (preg_match('/\\bunserialize\\s*\\(/i', $content) && !str_contains($content, 'allowed_classes')) $unserializeFindings[] = $path;
    if (preg_match('/\\b(?:include|include_once|require|require_once)\\s*[\\(]?\\s*\\$_(?:GET|POST|REQUEST|COOKIE)/i', $content)) $dynamicIncludeFindings[] = $path;
}
qa_check($categories, 'Dangerous runtime primitives', 'eval', 'No eval or string-assert execution in web-accessible PHP', 3, $evalFindings === [], qa_list($evalFindings));
qa_check($categories, 'Dangerous runtime primitives', 'commands', 'No operating-system command functions in web-accessible PHP', 3, $commandFindings === [], qa_list($commandFindings));
"""
new_commands = """$evalFindings = $commandFindings = $unserializeFindings = $dynamicIncludeFindings = [];
$processGatewayPath = 'includes/runtime-process.php';
$processGateway = qa_text($root, $processGatewayPath);
$processGatewaySafe = $processGateway !== ''
    && str_contains($processGateway, "['ffmpeg', 'ffprobe']")
    && str_contains($processGateway, "['bypass_shell'=>true")
    && preg_match('/proc_open\\s*\\(\\s*\\$command/i', $processGateway) === 1
    && preg_match('/\\$_(?:GET|POST|REQUEST|COOKIE)/i', $processGateway) !== 1
    && preg_match('/\\b(?:shell_exec|system|passthru|popen|pcntl_exec)\\s*\\(/i', $processGateway) !== 1;
foreach ($webPhp as $path) {
    $content = qa_text($root, $path);
    if ($content === '') continue;
    if (preg_match('/\\beval\\s*\\(/i', $content) || preg_match('/\\bassert\\s*\\(\\s*[\"\\']/i', $content)) $evalFindings[] = $path;
    if ($path !== $processGatewayPath && preg_match('/\\b(?:shell_exec|system|passthru|proc_open|popen|pcntl_exec)\\s*\\(/i', $content)) $commandFindings[] = $path;
    if (preg_match('/\\bunserialize\\s*\\(/i', $content) && !str_contains($content, 'allowed_classes')) $unserializeFindings[] = $path;
    if (preg_match('/\\b(?:include|include_once|require|require_once)\\s*[\\(]?\\s*\\$_(?:GET|POST|REQUEST|COOKIE)/i', $content)) $dynamicIncludeFindings[] = $path;
}
qa_check($categories, 'Dangerous runtime primitives', 'eval', 'No eval or string-assert execution in web-accessible PHP', 3, $evalFindings === [], qa_list($evalFindings));
qa_check($categories, 'Dangerous runtime primitives', 'commands', 'Operating-system commands are isolated to the audited allowlisted process gateway', 3, $commandFindings === [] && $processGatewaySafe, qa_list(array_merge($commandFindings, $processGatewaySafe ? [] : ['invalid-process-gateway'])));
"""
if old_commands not in audit:
    raise RuntimeError("Audit command scanner block not found")
audit = audit.replace(old_commands, new_commands, 1)
old_raw = """    if (preg_match('/catch\\s*\\(\\s*Throwable\\s+\\$([A-Za-z_][A-Za-z0-9_]*)\\s*\\)\\s*\\{.{0,1600}(?:echo|die|mg_fail)\\s*\\([^;]{0,450}\\$\\1->getMessage\\s*\\(/is', $content)) $rawThrowableFindings[] = $path;
"""
new_raw = """    foreach (qa_throwable_catch_blocks($content) as $catchBlock) {
        $variable = preg_quote((string)$catchBlock['variable'], '/');
        if (preg_match('/(?:echo|die|mg_fail)\\s*\\([^;]{0,450}' . $variable . '\\s*->\\s*getMessage\\s*\\(/is', (string)$catchBlock['body'])) {
            $rawThrowableFindings[] = $path;
            break;
        }
    }
"""
if old_raw not in audit:
    raise RuntimeError("Audit Throwable scanner line not found")
audit = audit.replace(old_raw, new_raw, 1)
audit_path.write_text(audit, encoding="utf-8")

print("Applied final production audit hardening transformations.")
