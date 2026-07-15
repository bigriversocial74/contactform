<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-game-observability.php';

function mg_hosted_game_analytics_range(array $input): array
{
    $days = max(1, min(365, (int)($input['days'] ?? 30)));
    $endInput = trim((string)($input['end'] ?? ''));
    $startInput = trim((string)($input['start'] ?? ''));
    $end = $endInput !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $endInput, new DateTimeZone('UTC')) : new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'));
    if (!$end) throw new InvalidArgumentException('Invalid analytics end date.');
    $start = $startInput !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $startInput, new DateTimeZone('UTC')) : $end->modify('-' . $days . ' days');
    if (!$start || $start >= $end) throw new InvalidArgumentException('Invalid analytics date range.');
    if (($end->getTimestamp() - $start->getTimestamp()) > 366 * 86400) throw new InvalidArgumentException('Analytics date ranges are limited to 366 days.');
    return [
        'start'=>$start->format('Y-m-d H:i:s'),
        'end'=>$end->format('Y-m-d H:i:s'),
        'start_date'=>$start->format('Y-m-d'),
        'end_date'=>$end->modify('-1 day')->format('Y-m-d'),
        'days'=>(int)ceil(($end->getTimestamp() - $start->getTimestamp()) / 86400),
    ];
}

function mg_hosted_game_analytics_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function mg_hosted_game_analytics_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_hosted_game_analytics_release_clause(string $alias, ?string $releasePublicId): array
{
    if ($releasePublicId === null || $releasePublicId === '') return ['', []];
    return [" AND {$alias}.release_public_id=?", [$releasePublicId]];
}

function mg_hosted_game_analytics_event_release_clause(?string $releasePublicId): array
{
    if ($releasePublicId === null || $releasePublicId === '') return ['', []];
    return [" AND JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.release.public_id'))=?", [$releasePublicId]];
}

