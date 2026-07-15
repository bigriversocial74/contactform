<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-standard-v1.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST' ? mg_input() : $_GET;
$slug = trim((string)($input['slug'] ?? $_GET['slug'] ?? ''));
if ($slug === '') mg_fail('Hosted game is required.', 422);

$pdo = mg_db();
if (!mg_hosted_game_schema_ready($pdo)) mg_fail('Hosted Games setup is incomplete.', 503);
$game = mg_hosted_game_by_slug($pdo, $slug, false);
if (!$game) mg_fail('Hosted game not found or unavailable.', 404);
$readiness = mg_hosted_game_readiness($pdo, $game);
$manifest = mg_hosted_game_standard_manifest_from_game($game);
$user = mg_current_user();
$userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
$link = $userId > 0 && !empty($game['developer_app_id'])
    ? mg_hosted_game_active_link($pdo, (int)$game['developer_app_id'], $userId)
    : null;

function mg_hosted_game_runtime_session(array $game, array $readiness, array $manifest, ?array $user, ?array $link): array
{
    return [
        'game'=>[
            'id'=>(string)$game['public_id'],
            'name'=>(string)$game['name'],
            'slug'=>(string)$game['slug'],
            'status'=>(string)$game['status'],
            'database_ready'=>(bool)$readiness['database_ready'],
            'integration_ready'=>(bool)$readiness['integration_ready'],
            'standard'=>mg_hosted_game_standard_public_manifest($manifest),
        ],
        'program'=>[
            'id'=>$game['program_public_id'] ?? null,
            'name'=>$game['program_name'] ?? null,
            'campaign_id'=>$game['campaign_public_id'] ?? null,
            'campaign_title'=>$game['campaign_title'] ?? null,
        ],
        'reward'=>[
            'template_id'=>$game['pppm_public_id'] ?? null,
            'title'=>$game['reward_title'] ?? null,
        ],
        'player'=>[
            'signed_in'=>is_array($user),
            'connected'=>is_array($link),
            'display_name'=>is_array($user) ? (string)($user['display_name'] ?? $user['full_name'] ?? 'Microgifter player') : null,
            'inbox_url'=>'/inbox.php',
        ],
        'runtime'=>[
            'sdk_version'=>MG_HOSTED_GAME_STANDARD_SDK_VERSION,
            'standard_version'=>'1.0.0',
            'max_duration_seconds'=>(int)$manifest['session']['max_duration_seconds'],
            'scoring'=>$manifest['scoring'],
            'qualification'=>$manifest['qualification'],
        ],
        'ready'=>(bool)$readiness['publish_ready'],
    ];
}

if ($method === 'GET') {
    mg_ok(mg_hosted_game_runtime_session($game, $readiness, $manifest, is_array($user) ? $user : null, $link));
}

mg_require_method('POST');
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$userId = (int)$user['id'];
if (!$readiness['publish_ready']) mg_fail('This hosted game is not ready for live play.', 503);
if (function_exists('mg_rate_limit')) mg_rate_limit('hosted.game.runtime', 'game:' . (int)$game['id'] . ':user:' . $userId, 180, 300);
$action = strtolower(trim((string)($input['action'] ?? '')));

