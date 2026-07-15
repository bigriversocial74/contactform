<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-observability.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$slug = trim((string)($input['slug'] ?? ''));
$eventType = strtolower(trim((string)($input['event_type'] ?? '')));
if ($slug === '' || preg_match('/^[a-z0-9_.:-]{2,100}$/',$eventType) !== 1) mg_fail('Hosted game telemetry is invalid.',422);

$allowed = [
    'game_loaded','game_startup','run_started','player_qualified','run_completed','run_abandoned',
    'asset_load_failed','sdk_request_failed','sdk_request_slow','runtime_error','manifest_warning',
    'api_slow','webhook_failed','database_failed','reward_failed'
];
if (!in_array($eventType,$allowed,true)) mg_fail('Unsupported hosted game telemetry event.',422);
$pdo = mg_db();
if (!mg_hosted_game_observability_schema_ready($pdo)) mg_fail('Hosted game telemetry is unavailable.',503);
$game = mg_hosted_game_by_slug($pdo,$slug,false);
if (!$game) mg_fail('Hosted game not found or unavailable.',404);

$sessionId = trim((string)($input['client']['session_id'] ?? $input['session_id'] ?? 'unknown'));
$rateKey = 'game:' . (int)$game['id'] . ':session:' . substr(hash('sha256',$sessionId),0,20);
if (function_exists('mg_rate_limit')) mg_rate_limit('hosted.game.telemetry',$rateKey,300,300);

$user = mg_current_user();
$userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
$event = is_array($input['event'] ?? null) ? $input['event'] : [];
$clientInput = is_array($input['client'] ?? null) ? $input['client'] : [];
$clientInput['sdk_version'] = $clientInput['sdk_version'] ?? $input['sdk_version'] ?? null;
$clientInput['game_version'] = $clientInput['game_version'] ?? $input['game_version'] ?? null;
$client = mg_hosted_game_observability_client($clientInput);
mg_hosted_game_json_encode($event,32768);

$run = null;
$runPublicId = trim((string)($input['run_id'] ?? ''));
$runToken = trim((string)($input['run_token'] ?? ''));
if ($runPublicId !== '' || $runToken !== '') {
    if ($userId < 1 || $runPublicId === '' || $runToken === '') mg_fail('Valid game run telemetry authorization is required.',403);
    $stmt = $pdo->prepare('SELECT * FROM hosted_game_runs WHERE public_id=? AND game_id=? AND player_user_id=? LIMIT 1');
    $stmt->execute([$runPublicId,(int)$game['id'],$userId]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$run || !hash_equals((string)$run['run_token_hash'],hash('sha256',$runToken))) mg_fail('Valid game run telemetry authorization is required.',403);
}

try {
    $runId = $run ? (int)$run['id'] : null;
    $storedType = 'telemetry.' . $eventType;
    $eventId = mg_hosted_game_observability_event($pdo,$game,$runId,$userId ?: null,$storedType,$event,$client);
    $durationMs = max(0,min(86400000,(int)($event['duration_ms'] ?? 0)));

    if ($runId !== null) {
        if ($eventType === 'run_started') {
            mg_hosted_game_observability_run_start(
                $pdo,$game,$runId,$client,
                (string)($client['sdk_version'] ?? $input['sdk_version'] ?? ''),
                (string)($client['game_version'] ?? $input['game_version'] ?? '')
            );
        } elseif ($eventType === 'player_qualified') {
            mg_hosted_game_observability_run_update($pdo,$runId,'qualified');
        } elseif ($eventType === 'run_completed') {
            if (!empty($event['qualified'])) mg_hosted_game_observability_run_update($pdo,$runId,'qualified');
            mg_hosted_game_observability_run_update($pdo,$runId,'completed',$durationMs ?: null);
        } elseif ($eventType === 'run_abandoned') {
            mg_hosted_game_observability_run_update($pdo,$runId,'abandoned',$durationMs ?: null);
        }
    }

    $diagnosticTypes = ['asset_load_failed','sdk_request_failed','runtime_error','manifest_warning','webhook_failed','database_failed','reward_failed'];
    $isSlowStartup = $eventType === 'game_startup' && $durationMs >= 3000;
    $isSlowRequest = in_array($eventType,['sdk_request_slow','api_slow'],true) && $durationMs >= 1500;
    $diagnosticId = null;
    if (in_array($eventType,$diagnosticTypes,true) || $isSlowStartup || $isSlowRequest) {
        $severity = (string)($event['severity'] ?? '');
        if ($severity === '') {
            $severity = ($eventType === 'runtime_error' || $eventType === 'reward_failed' || $eventType === 'webhook_failed' || $eventType === 'database_failed' || $durationMs >= 10000) ? 'error' : 'warning';
        }
        $titleMap = [
            'asset_load_failed'=>'Game asset failed to load',
            'sdk_request_failed'=>'SDK request failed',
            'sdk_request_slow'=>'Slow SDK request',
            'api_slow'=>'Slow game API action',
            'runtime_error'=>'JavaScript runtime error',
            'manifest_warning'=>'Manifest validation warning',
            'webhook_failed'=>'Webhook delivery failure',
            'database_failed'=>'Game database failure',
            'reward_failed'=>'Reward delivery failure',
            'game_startup'=>'Slow game startup',
        ];
        $diagnosticId = mg_hosted_game_observability_diagnostic($pdo,$game,$runId,$userId ?: null,[
            'category'=>$eventType,
            'severity'=>$severity,
            'title'=>(string)($event['title'] ?? $titleMap[$eventType] ?? 'Hosted game diagnostic'),
            'message'=>(string)($event['message'] ?? ($durationMs > 0 ? 'Observed duration: ' . $durationMs . ' ms.' : 'Hosted game diagnostic event.')),
            'stack'=>(string)($event['stack'] ?? ''),
            'context'=>array_merge($event,['event_id'=>$eventId]),
        ],$client);
    }
    mg_ok(['recorded'=>true,'diagnostic_id'=>$diagnosticId]);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    mg_security_log('error','hosted.game.telemetry_failed','Unable to record hosted game telemetry.',['game_id'=>(string)$game['public_id'],'event_type'=>$eventType,'message'=>$error->getMessage()],$userId ?: null);
    mg_fail('Unable to record hosted game telemetry.',500);
}
