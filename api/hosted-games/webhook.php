<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-observability.php';

function mg_hosted_game_webhook_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-','_',$name));
    return trim((string)($_SERVER[$key] ?? ''));
}

$gamePublicId = trim((string)($_GET['game'] ?? ''));
if ($gamePublicId === '') mg_fail('Hosted game webhook identity is required.',422);
$pdo = mg_db();
$game = mg_hosted_game_by_public_id($pdo,$gamePublicId,false);
if (!$game) mg_fail('Hosted game not found.',404);
try {
    $secrets = mg_hosted_game_secrets($pdo,(int)$game['id']);
} catch (Throwable) {
    mg_fail('Hosted game webhook is not configured.',503);
}
$secret = trim((string)$secrets['webhook_secret']);
$body = file_get_contents('php://input') ?: '';
$timestamp = mg_hosted_game_webhook_header('X-Microgifter-Timestamp');
$signature = mg_hosted_game_webhook_header('X-Microgifter-Signature');
$deliveryId = mg_hosted_game_webhook_header('X-Microgifter-Delivery');
$eventTypeHeader = mg_hosted_game_webhook_header('X-Microgifter-Event');
$verified = $secret !== '' && ctype_digit($timestamp) && abs(time()-(int)$timestamp) <= 300 && $signature !== '';
if ($verified) {
    $expected = 'sha256=' . hash_hmac('sha256',$timestamp . '.' . $body,$secret);
    $verified = hash_equals($expected,$signature);
}
if (!$verified) mg_fail('Invalid hosted game webhook signature.',401);
$payload = json_decode($body,true);
if (!is_array($payload)) mg_fail('Invalid hosted game webhook payload.',422);
$eventPublicId = trim((string)($payload['id'] ?? ''));
$eventType = trim((string)($payload['type'] ?? $eventTypeHeader));
$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$rewardId = trim((string)($data['reward_id'] ?? ''));
$externalEventId = trim((string)($data['external_event_id'] ?? ''));
if ($eventPublicId === '' || $eventType === '' || preg_match('/^[a-f0-9-]{36}$/i',$eventPublicId) !== 1) mg_fail('Webhook event identity is invalid.',422);

$pdo->beginTransaction();
try {
    $release = mg_hosted_game_observability_release($pdo,$game);
    $eventJson = [
        'delivery_id'=>$deliveryId ?: null,
        'payload_checksum'=>hash('sha256',$body),
        'release'=>['public_id'=>$release['public_id'],'version'=>$release['version']],
        'data'=>$data,
    ];
    $insert = $pdo->prepare('INSERT IGNORE INTO hosted_game_events (public_id,game_id,run_id,player_user_id,event_type,event_json,created_at) VALUES (?,?,NULL,NULL,?,?,NOW())');
    $insert->execute([$eventPublicId,(int)$game['id'],mb_substr($eventType,0,100),mg_hosted_game_json_encode($eventJson,65536)]);
    if ($insert->rowCount() === 0) {
        $pdo->commit();
        http_response_code(204);
        exit;
    }
    $run = null;
    if ($rewardId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM hosted_game_runs WHERE game_id=? AND reward_public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$game['id'],$rewardId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif ($externalEventId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM hosted_game_runs WHERE game_id=? AND external_event_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([(int)$game['id'],$externalEventId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($run) {
        $status = null;
        $error = null;
        $rewarded = false;
        if (in_array($eventType,['reward.delivered','reward.issued','reward.completed'],true)) {
            $status = 'delivered';
            $rewarded = true;
        } elseif (in_array($eventType,['reward.failed','reward.cancelled','reward.expired'],true)) {
            $status = 'failed';
            $error = trim((string)($data['message'] ?? $data['reason'] ?? 'Reward delivery failed.'));
        } elseif (in_array($eventType,['reward.queued','reward.processing'],true)) {
            $status = 'queued';
        }
        if ($status !== null) {
            $sql = 'UPDATE hosted_game_runs SET status=?,api_status=?,error_message=?,updated_at=NOW()';
            if ($rewarded) $sql .= ',rewarded_at=NOW()';
            $sql .= ' WHERE id=?';
            $pdo->prepare($sql)->execute([$status,$eventType,$error !== null ? mb_substr($error,0,500) : null,(int)$run['id']]);
            $pdo->prepare('UPDATE hosted_game_events SET run_id=?,player_user_id=? WHERE public_id=?')
                ->execute([(int)$run['id'],(int)$run['player_user_id'],$eventPublicId]);
            if ($status === 'failed') {
                mg_hosted_game_observability_diagnostic($pdo,$game,(int)$run['id'],(int)$run['player_user_id'],[
                    'category'=>'reward_failed',
                    'severity'=>'error',
                    'title'=>'Reward delivery failure',
                    'message'=>$error ?: 'The hosted game reward could not be delivered.',
                    'context'=>['event_type'=>$eventType,'reward_id'=>$rewardId,'delivery_id'=>$deliveryId ?: null],
                ]);
            }
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    try {
        mg_hosted_game_observability_diagnostic($pdo,$game,null,null,[
            'category'=>'webhook_failed',
            'severity'=>'error',
            'title'=>'Hosted game webhook processing failed',
            'message'=>mb_substr($error->getMessage(),0,500),
            'context'=>['event_type'=>$eventType,'delivery_id'=>$deliveryId ?: null],
        ]);
    } catch (Throwable) {}
    mg_security_log('error','hosted.game.webhook_failed','Hosted game webhook processing failed.',['game_id'=>$gamePublicId,'event_type'=>$eventType,'message'=>$error->getMessage()],null);
    mg_fail('Unable to process hosted game webhook.',500);
}
http_response_code(204);
