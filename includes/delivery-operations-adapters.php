<?php
declare(strict_types=1);

function mg_delivery_adapter_registry(?string $channel = null, ?callable $adapter = null): array|callable|null
{
    static $registry = [];
    if ($channel !== null && $adapter !== null) $registry[strtolower($channel)] = $adapter;
    if ($channel !== null) return $registry[strtolower($channel)] ?? null;
    return $registry;
}

function mg_delivery_register_adapter(string $channel, callable $adapter): void
{
    mg_delivery_adapter_registry(strtolower(trim($channel)), $adapter);
}

function mg_delivery_channel_readiness(?array $config = null): array
{
    $config ??= mg_delivery_config();
    $result = ['in_app'=>['enabled'=>true,'ready'=>true,'reason'=>'durable_notification_row']];
    foreach (['email','sms'] as $channel) {
        $enabled = mg_delivery_channel_enabled($channel, $config);
        $registered = is_callable(mg_delivery_adapter_registry($channel));
        $result[$channel] = [
            'enabled'=>$enabled,
            'ready'=>!$enabled || $registered,
            'adapter_registered'=>$registered,
            'reason'=>$enabled ? ($registered ? 'adapter_registered' : 'provider_adapter_missing') : 'disabled',
        ];
    }
    $pushEnabled = mg_delivery_channel_enabled('push', $config);
    $push = function_exists('mg_pwa_push_config') ? mg_pwa_push_config() : [];
    $pushReady = !$pushEnabled || (
        !empty($push['enabled']) && !empty($push['public_key_configured']) &&
        !empty($push['private_key_configured']) && !empty($push['provider_available'])
    );
    $result['push'] = [
        'enabled'=>$pushEnabled,
        'ready'=>$pushReady,
        'adapter_registered'=>true,
        'reason'=>$pushEnabled ? ($pushReady ? 'pwa_provider_ready' : 'pwa_provider_not_ready') : 'disabled',
    ];
    return $result;
}

function mg_delivery_normalize_result(array $result): array
{
    $outcome = strtolower((string)($result['outcome'] ?? 'retry'));
    if (!in_array($outcome, ['delivered','accepted','retry','suppressed','dead_letter'], true)) $outcome = 'retry';
    return [
        'outcome' => $outcome,
        'provider' => mb_substr(trim((string)($result['provider'] ?? 'internal')), 0, 80),
        'provider_reference' => isset($result['provider_reference']) ? mb_substr(trim((string)$result['provider_reference']), 0, 190) : null,
        'code' => mb_substr(trim((string)($result['code'] ?? $outcome)), 0, 100),
        'message' => mb_substr(trim((string)($result['message'] ?? '')), 0, 500),
        'metadata' => is_array($result['metadata'] ?? null) ? $result['metadata'] : [],
    ];
}

function mg_delivery_default_adapter(PDO $pdo, array $job, array $config): array
{
    $channel = (string)$job['channel'];
    if ($channel === 'in_app') {
        return ['outcome'=>'delivered','provider'=>'microgifter','code'=>'in_app_durable','message'=>'The in-app notification row is the durable delivery record.'];
    }

    if (!mg_delivery_channel_enabled($channel, $config)) {
        return ['outcome'=>'suppressed','provider'=>'microgifter','code'=>'channel_disabled','message'=>ucfirst($channel) . ' delivery is disabled by configuration.'];
    }

    if ($channel === 'push') {
        if ((string)($job['provider'] ?? '') === 'pwa_push' && !empty($job['destination_hash'])) {
            $stmt = $pdo->prepare('SELECT id FROM pwa_notification_deliveries WHERE delivery_job_id=? LIMIT 1');
            $stmt->execute([(int)$job['id']]);
            $deliveryId = (int)($stmt->fetchColumn() ?: 0);
            if ($deliveryId < 1) return ['outcome'=>'dead_letter','provider'=>'pwa_push','code'=>'push_delivery_missing','message'=>'The PWA provider delivery row is missing.'];
            $result = mg_pwa_push_attempt_delivery($pdo, $deliveryId);
            if (!empty($result['sent'])) return ['outcome'=>'accepted','provider'=>'pwa_push','code'=>'provider_accepted','message'=>'Push provider accepted the notification.'];
            $reason = (string)($result['reason'] ?? 'push_failed');
            if (in_array($reason, ['subscription_inactive','subscription_expired'], true)) return ['outcome'=>'suppressed','provider'=>'pwa_push','code'=>$reason,'message'=>'The browser push subscription is unavailable.'];
            if (in_array($reason, ['config_missing','provider_missing'], true)) return ['outcome'=>'dead_letter','provider'=>'pwa_push','code'=>$reason,'message'=>'Push provider configuration is incomplete.'];
            return ['outcome'=>'retry','provider'=>'pwa_push','code'=>$reason,'message'=>'Push delivery was not accepted.'];
        }
        $queued = mg_pwa_push_queue_for_notification($pdo, (int)$job['notification_id']);
        $count = (int)($queued['queued'] ?? 0);
        $reason = (string)($queued['reason'] ?? 'push_queue_failed');
        if ($count > 0 || $reason === 'already_queued') {
            return ['outcome'=>'delivered','provider'=>'pwa_push','code'=>'push_children_queued','message'=>'Per-subscription push delivery jobs were created.','metadata'=>['child_jobs'=>$count]];
        }
        if (in_array($reason, ['no_active_subscription','push_disabled','digest_off','pwa_push_disabled'], true)) {
            return ['outcome'=>'suppressed','provider'=>'pwa_push','code'=>$reason,'message'=>'Push was suppressed because no eligible subscription is available.'];
        }
        return ['outcome'=>'retry','provider'=>'pwa_push','code'=>$reason,'message'=>'Push child jobs could not be queued.'];
    }

    if ($channel === 'email' || $channel === 'sms') {
        return [
            'outcome'=>'dead_letter',
            'provider'=>$channel,
            'code'=>'provider_adapter_missing',
            'message'=>ucfirst($channel) . ' is enabled, but no production provider adapter has been registered.',
        ];
    }

    return ['outcome'=>'dead_letter','provider'=>'microgifter','code'=>'unsupported_channel','message'=>'Unsupported delivery channel.'];
}

