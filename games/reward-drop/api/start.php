<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

rd_require_post();
$user = rd_require_user_json();
$input = rd_input();
rd_require_csrf($input);
$config = rd_config();
$pdo = mg_db();
$readiness = rd_readiness($pdo, $config);

if (!$readiness['configured'] || !$readiness['credential_found'] || !$readiness['app_live'] || !$readiness['key_live'] || !$readiness['scopes_ready']) {
    rd_json(['ok' => false, 'message' => 'Reward Drop requires an active live developer app, live credential, reward scopes, program, template, and webhook secret.'], 503);
}

$userId = (int)$user['id'];
$appId = (int)$readiness['app_id'];
$link = rd_active_link($pdo, $appId, $userId);
if ($link === null) rd_json(['ok' => false, 'message' => 'Connect your Microgifter account before playing.', 'needs_link' => true], 409);

$cooldown = rd_cooldown($pdo, $userId, $config);
if (!$cooldown['eligible']) {
    rd_json(['ok' => false, 'message' => 'Your next Reward Drop will be available after the cooldown.', 'cooldown' => $cooldown], 429);
}

$pdo->prepare("UPDATE reward_drop_runs SET status='expired',updated_at=NOW() WHERE user_id=? AND status='started' AND expires_at<NOW()")
    ->execute([$userId]);

$runId = rd_uuid();
$runToken = rd_b64url_encode(random_bytes(32));
$eventId = 'reward-drop.' . str_replace('-', '', $runId);
$expiresAt = gmdate('Y-m-d H:i:s', time() + (int)$config['run_ttl_seconds']);

try {
    $stmt = $pdo->prepare("INSERT INTO reward_drop_runs (public_id,user_id,developer_app_id,linked_account_public_id,program_public_id,template_public_id,run_token_hash,external_event_id,target_score,status,started_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'started',NOW(),?,NOW(),NOW())");
    $stmt->execute([
        $runId,
        $userId,
        $appId,
        (string)$link['public_id'],
        (string)$config['program_id'],
        (string)$config['template_id'],
        hash('sha256', $runToken),
        $eventId,
        (int)$config['target_score'],
        $expiresAt,
    ]);
} catch (Throwable $error) {
    rd_json(['ok' => false, 'message' => 'Reward Drop is not ready. Confirm the game SQL migration was imported.'], 503);
}

rd_json([
    'ok' => true,
    'run' => [
        'run_id' => $runId,
        'run_token' => $runToken,
        'target_score' => (int)$config['target_score'],
        'duration_seconds' => (int)$config['duration_seconds'],
        'minimum_play_seconds' => (int)$config['minimum_play_seconds'],
        'expires_at' => gmdate('c', strtotime($expiresAt)),
    ],
]);
