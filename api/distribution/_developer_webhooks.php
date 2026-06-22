<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

function mg_dev_webhook_json(array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Webhook payload could not be encoded.');
    return $json;
}

function mg_dev_webhook_event(PDO $pdo, ?int $appId, int $merchantUserId, string $eventType, array $data, ?int $sourceEventId = null, ?string $aggregateType = null, ?string $aggregatePublicId = null): ?string
{
    if ($appId === null || $appId < 1) return null;
    $appStmt = $pdo->prepare("SELECT id,public_id,name,status,webhook_url FROM merchant_developer_apps WHERE id=? AND merchant_user_id=? LIMIT 1");
    $appStmt->execute([$appId,$merchantUserId]);
    $app = $appStmt->fetch();
    if (!$app || (string)$app['status'] !== 'active') return null;
    $eventId = mg_distribution_uuid();
    $payload = [
        'id' => $eventId,
        'type' => $eventType,
        'created_at' => gmdate('c'),
        'app_id' => (string)$app['public_id'],
        'data' => $data,
    ];
    $status = trim((string)($app['webhook_url'] ?? '')) === '' ? 'skipped' : 'queued';
    $pdo->prepare("INSERT INTO developer_webhook_events (public_id,app_id,merchant_user_id,source_event_id,event_type,aggregate_type,aggregate_public_id,payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$eventId,$appId,$merchantUserId,$sourceEventId,$eventType,$aggregateType,$aggregatePublicId,mg_dev_webhook_json($payload),$status]);
    return $eventId;
}

function mg_dev_webhook_signature(string $payload, string $timestamp, string $secret): string
{
    return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
}

function mg_dev_webhook_post(string $url, string $payload, array $headers): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>5]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['status'=>$status,'body'=>is_string($body)?$body:'','error'=>$error];
    }
    $opts = ['http'=>['method'=>'POST','header'=>implode("\r\n", $headers),'content'=>$payload,'timeout'=>12,'ignore_errors'=>true]];
    $body = @file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    foreach (($http_response_header ?? []) as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) { $status = (int)$m[1]; break; }
    return ['status'=>$status,'body'=>is_string($body)?$body:'','error'=>$body === false ? 'HTTP request failed.' : ''];
}