function mg_delivery_process_adapter(PDO $pdo, array $job, array $config): array
{
    $adapter = mg_delivery_adapter_registry((string)$job['channel']);
    if (is_callable($adapter)) {
        try {
            return mg_delivery_normalize_result((array)$adapter($pdo, $job, $config));
        } catch (Throwable $error) {
            return mg_delivery_normalize_result(['outcome'=>'retry','provider'=>(string)$job['channel'],'code'=>'adapter_exception','message'=>$error->getMessage()]);
        }
    }
    return mg_delivery_normalize_result(mg_delivery_default_adapter($pdo, $job, $config));
}

function mg_delivery_provider_event(PDO $pdo, int $jobId, string $eventType, array $result, int $attempt): void
{
    if (!mg_delivery_table_exists($pdo, 'mg_delivery_provider_events')) return;
    $eventKey = hash('sha256', implode(':', [$jobId, $attempt, $eventType, (string)($result['code'] ?? '')]));
    $stmt = $pdo->prepare(
        "INSERT INTO mg_delivery_provider_events
         (public_id,delivery_job_id,event_type,provider,provider_reference,event_key,response_code,message,metadata_json,occurred_at,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE id=id"
    );
    $stmt->execute([
        mg_delivery_uuid(), $jobId, $eventType,
        $result['provider'] ?? null, $result['provider_reference'] ?? null,
        $eventKey, $result['code'] ?? null, $result['message'] ?? null,
        mg_delivery_safe_json((array)($result['metadata'] ?? [])),
    ]);
}

function mg_delivery_apply_provider_event(PDO $pdo, string $provider, string $providerReference, string $eventType, array $metadata = []): array
{
    $provider = mb_substr(strtolower(trim($provider)), 0, 80);
    $providerReference = mb_substr(trim($providerReference), 0, 190);
    $eventType = strtolower(trim($eventType));
    if ($provider === '' || $providerReference === '') throw new InvalidArgumentException('Provider and provider reference are required.');
    if (!in_array($eventType, ['accepted','delivered','opened','failed','suppressed'], true)) throw new InvalidArgumentException('Unsupported provider event type.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM notification_delivery_jobs WHERE provider=? AND provider_reference=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$provider,$providerReference]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) throw new RuntimeException('Delivery job was not found for this provider reference.');
        $status = (string)$job['status'];
        if ($eventType === 'accepted' && !in_array($status,['delivered','cancelled','suppressed'],true)) $status='provider_accepted';
        elseif (in_array($eventType,['delivered','opened'],true) && $status!=='cancelled') $status='delivered';
        elseif ($eventType === 'failed' && !in_array($status,['delivered','cancelled'],true)) $status='dead_letter';
        elseif ($eventType === 'suppressed' && !in_array($status,['delivered','cancelled'],true)) $status='suppressed';
        $code = mb_substr(trim((string)($metadata['code'] ?? $eventType)),0,100);
        $message = mb_substr(trim((string)($metadata['message'] ?? '')),0,500);
        $pdo->prepare(
            "UPDATE notification_delivery_jobs SET status=?,accepted_at=IF(?='provider_accepted',COALESCE(accepted_at,NOW()),accepted_at),sent_at=IF(? IN ('provider_accepted','delivered'),COALESCE(sent_at,NOW()),sent_at),delivered_at=IF(?='delivered',COALESCE(delivered_at,NOW()),delivered_at),failed_at=IF(?='dead_letter',NOW(),failed_at),dead_lettered_at=IF(?='dead_letter',COALESCE(dead_lettered_at,NOW()),dead_lettered_at),suppressed_at=IF(?='suppressed',COALESCE(suppressed_at,NOW()),suppressed_at),failure_code=IF(?='dead_letter',?,NULL),failure_message=IF(?='dead_letter',?,NULL),updated_at=NOW() WHERE id=?"
        )->execute([$status,$status,$status,$status,$status,$status,$status,$status,$code,$status,$message,(int)$job['id']]);
        mg_delivery_provider_event($pdo,(int)$job['id'],$eventType,[
            'provider'=>$provider,'provider_reference'=>$providerReference,'code'=>$code,'message'=>$message,'metadata'=>$metadata
        ],max(1,(int)$job['attempt_count']));
        $pdo->commit();
        return ['updated'=>true,'job_id'=>(string)$job['public_id'],'status'=>$status,'event_type'=>$eventType];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