function mg_hosted_game_analytics_summary(PDO $pdo, array $game, array $range, ?string $releasePublicId): array
{
    $gameId = (int)$game['id'];
    [$runReleaseSql,$runReleaseParams] = mg_hosted_game_analytics_release_clause('hgo', $releasePublicId);
    [$eventReleaseSql,$eventReleaseParams] = mg_hosted_game_analytics_event_release_clause($releasePublicId);
    $runParams = [$gameId,$range['start'],$range['end'],...$runReleaseParams];
    $eventParams = [$gameId,$range['start'],$range['end'],...$eventReleaseParams];

    $loads = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(*) FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND hge.event_type IN ('standard.game_loaded','game_loaded','telemetry.game_loaded'){$eventReleaseSql}",
        $eventParams
    );
    $uniquePlayers = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(DISTINCT r.player_user_id) FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<?{$runReleaseSql}",
        $runParams
    );
    $connectedPlayers = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(DISTINCT hge.player_user_id) FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND hge.event_type='player.connected'{$eventReleaseSql}",
        $eventParams
    );
    $runsStarted = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(*) FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<?{$runReleaseSql}",
        $runParams
    );
    $runsCompleted = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(*) FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<? AND r.completed_at IS NOT NULL{$runReleaseSql}",
        $runParams
    );
    $qualified = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(*) FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<? AND (hgo.qualified_at IS NOT NULL OR r.reward_public_id IS NOT NULL){$runReleaseSql}",
        $runParams
    );
    $abandoned = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(*) FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<? AND hgo.abandoned_at IS NOT NULL{$runReleaseSql}",
        $runParams
    );
    $avgDuration = (float)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COALESCE(AVG(COALESCE(hgo.duration_ms,TIMESTAMPDIFF(MICROSECOND,r.started_at,r.completed_at)/1000)),0) FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<? AND r.completed_at IS NOT NULL{$runReleaseSql}",
        $runParams
    );
    $scoreRow = mg_hosted_game_analytics_rows($pdo,
        "SELECT COALESCE(AVG(r.score),0) AS average_score,COALESCE(MAX(r.score),0) AS highest_score,COALESCE(MIN(r.score),0) AS lowest_score FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<? AND r.score IS NOT NULL{$runReleaseSql}",
        $runParams
    )[0] ?? [];
    $repeatPlayers = (int)mg_hosted_game_analytics_scalar($pdo,
        "SELECT COUNT(*) FROM (SELECT r.player_user_id FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id WHERE r.game_id=? AND r.started_at>=? AND r.started_at<?{$runReleaseSql} GROUP BY r.player_user_id HAVING COUNT(*)>=2) repeated",
        $runParams
    );

    $rewardSql = "SELECT
        COUNT(DISTINCT CASE WHEN r.reward_public_id IS NOT NULL THEN r.id END) AS queued,
        COUNT(DISTINCT CASE WHEN r.status='delivered' OR dij.status='issued' THEN r.id END) AS delivered,
        COUNT(DISTINCT CASE WHEN r.status='failed' OR da.status='failed' OR dij.status IN ('failed','dead_letter') THEN r.id END) AS failed,
        COUNT(DISTINCT CASE WHEN pi.claimed_at IS NOT NULL OR pi.status IN ('verified','redeemed') THEN r.id END) AS claimed,
        COUNT(DISTINCT CASE WHEN pi.redeemed_at IS NOT NULL OR pi.status='redeemed' THEN r.id END) AS redeemed,
        COALESCE(SUM(CASE WHEN da.status='issued' THEN da.quantity ELSE 0 END),0) AS inventory_consumed,
        COALESCE(SUM(CASE WHEN da.status NOT IN ('failed','cancelled','expired') THEN da.unit_value_cents*da.quantity ELSE 0 END),0) AS allocated_value_cents
      FROM hosted_game_runs r
      LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id
      LEFT JOIN distribution_allocations da ON da.public_id=r.reward_public_id
      LEFT JOIN distribution_issuance_jobs dij ON dij.allocation_id=da.id
      LEFT JOIN pppm_items pi ON pi.id=dij.pppm_item_id
      WHERE r.game_id=? AND r.started_at>=? AND r.started_at<?{$runReleaseSql}";
    $reward = mg_hosted_game_analytics_rows($pdo,$rewardSql,$runParams)[0] ?? [];

    return [
        'game_loads'=>$loads,
        'unique_players'=>$uniquePlayers,
        'connected_players'=>$connectedPlayers,
        'runs_started'=>$runsStarted,
        'runs_completed'=>$runsCompleted,
        'qualification_rate'=>$runsStarted > 0 ? round(($qualified / $runsStarted) * 100, 2) : 0,
        'qualified_runs'=>$qualified,
        'abandonment_rate'=>$runsStarted > 0 ? round(($abandoned / $runsStarted) * 100, 2) : 0,
        'abandoned_runs'=>$abandoned,
        'average_play_duration_ms'=>(int)round($avgDuration),
        'average_score'=>(float)($scoreRow['average_score'] ?? 0),
        'highest_score'=>(int)($scoreRow['highest_score'] ?? 0),
        'lowest_score'=>(int)($scoreRow['lowest_score'] ?? 0),
        'repeat_player_rate'=>$uniquePlayers > 0 ? round(($repeatPlayers / $uniquePlayers) * 100, 2) : 0,
        'repeat_players'=>$repeatPlayers,
        'rewards'=>[
            'queued'=>(int)($reward['queued'] ?? 0),
            'delivered'=>(int)($reward['delivered'] ?? 0),
            'failed'=>(int)($reward['failed'] ?? 0),
            'claimed'=>(int)($reward['claimed'] ?? 0),
            'redeemed'=>(int)($reward['redeemed'] ?? 0),
            'inventory_consumed'=>(int)($reward['inventory_consumed'] ?? 0),
            'allocated_value_cents'=>(int)($reward['allocated_value_cents'] ?? 0),
            'cost_per_qualified_player_cents'=>$qualified > 0 ? (int)round(((int)($reward['allocated_value_cents'] ?? 0)) / $qualified) : 0,
        ],
    ];
}