function mg_dev_webhook_deliver_one(PDO $pdo, string $eventPublicId, string $workerId = 'developer-webhook-worker'): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT dwe.*,mda.public_id AS app_public_id,mda.webhook_url,mda.webhook_secret_hash,mda.status AS app_status FROM developer_webhook_events dwe INNER JOIN merchant_developer_apps mda ON mda.id=dwe.app_id WHERE dwe.public_id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$eventPublicId]);
        $event = $stmt->fetch();
        if (!$event) throw new RuntimeException('Webhook event not found.');
        if ((string)$event['status'] === 'delivered') { $pdo->commit(); return ['event_id'=>$eventPublicId,'status'=>'delivered','duplicate'=>true]; }
        if (!in_array((string)$event['status'], ['queued','failed'], true)) throw new RuntimeException('Webhook event is not deliverable.');
        if (!empty($event['next_attempt_at']) && strtotime((string)$event['next_attempt_at']) > time()) throw new RuntimeException('Webhook event is not ready.');
        if ((int)$event['attempts'] >= (int)$event['max_attempts']) throw new RuntimeException('Webhook event has exhausted retries.');
        if ((string)$event['app_status'] !== 'active' || trim((string)$event['webhook_url']) === '') {
            $pdo->prepare("UPDATE developer_webhook_events SET status='skipped',failure_message='Webhook URL is not configured.',updated_at=NOW() WHERE id=?")->execute([(int)$event['id']]);
            $pdo->commit();
            return ['event_id'=>$eventPublicId,'status'=>'skipped'];
        }
        $attemptNumber = (int)$event['attempts'] + 1;
        $payload = (string)$event['payload_json'];
        $timestamp = (string)time();
        $secret = (string)($event['webhook_secret_hash'] ?: hash('sha256', (string)$event['app_public_id']));
        $signature = mg_dev_webhook_signature($payload, $timestamp, $secret);
        $deliveryId = mg_distribution_uuid();
        $headers = [
            'Content-Type: application/json',
            'User-Agent: Microgifter-Webhooks/1.0',
            'X-Microgifter-Event: ' . (string)$event['event_type'],
            'X-Microgifter-Delivery: ' . $deliveryId,
            'X-Microgifter-Timestamp: ' . $timestamp,
            'X-Microgifter-Signature: ' . $signature,
        ];
        $pdo->prepare("UPDATE developer_webhook_events SET status='processing',attempts=?,last_attempt_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$attemptNumber,(int)$event['id']]);
        $attemptId = mg_distribution_uuid();
        $pdo->prepare("INSERT INTO developer_webhook_attempts (public_id,webhook_event_id,app_id,endpoint_hash,attempt_number,status,request_checksum,started_at,created_at) VALUES (?,?,?,?,?,'processing',?,NOW(),NOW())")
            ->execute([$attemptId,(int)$event['id'],(int)$event['app_id'],hash('sha256',(string)$event['webhook_url']),$attemptNumber,hash('sha256',$payload)]);
        $attemptDbId = (int)$pdo->lastInsertId();
        $pdo->commit();

        $response = mg_dev_webhook_post((string)$event['webhook_url'], $payload, $headers);
        $ok = $response['status'] >= 200 && $response['status'] < 300;

        $pdo->beginTransaction();
        if ($ok) {
            $pdo->prepare("UPDATE developer_webhook_events SET status='delivered',delivered_at=NOW(),failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$event['id']]);
            $pdo->prepare("UPDATE developer_webhook_attempts SET status='delivered',http_status=?,response_checksum=?,completed_at=NOW() WHERE id=?")->execute([$response['status'],hash('sha256',substr((string)$response['body'],0,65535)),$attemptDbId]);
            $pdo->commit();
            return ['event_id'=>$eventPublicId,'status'=>'delivered','http_status'=>$response['status']];
        }
        $dead = $attemptNumber >= (int)$event['max_attempts'];
        $next = $dead ? null : gmdate('Y-m-d H:i:s', time() + min(7200, 60 * (2 ** max(0, $attemptNumber - 1))));
        $message = substr($response['error'] ?: ('HTTP ' . $response['status']), 0, 500);
        $pdo->prepare("UPDATE developer_webhook_events SET status=?,next_attempt_at=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$dead?'dead_letter':'failed',$next,$message,(int)$event['id']]);
        $pdo->prepare("UPDATE developer_webhook_attempts SET status='failed',http_status=?,response_checksum=?,failure_message=?,completed_at=NOW() WHERE id=?")->execute([$response['status'],hash('sha256',substr((string)$response['body'],0,65535)),$message,$attemptDbId]);
        $pdo->commit();
        return ['event_id'=>$eventPublicId,'status'=>$dead?'dead_letter':'failed','http_status'=>$response['status'],'next_attempt_at'=>$next];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['event_id'=>$eventPublicId,'status'=>'failed','message'=>$e->getMessage()];
    }
}

function mg_dev_webhook_claimable(PDO $pdo, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query("SELECT public_id FROM developer_webhook_events WHERE status IN ('queued','failed') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) AND attempts<max_attempts ORDER BY created_at ASC,id ASC LIMIT " . $limit);
    return array_column($stmt->fetchAll(), 'public_id');
}

function mg_dev_webhook_run(PDO $pdo, int $limit = 25, string $workerId = 'developer-webhook-worker'): array
{
    $events = mg_dev_webhook_claimable($pdo, $limit);
    $results = [];
    foreach ($events as $eventId) $results[] = mg_dev_webhook_deliver_one($pdo, (string)$eventId, $workerId);
    return ['requested_limit'=>max(1,min(100,$limit)),'claimed'=>count($events),'results'=>$results];
}
