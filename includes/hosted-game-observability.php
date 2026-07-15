<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-games.php';

function mg_hosted_game_observability_schema_ready(PDO $pdo): bool
{
    foreach (['hosted_game_run_observability','hosted_game_diagnostic_groups','hosted_game_diagnostic_occurrences'] as $table) {
        if (!mg_hosted_game_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_hosted_game_observability_browser(string $userAgent): string
{
    $ua = strtolower($userAgent);
    return match (true) {
        str_contains($ua, 'edg/') => 'Edge',
        str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Opera',
        str_contains($ua, 'firefox/') => 'Firefox',
        str_contains($ua, 'samsungbrowser/') => 'Samsung Internet',
        str_contains($ua, 'chrome/') || str_contains($ua, 'crios/') => 'Chrome',
        str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/') => 'Safari',
        default => 'Other',
    };
}

function mg_hosted_game_observability_device(int $width, string $declared = ''): string
{
    $declared = strtolower(trim($declared));
    if (in_array($declared, ['mobile','tablet','desktop'], true)) return $declared;
    if ($width > 0 && $width < 768) return 'mobile';
    if ($width >= 768 && $width < 1100) return 'tablet';
    return 'desktop';
}

function mg_hosted_game_observability_client(array $input = [], ?string $userAgent = null): array
{
    $width = max(0, min(10000, (int)($input['viewport_width'] ?? $input['width'] ?? 0)));
    $height = max(0, min(10000, (int)($input['viewport_height'] ?? $input['height'] ?? 0)));
    $ua = mb_substr(trim((string)($userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 1000);
    $connection = is_array($input['connection'] ?? null) ? $input['connection'] : [];

    return array_filter([
        'session_id' => preg_match('/^[a-zA-Z0-9_-]{8,100}$/', (string)($input['session_id'] ?? '')) === 1 ? (string)$input['session_id'] : null,
        'browser_family' => mg_hosted_game_observability_browser($ua),
        'device_type' => mg_hosted_game_observability_device($width, (string)($input['device_type'] ?? '')),
        'viewport_width' => $width ?: null,
        'viewport_height' => $height ?: null,
        'pixel_ratio' => isset($input['pixel_ratio']) ? max(0.5, min(10, (float)$input['pixel_ratio'])) : null,
        'orientation' => in_array((string)($input['orientation'] ?? ''), ['portrait','landscape'], true) ? (string)$input['orientation'] : null,
        'platform' => mb_substr(trim((string)($input['platform'] ?? '')), 0, 80) ?: null,
        'locale' => mb_substr(trim((string)($input['locale'] ?? '')), 0, 30) ?: null,
        'timezone_offset' => isset($input['timezone_offset']) ? max(-1440, min(1440, (int)$input['timezone_offset'])) : null,
        'effective_type' => mb_substr(trim((string)($connection['effective_type'] ?? $input['effective_type'] ?? '')), 0, 20) ?: null,
        'save_data' => isset($connection['save_data']) ? (bool)$connection['save_data'] : null,
        'sdk_version' => mb_substr(trim((string)($input['sdk_version'] ?? '')), 0, 40) ?: null,
        'game_version' => mb_substr(trim((string)($input['game_version'] ?? '')), 0, 40) ?: null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
}

function mg_hosted_game_observability_release(PDO $pdo, array $game): array
{
    $releaseId = trim((string)($game['current_release_public_id'] ?? $game['release_id'] ?? ''));
    $version = isset($game['version_number']) ? (int)$game['version_number'] : (isset($game['release_version']) ? (int)$game['release_version'] : 0);
    if ($releaseId !== '' && $version > 0) return ['public_id'=>$releaseId,'version'=>$version];

    $stmt = $pdo->prepare('SELECT hgr.public_id,hgr.version_number FROM hosted_games hg LEFT JOIN hosted_game_releases hgr ON hgr.public_id=hg.current_release_public_id AND hgr.game_id=hg.id WHERE hg.id=? LIMIT 1');
    $stmt->execute([(int)$game['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['public_id'=>trim((string)($row['public_id'] ?? '')) ?: null,'version'=>isset($row['version_number']) ? (int)$row['version_number'] : null];
}

function mg_hosted_game_observability_event(PDO $pdo, array $game, ?int $runId, ?int $playerUserId, string $eventType, array $event = [], array $client = []): string
{
    $release = mg_hosted_game_observability_release($pdo, $game);
    $event['release'] = [
        'public_id' => $release['public_id'],
        'version' => $release['version'],
    ];
    if ($client !== []) $event['client'] = $client;
    $publicId = mg_hosted_game_uuid();
    $pdo->prepare('INSERT INTO hosted_game_events (public_id,game_id,run_id,player_user_id,event_type,event_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$publicId,(int)$game['id'],$runId,$playerUserId,mb_substr($eventType,0,100),mg_hosted_game_json_encode($event,65536)]);
    return $publicId;
}

function mg_hosted_game_observability_normalize_message(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/https?:\/\/[^\s]+/i', '<url>', $value) ?? $value;
    $value = preg_replace('/\b[0-9a-f]{8,}\b/i', '<id>', $value) ?? $value;
    $value = preg_replace('/\b\d+\b/', '<n>', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return mb_substr($value, 0, 500);
}

function mg_hosted_game_observability_diagnostic(PDO $pdo, array $game, ?int $runId, ?int $playerUserId, array $diagnostic, array $client = []): string
{
    if (!mg_hosted_game_observability_schema_ready($pdo)) return '';

    $release = mg_hosted_game_observability_release($pdo, $game);
    $category = strtolower(trim((string)($diagnostic['category'] ?? 'runtime_error')));
    if (preg_match('/^[a-z0-9_.:-]{2,80}$/', $category) !== 1) $category = 'runtime_error';
    $severity = strtolower(trim((string)($diagnostic['severity'] ?? 'error')));
    if (!in_array($severity, ['info','warning','error','critical'], true)) $severity = 'error';
    $title = mb_substr(trim((string)($diagnostic['title'] ?? str_replace(['.','_','-'], ' ', $category))), 0, 180);
    $message = mb_substr(trim((string)($diagnostic['message'] ?? 'Hosted game diagnostic event.')), 0, 500);
    if ($title === '') $title = 'Hosted game diagnostic';
    if ($message === '') $message = $title;
    $browser = mb_substr(trim((string)($client['browser_family'] ?? 'Other')), 0, 80);
    $stack = mb_substr(trim((string)($diagnostic['stack'] ?? '')), 0, 8000);
    $fingerprintSource = implode('|', [
        (string)$game['public_id'],
        (string)($release['public_id'] ?? 'unknown'),
        $browser,
        $category,
        mg_hosted_game_observability_normalize_message($title),
        mg_hosted_game_observability_normalize_message($message),
        mg_hosted_game_observability_normalize_message(strtok($stack, "\n") ?: ''),
    ]);
    $fingerprint = hash('sha256', $fingerprintSource);
    $sample = [
        'message' => $message,
        'stack' => $stack !== '' ? $stack : null,
        'context' => is_array($diagnostic['context'] ?? null) ? $diagnostic['context'] : [],
        'client' => $client,
    ];
    $sampleJson = mg_hosted_game_json_encode($sample, 65536);
    $publicId = mg_hosted_game_uuid();

    $pdo->prepare(
        "INSERT INTO hosted_game_diagnostic_groups
         (public_id,game_id,release_public_id,release_version,fingerprint,category,severity,status,title,message,browser_family,occurrence_count,affected_players,first_seen_at,last_seen_at,sample_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,'open',?,?,?,1,?,NOW(),NOW(),?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
           occurrence_count=occurrence_count+1,
           affected_players=affected_players+VALUES(affected_players),
           severity=IF(FIELD(VALUES(severity),'info','warning','error','critical')>FIELD(severity,'info','warning','error','critical'),VALUES(severity),severity),
           status=IF(status='ignored','ignored','open'),
           resolved_at=IF(status='ignored',resolved_at,NULL),
           resolved_by_user_id=IF(status='ignored',resolved_by_user_id,NULL),
           title=VALUES(title),message=VALUES(message),browser_family=VALUES(browser_family),sample_json=VALUES(sample_json),last_seen_at=NOW(),updated_at=NOW()"
    )->execute([
        $publicId,(int)$game['id'],$release['public_id'],$release['version'],$fingerprint,$category,$severity,
        $title,$message,$browser,$playerUserId ? 1 : 0,$sampleJson
    ]);

    $stmt = $pdo->prepare('SELECT id,public_id FROM hosted_game_diagnostic_groups WHERE game_id=? AND fingerprint=? LIMIT 1');
    $stmt->execute([(int)$game['id'],$fingerprint]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) return '';

    $occurrencePublicId = mg_hosted_game_uuid();
    $context = [
        'category'=>$category,
        'severity'=>$severity,
        'title'=>$title,
        'message'=>$message,
        'stack'=>$stack !== '' ? $stack : null,
        'client'=>$client,
        'context'=>is_array($diagnostic['context'] ?? null) ? $diagnostic['context'] : [],
    ];
    $pdo->prepare('INSERT INTO hosted_game_diagnostic_occurrences (public_id,diagnostic_group_id,game_id,run_id,player_user_id,release_public_id,event_type,context_json,occurred_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([$occurrencePublicId,(int)$group['id'],(int)$game['id'],$runId,$playerUserId,$release['public_id'],$category,mg_hosted_game_json_encode($context,65536)]);

    return (string)$group['public_id'];
}

function mg_hosted_game_observability_run_start(PDO $pdo, array $game, int $runId, array $client, string $sdkVersion, string $gameVersion): void
{
    if (!mg_hosted_game_observability_schema_ready($pdo)) return;
    $release = mg_hosted_game_observability_release($pdo, $game);
    $pdo->prepare(
        'INSERT INTO hosted_game_run_observability (run_id,game_id,release_public_id,release_version,sdk_version,game_version,client_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE release_public_id=VALUES(release_public_id),release_version=VALUES(release_version),sdk_version=VALUES(sdk_version),game_version=VALUES(game_version),client_json=VALUES(client_json),updated_at=NOW()'
    )->execute([$runId,(int)$game['id'],$release['public_id'],$release['version'],mb_substr($sdkVersion,0,40),mb_substr($gameVersion,0,40),$client === [] ? null : mg_hosted_game_json_encode($client,32768)]);
}

function mg_hosted_game_observability_run_update(PDO $pdo, int $runId, string $state, ?int $durationMs = null): void
{
    if (!mg_hosted_game_observability_schema_ready($pdo)) return;
    $durationMs = $durationMs !== null ? max(0, min(86400000, $durationMs)) : null;
    if ($state === 'qualified') {
        $pdo->prepare('UPDATE hosted_game_run_observability SET qualified_at=COALESCE(qualified_at,NOW()),updated_at=NOW() WHERE run_id=?')->execute([$runId]);
    } elseif ($state === 'abandoned') {
        $pdo->prepare('UPDATE hosted_game_run_observability SET abandoned_at=COALESCE(abandoned_at,NOW()),duration_ms=COALESCE(?,duration_ms),updated_at=NOW() WHERE run_id=?')->execute([$durationMs,$runId]);
    } elseif ($state === 'completed') {
        $pdo->prepare('UPDATE hosted_game_run_observability SET duration_ms=COALESCE(?,duration_ms),updated_at=NOW() WHERE run_id=?')->execute([$durationMs,$runId]);
    }
}

function mg_hosted_game_observability_resolve(PDO $pdo, int $gameId, string $diagnosticPublicId, int $actorUserId, string $status): array
{
    if (!in_array($status, ['open','resolved','ignored'], true)) throw new InvalidArgumentException('Invalid diagnostic status.');
    $stmt = $pdo->prepare('SELECT * FROM hosted_game_diagnostic_groups WHERE public_id=? AND game_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$diagnosticPublicId,$gameId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgHostedGameException('Hosted game diagnostic not found.');

    if ($status === 'open') {
        $pdo->prepare("UPDATE hosted_game_diagnostic_groups SET status='open',resolved_at=NULL,resolved_by_user_id=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
    } else {
        $pdo->prepare('UPDATE hosted_game_diagnostic_groups SET status=?,resolved_at=NOW(),resolved_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$status,$actorUserId,(int)$row['id']]);
    }
    $row['status'] = $status;
    return $row;
}