function mg_hosted_game_analytics_timeseries(PDO $pdo, array $game, array $range, ?string $releasePublicId): array
{
    $dates = [];
    $cursor = new DateTimeImmutable($range['start'], new DateTimeZone('UTC'));
    $end = new DateTimeImmutable($range['end'], new DateTimeZone('UTC'));
    while ($cursor < $end) {
        $dates[$cursor->format('Y-m-d')] = ['date'=>$cursor->format('Y-m-d'),'loads'=>0,'runs'=>0,'completed'=>0,'qualified'=>0,'abandoned'=>0,'errors'=>0];
        $cursor = $cursor->modify('+1 day');
    }
    [$eventReleaseSql,$eventReleaseParams] = mg_hosted_game_analytics_event_release_clause($releasePublicId);
    $eventRows = mg_hosted_game_analytics_rows($pdo,
        "SELECT DATE(hge.created_at) metric_date,
          SUM(hge.event_type IN ('standard.game_loaded','game_loaded','telemetry.game_loaded')) loads,
          SUM(hge.event_type IN ('standard.runtime_error','runtime_error','telemetry.runtime_error','telemetry.sdk_request_failed','telemetry.asset_load_failed')) errors
         FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<?{$eventReleaseSql} GROUP BY DATE(hge.created_at)",
        [(int)$game['id'],$range['start'],$range['end'],...$eventReleaseParams]
    );
    foreach ($eventRows as $row) {
        $key = (string)$row['metric_date'];
        if (isset($dates[$key])) {
            $dates[$key]['loads'] = (int)$row['loads'];
            $dates[$key]['errors'] = (int)$row['errors'];
        }
    }
    [$runReleaseSql,$runReleaseParams] = mg_hosted_game_analytics_release_clause('hgo', $releasePublicId);
    $runRows = mg_hosted_game_analytics_rows($pdo,
        "SELECT DATE(r.started_at) metric_date,COUNT(*) runs,SUM(r.completed_at IS NOT NULL) completed,SUM(hgo.qualified_at IS NOT NULL OR r.reward_public_id IS NOT NULL) qualified,SUM(hgo.abandoned_at IS NOT NULL) abandoned
         FROM hosted_game_runs r LEFT JOIN hosted_game_run_observability hgo ON hgo.run_id=r.id
         WHERE r.game_id=? AND r.started_at>=? AND r.started_at<?{$runReleaseSql} GROUP BY DATE(r.started_at)",
        [(int)$game['id'],$range['start'],$range['end'],...$runReleaseParams]
    );
    foreach ($runRows as $row) {
        $key = (string)$row['metric_date'];
        if (isset($dates[$key])) {
            $dates[$key]['runs'] = (int)$row['runs'];
            $dates[$key]['completed'] = (int)$row['completed'];
            $dates[$key]['qualified'] = (int)$row['qualified'];
            $dates[$key]['abandoned'] = (int)$row['abandoned'];
        }
    }
    return array_values($dates);
}

function mg_hosted_game_analytics_breakdowns(PDO $pdo, array $game, array $range, ?string $releasePublicId): array
{
    [$releaseSql,$releaseParams] = mg_hosted_game_analytics_event_release_clause($releasePublicId);
    $baseParams = [(int)$game['id'],$range['start'],$range['end'],...$releaseParams];
    $eventFilter = "hge.event_type IN ('standard.game_loaded','game_loaded','telemetry.game_loaded')";
    $device = mg_hosted_game_analytics_rows($pdo,
        "SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.client.device_type')),''),'unknown') label,COUNT(*) value FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND {$eventFilter}{$releaseSql} GROUP BY label ORDER BY value DESC",
        $baseParams
    );
    $browser = mg_hosted_game_analytics_rows($pdo,
        "SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.client.browser_family')),''),'Other') label,COUNT(*) value FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND {$eventFilter}{$releaseSql} GROUP BY label ORDER BY value DESC LIMIT 12",
        $baseParams
    );
    $viewport = mg_hosted_game_analytics_rows($pdo,
        "SELECT CASE
          WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.client.viewport_width')) AS UNSIGNED)<768 THEN 'Under 768px'
          WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.client.viewport_width')) AS UNSIGNED)<1100 THEN '768–1099px'
          WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.client.viewport_width')) AS UNSIGNED)<1440 THEN '1100–1439px'
          WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.client.viewport_width')) AS UNSIGNED)>=1440 THEN '1440px+'
          ELSE 'Unknown' END label,COUNT(*) value
         FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND {$eventFilter}{$releaseSql} GROUP BY label ORDER BY value DESC",
        $baseParams
    );
    return ['devices'=>$device,'browsers'=>$browser,'viewports'=>$viewport];
}