function mg_hosted_game_runtime_run(PDO $pdo, int $gameId, int $userId, string $runPublicId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM hosted_game_runs WHERE public_id=? AND game_id=? AND player_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$runPublicId,$gameId,$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hosted_game_runtime_token(array $run, string $token): bool
{
    return $token !== '' && hash_equals((string)$run['run_token_hash'], hash('sha256', $token));
}

function mg_hosted_game_runtime_player_key(int $userId): string
{
    return 'microgifter-user-' . $userId;
}

function mg_hosted_game_runtime_require_capability(array $manifest, string $capability): void
{
    if (!mg_hosted_game_standard_has_capability($manifest, $capability)) {
        mg_fail('This hosted game release does not enable the ' . $capability . ' capability.', 403);
    }
}

function mg_hosted_game_runtime_authorized_run(PDO $pdo, array $game, int $userId, array $input, bool $forUpdate = false): array
{
    $runPublicId = trim((string)($input['run_id'] ?? ''));
    $runToken = trim((string)($input['run_token'] ?? ''));
    if ($runPublicId === '' || $runToken === '') mg_fail('Valid game run authorization is required.', 422);
    $run = mg_hosted_game_runtime_run($pdo, (int)$game['id'], $userId, $runPublicId, $forUpdate);
    if (!$run || !mg_hosted_game_runtime_token($run, $runToken)) mg_fail('Valid game run authorization is required.', 403);
    return $run;
}

try {
    if ($action === 'session') {
        $link = mg_hosted_game_active_link($pdo, (int)$game['developer_app_id'], $userId);
        mg_ok(mg_hosted_game_runtime_session($game, $readiness, $manifest, $user, $link));
    }

    if ($action === 'connect') {
        mg_hosted_game_runtime_require_capability($manifest, 'player');
        $link = mg_hosted_game_connect_player($pdo, $game, $userId);
        mg_hosted_game_log_event($pdo,(int)$game['id'],null,$userId,'player.connected',['linked_account_id'=>(string)$link['public_id']]);
        mg_ok(['connected'=>true,'linked_account_id'=>(string)$link['public_id'],'player'=>['display_name'=>(string)($user['display_name'] ?? $user['full_name'] ?? 'Microgifter player')]],'Player connected.');
    }

    if ($action === 'start') {
        mg_hosted_game_runtime_require_capability($manifest, 'runs');
        $link = mg_hosted_game_connect_player($pdo, $game, $userId);
        $configStmt = $pdo->prepare("SELECT dp.public_id AS program_public_id,c.public_id AS campaign_public_id,cpt.public_id AS template_public_id FROM hosted_games hg INNER JOIN distribution_programs dp ON dp.id=hg.distribution_program_id INNER JOIN campaigns c ON c.id=hg.campaign_id INNER JOIN catalog_pppm_templates cpt ON cpt.id=hg.pppm_template_id WHERE hg.id=? AND hg.status='active' LIMIT 1");
        $configStmt->execute([(int)$game['id']]);
        $snapshot = $configStmt->fetch(PDO::FETCH_ASSOC);
        if (!$snapshot) mg_fail('Game reward configuration is unavailable.', 503);
        $pdo->prepare("UPDATE hosted_game_runs SET status='expired',error_message='Superseded by a new game run.',updated_at=NOW() WHERE game_id=? AND player_user_id=? AND status='started'")
            ->execute([(int)$game['id'],$userId]);
        $runPublicId = mg_hosted_game_uuid();
        $runToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $eventId = 'hosted-game.' . str_replace('-', '', $runPublicId);
        $duration = max(30, min(86400, (int)$manifest['session']['max_duration_seconds']));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $duration);
        $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
        $metadata['hosted_game_standard'] = '1.0.0';
        $metadata['game_version'] = (string)$manifest['version'];
        $metadata['sdk_version'] = mb_substr(trim((string)($input['sdk_version'] ?? MG_HOSTED_GAME_STANDARD_SDK_VERSION)), 0, 40);
        $metadataJson = mg_hosted_game_json_encode($metadata, 65536);
        $pdo->prepare("INSERT INTO hosted_game_runs (public_id,game_id,player_user_id,developer_app_id,linked_account_public_id,program_public_id,campaign_public_id,template_public_id,run_token_hash,external_event_id,status,result_json,started_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'started',?,NOW(),?,NOW(),NOW())")
            ->execute([$runPublicId,(int)$game['id'],$userId,(int)$game['developer_app_id'],(string)$link['public_id'],(string)$snapshot['program_public_id'],(string)$snapshot['campaign_public_id'],(string)$snapshot['template_public_id'],hash('sha256',$runToken),$eventId,$metadataJson,$expiresAt]);
        $runId = (int)$pdo->lastInsertId();
        mg_hosted_game_log_event($pdo,(int)$game['id'],$runId,$userId,'run.started',['external_event_id'=>$eventId]);
        mg_hosted_game_log_event($pdo,(int)$game['id'],$runId,$userId,'standard.run_started',['duration_seconds'=>$duration,'game_version'=>(string)$manifest['version']]);
        mg_ok(['run'=>['run_id'=>$runPublicId,'run_token'=>$runToken,'status'=>'started','expires_at'=>gmdate('c',strtotime($expiresAt)),'max_duration_seconds'=>$duration]],'Game run started.',201);
    }

    if ($action === 'complete') {
        mg_hosted_game_runtime_require_capability($manifest, 'runs');
        $runPublicId = trim((string)($input['run_id'] ?? ''));
        $runToken = trim((string)($input['run_token'] ?? ''));
        $qualified = !empty($input['qualified']);
        $scoreInput = $input['score'] ?? null;
        $score = $scoreInput === null || $scoreInput === '' ? null : filter_var($scoreInput, FILTER_VALIDATE_INT);
        $result = is_array($input['result'] ?? null) ? $input['result'] : [];
        if ($runPublicId === '' || $runToken === '' || ($scoreInput !== null && $scoreInput !== '' && $score === false)) mg_fail('The game result is incomplete.',422);
        $qualificationMode = (string)$manifest['qualification']['mode'];
        if ($qualified && $qualificationMode === 'none') mg_fail('This game manifest does not enable reward qualification.',409);
        if ($qualified && $qualificationMode === 'server_review') mg_fail('This game requires a reviewed server-side qualification endpoint before reward issuance.',409);
        $result['hosted_game_standard'] = '1.0.0';
        $resultJson = mg_hosted_game_json_encode($result,65536);
        $pdo->beginTransaction();
        $run = mg_hosted_game_runtime_run($pdo,(int)$game['id'],$userId,$runPublicId,true);
        if (!$run) mg_fail('Game run not found.',404);
        if (!mg_hosted_game_runtime_token($run,$runToken)) mg_fail('The game run token is invalid.',403);
        if (in_array((string)$run['status'],['queued','delivered'],true)) {
            $pdo->commit();
            mg_ok(['duplicate'=>true,'run'=>mg_hosted_game_run_payload($run)],'Reward request already exists.');
        }
        if ((string)$run['status'] !== 'started') mg_fail('This game run cannot be completed again.',409);
        if (strtotime((string)$run['expires_at']) < time()) {
            $pdo->prepare("UPDATE hosted_game_runs SET status='expired',error_message='Game run expired before completion.',updated_at=NOW() WHERE id=?")->execute([(int)$run['id']]);
            $pdo->commit();
            mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'standard.run_abandoned',['reason'=>'expired']);
            mg_fail('This game run expired. Start a new run.',409);
        }
        if (!$qualified) {
            $pdo->prepare("UPDATE hosted_game_runs SET status='completed',score=?,result_json=?,completed_at=NOW(),error_message=NULL,updated_at=NOW() WHERE id=?")
                ->execute([$score,$resultJson,(int)$run['id']]);
            $pdo->commit();
            $updated = mg_hosted_game_runtime_run($pdo,(int)$game['id'],$userId,$runPublicId,false);
            mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'run.completed',['qualified'=>false,'score'=>$score]);
            mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'standard.run_completed',['qualified'=>false,'score'=>$score]);
            mg_ok(['qualified'=>false,'reward_issued'=>false,'run'=>mg_hosted_game_run_payload($updated ?: $run)],'Game run completed.');
        }
        $pdo->prepare("UPDATE hosted_game_runs SET status='issuing',score=?,result_json=?,completed_at=NOW(),error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$score,$resultJson,(int)$run['id']]);
        $pdo->commit();

        try {
            $secrets = mg_hosted_game_secrets($pdo,(int)$game['id']);
            $response = mg_hosted_game_api_request($game,$secrets,'POST','/api/public/v1/rewards/issue.php',[
                'program_id'=>(string)$run['program_public_id'],
                'external_event_id'=>(string)$run['external_event_id'],
                'event_type'=>'hosted_game.reward.earned',
                'recipient'=>['linked_account_id'=>(string)$run['linked_account_public_id']],
                'reward'=>['template_id'=>(string)$run['template_public_id'],'quantity'=>1],
                'metadata'=>[
                    'source'=>'hosted-game',
                    'hosted_game_standard'=>'1.0.0',
                    'hosted_game_id'=>(string)$game['public_id'],
                    'hosted_game_slug'=>(string)$game['slug'],
                    'game_version'=>(string)$manifest['version'],
                    'game_run_id'=>(string)$run['public_id'],
                    'campaign_id'=>(string)$run['campaign_public_id'],
                    'score'=>$score,
                    'result'=>$result,
                    'microgifter_user_id'=>$userId,
                ],
            ],[
                'X-Request-ID: hosted-game-' . str_replace('-','',(string)$run['public_id']),
                'X-Idempotency-Key: ' . (string)$run['external_event_id'],
            ]);
            $rewardId = trim((string)($response['data']['reward_id'] ?? ''));
            $apiStatus = trim((string)($response['data']['status'] ?? 'queued')) ?: 'queued';
            if ($rewardId === '') throw new MgHostedGameException('Microgifter did not return a reward ID.');
            $pdo->prepare("UPDATE hosted_game_runs SET reward_public_id=?,api_status=?,status='queued',error_message=NULL,updated_at=NOW() WHERE id=?")
                ->execute([$rewardId,$apiStatus,(int)$run['id']]);
            $updated = mg_hosted_game_runtime_run($pdo,(int)$game['id'],$userId,$runPublicId,false);
            mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'standard.run_completed',['qualified'=>true,'score'=>$score,'reward_id'=>$rewardId]);
            mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'reward.queued',['reward_id'=>$rewardId,'api_status'=>$apiStatus]);
            mg_ok(['qualified'=>true,'reward_issued'=>true,'run'=>mg_hosted_game_run_payload($updated ?: $run)],'Reward earned and queued for delivery.',202);
        } catch (Throwable $issueError) {
            $message = mb_substr($issueError->getMessage(),0,500);
            $pdo->prepare("UPDATE hosted_game_runs SET status='failed',error_message=?,updated_at=NOW() WHERE id=? AND status='issuing'")
                ->execute([$message,(int)$run['id']]);
            mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'reward.failed',['message'=>$message]);
            throw $issueError;
        }
    }

    if ($action === 'abandon') {
        mg_hosted_game_runtime_require_capability($manifest, 'runs');
        $reason = strtolower(trim((string)($input['reason'] ?? 'player_exit')));
        if (preg_match('/^[a-z0-9_.:-]{2,120}$/', $reason) !== 1) $reason = 'player_exit';
        $result = is_array($input['result'] ?? null) ? $input['result'] : [];
        $result['abandoned'] = true;
        $result['reason'] = $reason;
        $resultJson = mg_hosted_game_json_encode($result, 32768);
        $pdo->beginTransaction();
        $run = mg_hosted_game_runtime_authorized_run($pdo, $game, $userId, $input, true);
        if ((string)$run['status'] !== 'started') {
            $pdo->commit();
            mg_ok(['abandoned'=>false,'run'=>mg_hosted_game_run_payload($run)],'Game run was already closed.');
        }
        $pdo->prepare("UPDATE hosted_game_runs SET status='completed',result_json=?,completed_at=NOW(),error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$resultJson,(int)$run['id']]);
        $pdo->commit();
        $updated = mg_hosted_game_runtime_run($pdo,(int)$game['id'],$userId,(string)$run['public_id'],false);
        mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'standard.run_abandoned',['reason'=>$reason]);
        mg_ok(['abandoned'=>true,'run'=>mg_hosted_game_run_payload($updated ?: $run)],'Game run abandoned.');
    }

    if ($action === 'event') {
        mg_hosted_game_runtime_require_capability($manifest, 'events');
        [$eventType, $event] = mg_hosted_game_standard_event((string)($input['event_type'] ?? ''), $input['event'] ?? []);
        if ((string)$manifest['standard']['compliance'] === 'standard' && !in_array($eventType, $manifest['events'], true)) {
            mg_fail('This event is not declared by the active game manifest.', 403);
        }
        $runId = null;
        if (!empty($input['run_id']) || !empty($input['run_token'])) {
            $run = mg_hosted_game_runtime_authorized_run($pdo, $game, $userId, $input, false);
            $runId = (int)$run['id'];
        }
        if ($eventType === 'score_updated') {
            $eventScore = $event['score'] ?? null;
            if (!is_int($eventScore) && filter_var($eventScore, FILTER_VALIDATE_INT) === false) mg_fail('score_updated requires an integer score.',422);
            $event['score'] = (int)$eventScore;
        }
        $event['standard_version'] = '1.0.0';
        mg_hosted_game_log_event($pdo,(int)$game['id'],$runId,$userId,'standard.' . $eventType,$event);
        mg_ok(['recorded'=>true,'event_type'=>$eventType]);
    }

    if ($action === 'status') {
        mg_hosted_game_runtime_require_capability($manifest, 'runs');
        $runPublicId = trim((string)($input['run_id'] ?? ''));
        $run = mg_hosted_game_runtime_run($pdo,(int)$game['id'],$userId,$runPublicId,false);
        if (!$run) mg_fail('Game run not found.',404);
        mg_ok(['run'=>mg_hosted_game_run_payload($run)]);
    }

    if ($action === 'state_load') {
        mg_hosted_game_runtime_require_capability($manifest, 'state');
        $key = trim((string)($input['key'] ?? 'default'));
        if (preg_match('/^[A-Za-z0-9_.:-]{1,120}$/',$key) !== 1) mg_fail('Invalid game state key.',422);
        $gamePdo = mg_hosted_game_database_pdo($pdo,(int)$game['id']);
        $stmt = $gamePdo->prepare('SELECT state_json,updated_at FROM microgifter_game_player_state WHERE player_key=? AND state_key=? LIMIT 1');
        $stmt->execute([mg_hosted_game_runtime_player_key($userId),$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        mg_ok(['key'=>$key,'state'=>$row ? json_decode((string)$row['state_json'],true) : null,'updated_at'=>$row['updated_at'] ?? null]);
    }

    if ($action === 'state_save') {
        mg_hosted_game_runtime_require_capability($manifest, 'state');
        $key = trim((string)($input['key'] ?? 'default'));
        if (preg_match('/^[A-Za-z0-9_.:-]{1,120}$/',$key) !== 1) mg_fail('Invalid game state key.',422);
        if (!array_key_exists('state',$input)) mg_fail('Game state is required.',422);
        $stateJson = mg_hosted_game_json_encode($input['state'],65536);
        $gamePdo = mg_hosted_game_database_pdo($pdo,(int)$game['id']);
        $gamePdo->prepare('INSERT INTO microgifter_game_player_state (player_key,state_key,state_json,created_at,updated_at) VALUES (?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE state_json=VALUES(state_json),updated_at=NOW()')
            ->execute([mg_hosted_game_runtime_player_key($userId),$key,$stateJson]);
        mg_hosted_game_log_event($pdo,(int)$game['id'],null,$userId,'state.saved',['key'=>$key]);
        mg_ok(['key'=>$key,'saved'=>true],'Game state saved.');
    }

    if ($action === 'score_submit') {
        mg_hosted_game_runtime_require_capability($manifest, 'scores');
        $score = filter_var($input['score'] ?? null,FILTER_VALIDATE_INT);
        $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
        if ($score === false) mg_fail('An integer score is required.',422);
        $run = mg_hosted_game_runtime_authorized_run($pdo, $game, $userId, $input, false);
        $gamePdo = mg_hosted_game_database_pdo($pdo,(int)$game['id']);
        $scorePublicId = mg_hosted_game_uuid();
        $metadata['game_version'] = (string)$manifest['version'];
        $gamePdo->prepare('INSERT INTO microgifter_game_scores (public_id,player_key,score,metadata_json,created_at) VALUES (?,?,?,?,NOW())')
            ->execute([$scorePublicId,mg_hosted_game_runtime_player_key($userId),(int)$score,mg_hosted_game_json_encode($metadata,32768)]);
        mg_hosted_game_log_event($pdo,(int)$game['id'],(int)$run['id'],$userId,'score.submitted',['score'=>(int)$score]);
        mg_ok(['score_id'=>$scorePublicId,'score'=>(int)$score],'Score submitted.',201);
    }

    if ($action === 'leaderboard') {
        mg_hosted_game_runtime_require_capability($manifest, 'leaderboard');
        $limit = max(1,min(100,(int)($input['limit'] ?? 20)));
        $gamePdo = mg_hosted_game_database_pdo($pdo,(int)$game['id']);
        $sort = (string)$manifest['scoring']['sort'] === 'low' ? 'ASC' : 'DESC';
        $aggregate = $sort === 'ASC' ? 'MIN' : 'MAX';
        $stmt = $gamePdo->query('SELECT player_key,' . $aggregate . '(score) AS score,MAX(created_at) AS achieved_at FROM microgifter_game_scores GROUP BY player_key ORDER BY score ' . $sort . ',achieved_at ASC LIMIT ' . $limit);
        $rank = 0;
        $rows = array_map(static function(array $row) use (&$rank): array {
            $rank++;
            return ['rank'=>$rank,'player'=>'Player ' . strtoupper(substr(hash('sha256',(string)$row['player_key']),0,6)),'score'=>(int)$row['score'],'achieved_at'=>$row['achieved_at']];
        },$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        mg_ok(['leaderboard'=>$rows,'sort'=>strtolower($sort)]);
    }

    if ($action === 'track') {
        mg_hosted_game_runtime_require_capability($manifest, 'events');
        $eventType = strtolower(trim((string)($input['event_type'] ?? 'game.event')));
        $event = is_array($input['event'] ?? null) ? $input['event'] : [];
        if (preg_match('/^[a-z0-9_.:-]{2,100}$/',$eventType) !== 1) mg_fail('Invalid game event type.',422);
        mg_hosted_game_json_encode($event,32768);
        mg_hosted_game_log_event($pdo,(int)$game['id'],null,$userId,$eventType,$event);
        mg_ok(['recorded'=>true]);
    }
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','hosted.game.runtime_failed','Hosted game runtime action failed.',['game_id'=>(string)$game['public_id'],'action'=>$action,'message'=>$error->getMessage()],$userId);
    mg_fail('Unable to complete the hosted game request.',500);
}

mg_fail('Invalid hosted game runtime action.',422);
