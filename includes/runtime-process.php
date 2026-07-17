<?php
declare(strict_types=1);

/**
 * Canonical allowlisted process gateway.
 *
 * Web-request code may request only explicitly supported media binaries through
 * this file. Commands are passed to proc_open as argument arrays with shell
 * bypass enabled; request values are never interpreted by a shell.
 */

function mg_runtime_process_candidates(string $binary): array
{
    $definitions = [
        'ffmpeg' => [
            'env' => 'MG_FFMPEG_PATH',
            'paths' => ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/ffmpeg/bin/ffmpeg', '/opt/cpanel/ea-ffmpeg/bin/ffmpeg'],
        ],
        'ffprobe' => [
            'env' => 'MG_FFPROBE_PATH',
            'paths' => ['/usr/bin/ffprobe', '/usr/local/bin/ffprobe', '/opt/ffmpeg/bin/ffprobe', '/opt/cpanel/ea-ffmpeg/bin/ffprobe'],
        ],
    ];
    if (!isset($definitions[$binary])) return [];

    $candidates = [];
    $configured = trim((string)getenv($definitions[$binary]['env']));
    if ($configured !== '') $candidates[] = $configured;
    foreach ($definitions[$binary]['paths'] as $path) $candidates[] = $path;

    return array_values(array_unique($candidates));
}

function mg_runtime_process_resolve(string $binary): ?string
{
    if (!in_array($binary, ['ffmpeg', 'ffprobe'], true)) return null;
    foreach (mg_runtime_process_candidates($binary) as $candidate) {
        if ($candidate === '' || $candidate[0] !== '/' || str_contains($candidate, "\0")) continue;
        $real = realpath($candidate);
        if ($real === false || basename($real) !== $binary || !is_file($real) || !is_executable($real)) continue;
        return $real;
    }
    return null;
}

function mg_runtime_process_run(
    string $binary,
    array $arguments,
    int $timeoutSeconds = 30,
    int $outputLimitBytes = 1048576
): array {
    $path = mg_runtime_process_resolve($binary);
    if ($path === null) {
        return ['available'=>false, 'code'=>127, 'stdout'=>'', 'stderr'=>'Binary is not available.', 'timed_out'=>false, 'binary'=>''];
    }

    $timeoutSeconds = max(1, min(3600, $timeoutSeconds));
    $outputLimitBytes = max(4096, min(8 * 1024 * 1024, $outputLimitBytes));
    $command = [$path];
    foreach ($arguments as $argument) {
        if (!is_scalar($argument) && $argument !== null) {
            throw new InvalidArgumentException('Process arguments must be scalar values.');
        }
        $value = (string)$argument;
        if (str_contains($value, "\0") || strlen($value) > 10000) {
            throw new InvalidArgumentException('Process argument is invalid.');
        }
        $command[] = $value;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = ['PATH'=>'/usr/bin:/bin', 'LANG'=>'C', 'LC_ALL'=>'C'];
    $process = proc_open($command, $descriptors, $pipes, null, $environment, ['bypass_shell'=>true, 'suppress_errors'=>true]);
    if (!is_resource($process)) {
        return ['available'=>true, 'code'=>127, 'stdout'=>'', 'stderr'=>'Unable to start media process.', 'timed_out'=>false, 'binary'=>$path];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $startedAt = microtime(true);
    $status = proc_get_status($process);
    $timedOut = false;

    while (!empty($status['running'])) {
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        if (strlen($stdout) > $outputLimitBytes) $stdout = substr($stdout, 0, $outputLimitBytes);
        if (strlen($stderr) > $outputLimitBytes) $stderr = substr($stderr, 0, $outputLimitBytes);
        if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            usleep(100000);
            $status = proc_get_status($process);
            if (!empty($status['running'])) proc_terminate($process, 9);
            break;
        }
        usleep(20000);
        $status = proc_get_status($process);
    }

    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = isset($status['exitcode']) ? (int)$status['exitcode'] : -1;
    $closeCode = proc_close($process);
    if ($exitCode < 0) $exitCode = (int)$closeCode;
    if ($timedOut) $exitCode = 124;

    return [
        'available'=>true,
        'code'=>$exitCode,
        'stdout'=>substr($stdout, 0, $outputLimitBytes),
        'stderr'=>substr($stderr, 0, $outputLimitBytes),
        'timed_out'=>$timedOut,
        'binary'=>$path,
    ];
}