function mg_hosted_game_analytics_funnels(PDO $pdo, array $game, array $range, ?string $releasePublicId): array
{
    [$releaseSql,$releaseParams] = mg_hosted_game_analytics_event_release_clause($releasePublicId);
    $params = [(int)$game['id'],$range['start'],$range['end'],...$releaseParams];
    $events = mg_hosted_game_analytics_rows($pdo,
        "SELECT REPLACE(hge.event_type,'standard.','') event_type,COUNT(*) occurrences,COUNT(DISTINCT hge.player_user_id) players,COUNT(DISTINCT hge.run_id) runs
         FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND hge.event_type IN ('standard.game_loaded','standard.run_started','standard.level_started','standard.score_updated','standard.level_completed','standard.player_qualified','standard.run_completed','standard.run_abandoned','standard.runtime_error'){$releaseSql}
         GROUP BY hge.event_type ORDER BY FIELD(event_type,'game_loaded','run_started','level_started','score_updated','level_completed','player_qualified','run_completed','run_abandoned','runtime_error')",
        $params
    );
    $levels = mg_hosted_game_analytics_rows($pdo,
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(hge.event_json,'$.level')) level,
          SUM(hge.event_type='standard.level_started') started,
          SUM(hge.event_type='standard.level_completed') completed,
          COUNT(DISTINCT hge.player_user_id) players
         FROM hosted_game_events hge WHERE hge.game_id=? AND hge.created_at>=? AND hge.created_at<? AND hge.event_type IN ('standard.level_started','standard.level_completed') AND JSON_EXTRACT(hge.event_json,'$.level') IS NOT NULL{$releaseSql}
         GROUP BY level ORDER BY MIN(hge.created_at) ASC LIMIT 50",
        $params
    );
    return ['events'=>$events,'levels'=>$levels];
}

function mg_hosted_game_analytics_releases(PDO $pdo, array $game, array $range): array
{
    return mg_hosted_game_analytics_rows($pdo,
        "SELECT hgr.public_id,hgr.version_number,hgr.status,hgr.created_at,hgr.activated_at,
          COUNT(DISTINCT r.id) runs_started,
          COUNT(DISTINCT CASE WHEN r.completed_at IS NOT NULL THEN r.id END) runs_completed,
          COUNT(DISTINCT CASE WHEN hgo.qualified_at IS NOT NULL OR r.reward_public_id IS NOT NULL THEN r.id END) qualified_runs,
          COUNT(DISTINCT CASE WHEN hgo.abandoned_at IS NOT NULL THEN r.id END) abandoned_runs,
          COALESCE(AVG(r.score),0) average_score,
          COALESCE(MAX(r.score),0) highest_score,
          COUNT(DISTINCT CASE WHEN r.status='delivered' THEN r.id END) rewards_delivered,
          (SELECT COUNT(*) FROM hosted_game_events e WHERE e.game_id=hgr.game_id AND e.created_at>=? AND e.created_at<? AND JSON_UNQUOTE(JSON_EXTRACT(e.event_json,'$.release.public_id'))=hgr.public_id AND e.event_type IN ('standard.game_loaded','game_loaded','telemetry.game_loaded')) game_loads,
          (SELECT COUNT(*) FROM hosted_game_diagnostic_groups dg WHERE dg.game_id=hgr.game_id AND dg.release_public_id=hgr.public_id AND dg.first_seen_at<?) diagnostic_groups
         FROM hosted_game_releases hgr
         LEFT JOIN hosted_game_run_observability hgo ON hgo.game_id=hgr.game_id AND hgo.release_public_id=hgr.public_id AND hgo.created_at>=? AND hgo.created_at<?
         LEFT JOIN hosted_game_runs r ON r.id=hgo.run_id
         WHERE hgr.game_id=? GROUP BY hgr.id ORDER BY hgr.version_number DESC",
        [$range['start'],$range['end'],$range['end'],$range['start'],$range['end'],(int)$game['id']]
    );
}

function mg_hosted_game_analytics_diagnostics(PDO $pdo, array $game, array $range, array $input): array
{
    $status = strtolower(trim((string)($input['diagnostic_status'] ?? 'open')));
    if (!in_array($status, ['open','resolved','ignored','all'], true)) $status = 'open';
    $severity = strtolower(trim((string)($input['severity'] ?? 'all')));
    if (!in_array($severity, ['info','warning','error','critical','all'], true)) $severity = 'all';
    $release = trim((string)($input['release_id'] ?? ''));
    $where = ['dg.game_id=?','dg.last_seen_at>=?','dg.first_seen_at<?'];
    $params = [(int)$game['id'],$range['start'],$range['end']];
    if ($status !== 'all') { $where[] = 'dg.status=?'; $params[] = $status; }
    if ($severity !== 'all') { $where[] = 'dg.severity=?'; $params[] = $severity; }
    if ($release !== '') { $where[] = 'dg.release_public_id=?'; $params[] = $release; }
    $rows = mg_hosted_game_analytics_rows($pdo,
        "SELECT dg.public_id,dg.release_public_id,dg.release_version,dg.category,dg.severity,dg.status,dg.title,dg.message,dg.browser_family,dg.occurrence_count,dg.first_seen_at,dg.last_seen_at,dg.resolved_at,
          (SELECT COUNT(DISTINCT dgo.player_user_id) FROM hosted_game_diagnostic_occurrences dgo WHERE dgo.diagnostic_group_id=dg.id AND dgo.player_user_id IS NOT NULL) affected_players,
          dg.sample_json
         FROM hosted_game_diagnostic_groups dg WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(dg.severity,'critical','error','warning','info'),dg.last_seen_at DESC LIMIT 250",
        $params
    );
    foreach ($rows as &$row) {
        $row['occurrence_count'] = (int)$row['occurrence_count'];
        $row['affected_players'] = (int)$row['affected_players'];
        $row['release_version'] = $row['release_version'] !== null ? (int)$row['release_version'] : null;
        $row['sample'] = mg_hosted_game_json_decode($row['sample_json'] ?? null);
        unset($row['sample_json']);
    }
    unset($row);
    return $rows;
}

