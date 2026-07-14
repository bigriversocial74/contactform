<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

rd_require_post();
$user = rd_require_user_json();
$input = rd_input();
rd_require_csrf($input);
$config = rd_config();
$pdo = mg_db();

$runId = trim((string)($input['run_id'] ?? ''));
$runToken = trim((string)($input['run_token'] ?? ''));
$score = filter_var($input['score'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 500]]);
if ($runId === '' || $runToken === '' || $score === false) {
    rd_json(['ok' => false, 'message' => 'The game result is incomplete.'], 422);
}

$userId = (int)$user['id'];
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM reward_drop_runs WHERE public_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$runId, $userId]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$run) throw new DomainException('Game run not found.');

    if (in_array((string)$run['status'], ['queued','delivered'], true)) {
        $pdo->commit();
        rd_json(['ok' => true, 'duplicate' => true, 'run' => rd_run_payload($run)]);
    }
    if ((string)$run['status'] !== 'started') throw new DomainException('This game run cannot be submitted again.');
    if (!hash_equals((string)$run['run_token_hash'], hash('sha256', $runToken))) throw new DomainException('The game run token is invalid.');
    if (strtotime((string)$run['expires_at']) < time()) {
        $pdo->prepare("UPDATE reward_drop_runs SET status='expired',error_message='Game run expired before completion.',updated_at=NOW() WHERE id=?")->execute([(int)$run['id']]);
        $pdo->commit();
        rd_json(['ok' => false, 'message' => 'This game run expired. Start a new game.'], 409);
    }

    $elapsed = time() - strtotime((string)$run['started_at']);
    if ($elapsed < (int)$config['minimum_play_seconds']) throw new DomainException('The game was completed too quickly to qualify.');
    if ((int)$score < (int)$run['target_score']) throw new DomainException('Reach the target score before claiming the reward.');

    $pdo->prepare("UPDATE reward_drop_runs SET score=?,status='issuing',completed_at=NOW(),error_message=NULL,updated_at=NOW() WHERE id=?")
        ->execute([(int)$score, (int)$run['id']]);
    $pdo->commit();
} catch (DomainException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    rd_json(['ok' => false, 'message' => $error->getMessage()], 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    rd_json(['ok' => false, 'message' => 'Unable to validate the game result.'], 500);
}

try {
    $response = rd_api_request($config, 'POST', '/api/public/v1/rewards/issue.php', [
        'program_id' => (string)$run['program_public_id'],
        'external_event_id' => (string)$run['external_event_id'],
        'event_type' => 'game.reward.earned',
        'recipient' => [
            'linked_account_id' => (string)$run['linked_account_public_id'],
        ],
        'reward' => [
            'template_id' => (string)$run['template_public_id'],
            'quantity' => 1,
        ],
        'metadata' => [
            'source' => 'reward-drop-game',
            'game_run_id' => (string)$run['public_id'],
            'score' => (int)$score,
            'target_score' => (int)$run['target_score'],
            'microgifter_user_id' => $userId,
        ],
    ], [
        'X-Request-ID: reward-drop-' . str_replace('-', '', (string)$run['public_id']),
        'X-Idempotency-Key: ' . (string)$run['external_event_id'],
    ]);

    $rewardId = trim((string)($response['data']['reward_id'] ?? ''));
    $apiStatus = trim((string)($response['data']['status'] ?? 'queued')) ?: 'queued';
    if ($rewardId === '') throw new RuntimeException('Microgifter did not return a reward ID.');

    $pdo->prepare("UPDATE reward_drop_runs SET reward_public_id=?,api_status=?,status='queued',error_message=NULL,updated_at=NOW() WHERE public_id=? AND user_id=?")
        ->execute([$rewardId, $apiStatus, $runId, $userId]);
    $updated = rd_find_run($pdo, $runId, $userId);
    rd_json([
        'ok' => true,
        'message' => 'Reward earned. Microgifter is delivering it to your Inbox.',
        'run' => $updated ? rd_run_payload($updated) : ['run_id' => $runId, 'status' => 'queued', 'reward_id' => $rewardId, 'inbox_url' => '/inbox.php'],
    ], 202);
} catch (Throwable $error) {
    $pdo->prepare("UPDATE reward_drop_runs SET status='failed',error_message=?,updated_at=NOW() WHERE public_id=? AND user_id=? AND status='issuing'")
        ->execute([mb_substr($error->getMessage(), 0, 500), $runId, $userId]);
    rd_json(['ok' => false, 'message' => $error->getMessage()], 502);
}
