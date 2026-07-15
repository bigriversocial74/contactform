<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-game-analytics-report.php';

function mg_hosted_game_diagnostics_csv(array $headers, array $rows): string
{
    $stream = fopen('php://temp','w+');
    if (!is_resource($stream)) throw new RuntimeException('Unable to prepare diagnostics CSV.');
    fputcsv($stream,$headers);
    foreach ($rows as $row) {
        $values = [];
        foreach ($headers as $header) $values[] = $row[$header] ?? null;
        fputcsv($stream,$values);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return is_string($csv) ? $csv : '';
}

function mg_hosted_game_diagnostics_export(PDO $pdo, array $game, array $input): never
{
    if (!class_exists('ZipArchive')) throw new MgHostedGameException('The PHP Zip extension is required to export diagnostics.');
    if (!mg_hosted_game_observability_schema_ready($pdo)) throw new MgHostedGameException('Hosted Games analytics setup is incomplete.');

    $range = mg_hosted_game_analytics_range($input);
    $payload = mg_hosted_game_analytics_payload($pdo,$game,$input);
    $groups = mg_hosted_game_analytics_rows($pdo,
        "SELECT dg.public_id,dg.release_public_id,dg.release_version,dg.category,dg.severity,dg.status,dg.title,dg.message,dg.browser_family,dg.occurrence_count,dg.first_seen_at,dg.last_seen_at,dg.resolved_at
         FROM hosted_game_diagnostic_groups dg WHERE dg.game_id=? AND dg.last_seen_at>=? AND dg.first_seen_at<? ORDER BY dg.last_seen_at DESC LIMIT 5000",
        [(int)$game['id'],$range['start'],$range['end']]
    );
    $occurrences = mg_hosted_game_analytics_rows($pdo,
        "SELECT dgo.public_id,dg.public_id diagnostic_id,dgo.release_public_id,dgo.event_type,dgo.player_user_id,dgo.run_id,dgo.occurred_at,dgo.context_json
         FROM hosted_game_diagnostic_occurrences dgo INNER JOIN hosted_game_diagnostic_groups dg ON dg.id=dgo.diagnostic_group_id
         WHERE dgo.game_id=? AND dgo.occurred_at>=? AND dgo.occurred_at<? ORDER BY dgo.occurred_at DESC LIMIT 10000",
        [(int)$game['id'],$range['start'],$range['end']]
    );
    foreach ($occurrences as &$occurrence) {
        $occurrence['context_json'] = is_string($occurrence['context_json'] ?? null) ? $occurrence['context_json'] : json_encode($occurrence['context_json'] ?? [],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    unset($occurrence);

    $temp = tempnam(sys_get_temp_dir(),'mg-hg-diagnostics-');
    if (!is_string($temp) || $temp === '') throw new RuntimeException('Unable to prepare diagnostics export.');
    $zipPath = $temp . '.zip';
    @unlink($temp);
    $zip = new ZipArchive();
    if ($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create diagnostics export.');

    $summary = [
        'schema'=>'microgifter.hosted-game-diagnostics/v1',
        'generated_at'=>gmdate('c'),
        'game'=>$payload['game'],
        'range'=>$payload['range'],
        'summary'=>$payload['summary'],
        'health'=>$payload['health'],
        'release_comparison'=>$payload['releases'],
        'limits'=>['diagnostic_groups'=>5000,'occurrences'=>10000],
    ];
    $zip->addFromString('summary.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}');
    $zip->addFromString('diagnostic-groups.csv',mg_hosted_game_diagnostics_csv([
        'public_id','release_public_id','release_version','category','severity','status','title','message','browser_family','occurrence_count','first_seen_at','last_seen_at','resolved_at'
    ],$groups));
    $zip->addFromString('diagnostic-occurrences.csv',mg_hosted_game_diagnostics_csv([
        'public_id','diagnostic_id','release_public_id','event_type','player_user_id','run_id','occurred_at','context_json'
    ],$occurrences));
    $zip->addFromString('README.txt',"Microgifter Hosted Game Diagnostics Export v1\n\nGame: " . (string)$game['name'] . "\nGame ID: " . (string)$game['public_id'] . "\nRange: {$range['start_date']} through {$range['end_date']} UTC\n\nThe export intentionally excludes API credentials, webhook secrets, database credentials, cookies, CSRF tokens, email addresses, and raw IP addresses.\n");
    $zip->close();

    $filename = preg_replace('/[^a-z0-9-]+/i','-',strtolower((string)$game['slug'])) . '-diagnostics-' . gmdate('Ymd-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string)filesize($zipPath));
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}