function mg_hosted_game_analytics_health(PDO $pdo, array $game, array $range): array
{
    $diagnosticCounts = mg_hosted_game_analytics_rows($pdo,
        "SELECT severity,COUNT(*) groups,SUM(occurrence_count) occurrences FROM hosted_game_diagnostic_groups WHERE game_id=? AND status='open' GROUP BY severity",
        [(int)$game['id']]
    );
    $categories = mg_hosted_game_analytics_rows($pdo,
        "SELECT category,COUNT(*) groups,SUM(occurrence_count) occurrences,MAX(last_seen_at) last_seen_at FROM hosted_game_diagnostic_groups WHERE game_id=? AND status='open' GROUP BY category ORDER BY occurrences DESC LIMIT 12",
        [(int)$game['id']]
    );
    $db = mg_hosted_game_database_row($pdo,(int)$game['id'],false);
    $slowStartup = mg_hosted_game_analytics_rows($pdo,
        "SELECT COALESCE(AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(event_json,'$.duration_ms')) AS UNSIGNED)),0) average_ms,COALESCE(MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(event_json,'$.duration_ms')) AS UNSIGNED)),0) maximum_ms,COUNT(*) samples FROM hosted_game_events WHERE game_id=? AND created_at>=? AND created_at<? AND event_type='telemetry.game_startup'",
        [(int)$game['id'],$range['start'],$range['end']]
    )[0] ?? [];
    return [
        'readiness'=>mg_hosted_game_readiness($pdo,$game),
        'database'=>mg_hosted_game_database_public($db),
        'open_diagnostics'=>$diagnosticCounts,
        'open_categories'=>$categories,
        'startup'=>[
            'average_ms'=>(int)round((float)($slowStartup['average_ms'] ?? 0)),
            'maximum_ms'=>(int)($slowStartup['maximum_ms'] ?? 0),
            'samples'=>(int)($slowStartup['samples'] ?? 0),
        ],
    ];
}

function mg_hosted_game_analytics_payload(PDO $pdo, array $game, array $input): array
{
    if (!mg_hosted_game_observability_schema_ready($pdo)) {
        throw new MgHostedGameException('Hosted Games analytics setup is incomplete. Import database/hosted_games_analytics_diagnostics_v1.sql.');
    }
    $range = mg_hosted_game_analytics_range($input);
    $releasePublicId = trim((string)($input['release_id'] ?? '')) ?: null;
    $public = mg_hosted_game_public_record($pdo,$game);
    return [
        'game'=>$public,
        'range'=>$range,
        'release_id'=>$releasePublicId,
        'summary'=>mg_hosted_game_analytics_summary($pdo,$game,$range,$releasePublicId),
        'timeseries'=>mg_hosted_game_analytics_timeseries($pdo,$game,$range,$releasePublicId),
        'breakdowns'=>mg_hosted_game_analytics_breakdowns($pdo,$game,$range,$releasePublicId),
        'funnels'=>mg_hosted_game_analytics_funnels($pdo,$game,$range,$releasePublicId),
        'releases'=>mg_hosted_game_analytics_releases($pdo,$game,$range),
        'diagnostics'=>mg_hosted_game_analytics_diagnostics($pdo,$game,$range,$input),
        'health'=>mg_hosted_game_analytics_health($pdo,$game,$range),
        'generated_at'=>gmdate('c'),
    ];
}
