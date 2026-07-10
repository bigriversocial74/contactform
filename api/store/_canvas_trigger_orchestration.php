<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_trigger_engine_runner.php';

const MG_TRIGGER_INGESTION_CHECKPOINTS = 'mg_store_trigger_ingestion_checkpoints';
const MG_TRIGGER_ORCHESTRATION_POLICIES = 'mg_store_trigger_orchestration_policies';
const MG_TRIGGER_SCHEDULER_RUNS = 'mg_store_trigger_scheduler_runs';
const MG_TRIGGER_DEAD_LETTERS = 'mg_store_trigger_dead_letters';

function mg_trigger_orchestration_tables(): array
{
    return [
        MG_TRIGGER_INGESTION_CHECKPOINTS,
        MG_TRIGGER_ORCHESTRATION_POLICIES,
        MG_TRIGGER_SCHEDULER_RUNS,
        MG_TRIGGER_DEAD_LETTERS,
    ];
}

function mg_trigger_orchestration_required_tables(): array
{
    return array_merge(mg_store_trigger_engine_required_tables(), mg_trigger_orchestration_tables(), [
        'campaign_events',
        'campaign_contacts',
        'mg_merchant_customer_behavior_profiles',
    ]);
}

function mg_trigger_orchestration_missing_tables(PDO $pdo): array
{
    return mg_store_canvas_missing_tables($pdo, mg_trigger_orchestration_required_tables());
}

function mg_trigger_orchestration_schema_ready(PDO $pdo): bool
{
    return mg_trigger_orchestration_missing_tables($pdo) === [];
}

function mg_trigger_orchestration_require_schema(PDO $pdo): void
{
    mg_store_canvas_require_tables($pdo, mg_trigger_orchestration_required_tables(), 'Trigger event ingestion and orchestration');
}

function mg_trigger_orchestration_sources(): array
{
    return [
        'store_session_events' => 'Store sessions and product activity',
        'campaign_events' => 'Campaign participation and Wallet lifecycle',
        'wallet_reconciliation' => 'Wallet claim and redemption reconciliation',
        'behavior_profiles' => 'Campaign-interest and inactivity-risk intelligence',
    ];
}

function mg_trigger_orchestration_event_types(): array
{
    return mg_store_trigger_engine_event_types() + [
        'campaign_opened' => 'Campaign opened',
        'campaign_participated' => 'Campaign participated',
    ];
}

function mg_trigger_orchestration_json(mixed $value): array
{
    return mg_store_trigger_engine_json($value);
}

function mg_trigger_orchestration_uuid(): string
{
    return mg_store_trigger_engine_uuid();
}

function mg_trigger_orchestration_settings(PDO $pdo, int $merchantUserId): array
{
    $row = mg_store_trigger_engine_settings($pdo, $merchantUserId, true);
    return [
        'id' => (string)$row['public_id'],
        'execution_mode' => (string)$row['execution_mode'],
        'emergency_pause' => !empty($row['emergency_pause']),
        'ingestion_enabled' => !array_key_exists('ingestion_enabled', $row) || !empty($row['ingestion_enabled']),
        'scheduler_enabled' => !empty($row['scheduler_enabled']),
        'max_notifications_per_run' => max(1, min(100, (int)$row['max_notifications_per_run'])),
        'default_cooldown_seconds' => max(300, min(2592000, (int)$row['default_cooldown_seconds'])),
        'timezone' => (string)($row['orchestration_timezone'] ?? 'UTC'),
        'quiet_hours_start' => $row['quiet_hours_start'] ?? null,
        'quiet_hours_end' => $row['quiet_hours_end'] ?? null,
        'last_ingestion_at' => $row['last_ingestion_at'] ?? null,
        'last_ingestion_status' => (string)($row['last_ingestion_status'] ?? 'never'),
        'last_ingestion_summary' => mg_trigger_orchestration_json($row['last_ingestion_summary_json'] ?? null),
        'last_scheduler_heartbeat_at' => $row['last_scheduler_heartbeat_at'] ?? null,
        'last_run_at' => $row['last_run_at'] ?? null,
        'last_run_status' => (string)($row['last_run_status'] ?? 'never'),
    ];
}

function mg_trigger_orchestration_update_settings(PDO $pdo, int $merchantUserId, array $input): array
{
    mg_store_trigger_engine_settings($pdo, $merchantUserId, true);
    $emergencyPause = !empty($input['emergency_pause']) ? 1 : 0;
    $ingestionEnabled = array_key_exists('ingestion_enabled', $input) ? (!empty($input['ingestion_enabled']) ? 1 : 0) : 1;
    $schedulerEnabled = !empty($input['scheduler_enabled']) ? 1 : 0;
    $timezone = trim((string)($input['timezone'] ?? 'UTC')) ?: 'UTC';
    try {
        new DateTimeZone($timezone);
    } catch (Throwable) {
        throw new InvalidArgumentException('Invalid orchestration timezone.');
    }
    $quietStart = trim((string)($input['quiet_hours_start'] ?? ''));
    $quietEnd = trim((string)($input['quiet_hours_end'] ?? ''));
    foreach ([$quietStart, $quietEnd] as $value) {
        if ($value !== '' && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) {
            throw new InvalidArgumentException('Quiet hours must use HH:MM format.');
        }
    }
    if (($quietStart === '') xor ($quietEnd === '')) {
        throw new InvalidArgumentException('Quiet-hours start and end must both be provided.');
    }
    $stmt = $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET emergency_pause=?,ingestion_enabled=?,scheduler_enabled=?,orchestration_timezone=?,quiet_hours_start=?,quiet_hours_end=?,updated_at=NOW() WHERE merchant_user_id=?');
    $stmt->execute([$emergencyPause,$ingestionEnabled,$schedulerEnabled,$timezone,$quietStart !== '' ? $quietStart : null,$quietEnd !== '' ? $quietEnd : null,$merchantUserId]);
    return mg_trigger_orchestration_settings($pdo, $merchantUserId);
}

function mg_trigger_orchestration_policy_public(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'event_type' => (string)$row['event_type'],
        'event_label' => mg_trigger_orchestration_event_types()[(string)$row['event_type']] ?? ucwords(str_replace('_', ' ', (string)$row['event_type'])),
        'name' => (string)$row['name'],
        'status' => (string)$row['status'],
        'delay_seconds' => (int)$row['delay_seconds'],
        'retry_delay_seconds' => (int)$row['retry_delay_seconds'],
        'max_attempts' => (int)$row['max_attempts'],
        'quiet_hours_start' => $row['quiet_hours_start'] ?? null,
        'quiet_hours_end' => $row['quiet_hours_end'] ?? null,
        'timezone' => (string)$row['timezone'],
        'require_active_session' => !empty($row['require_active_session']),
        'greeting_mode' => (string)$row['greeting_mode'],
        'follow_up_mode' => (string)$row['follow_up_mode'],
        'release_after_seconds' => (int)$row['release_after_seconds'],
        'suppress_after_claim_seconds' => (int)$row['suppress_after_claim_seconds'],
        'suppress_after_redeem_seconds' => (int)$row['suppress_after_redeem_seconds'],
        'message_variants' => mg_trigger_orchestration_json($row['message_variants_json'] ?? null),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_trigger_orchestration_policies(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_orchestration_policies WHERE merchant_user_id=? AND status<>'archived' ORDER BY FIELD(status,'enabled','paused'),event_type,id");
    $stmt->execute([$merchantUserId]);
    return array_map('mg_trigger_orchestration_policy_public', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_trigger_orchestration_policy_row(PDO $pdo, int $merchantUserId, string $eventType): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_orchestration_policies WHERE merchant_user_id=? AND event_type=? AND status<>'archived' LIMIT 1");
    $stmt->execute([$merchantUserId,$eventType]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_trigger_orchestration_save_policy(PDO $pdo, int $merchantUserId, array $input): array
{
    $eventType = strtolower(trim((string)($input['event_type'] ?? '')));
    if (!array_key_exists($eventType, mg_trigger_orchestration_event_types())) {
        throw new InvalidArgumentException('Invalid orchestration event type.');
    }
    $name = trim((string)($input['name'] ?? '')) ?: (mg_trigger_orchestration_event_types()[$eventType] . ' policy');
    $name = mb_substr($name, 0, 180);
    $status = strtolower(trim((string)($input['status'] ?? 'paused')));
    if (!in_array($status, ['enabled','paused'], true)) $status = 'paused';
    $delay = max(0, min(2592000, (int)($input['delay_seconds'] ?? 0)));
    $retryDelay = max(300, min(604800, (int)($input['retry_delay_seconds'] ?? 900)));
    $maxAttempts = max(1, min(100, (int)($input['max_attempts'] ?? 12)));
    $timezone = trim((string)($input['timezone'] ?? 'UTC')) ?: 'UTC';
    try { new DateTimeZone($timezone); } catch (Throwable) { throw new InvalidArgumentException('Invalid policy timezone.'); }
    $quietStart = trim((string)($input['quiet_hours_start'] ?? ''));
    $quietEnd = trim((string)($input['quiet_hours_end'] ?? ''));
    if (($quietStart === '') xor ($quietEnd === '')) throw new InvalidArgumentException('Policy quiet-hours start and end must both be provided.');
    foreach ([$quietStart,$quietEnd] as $value) {
        if ($value !== '' && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) throw new InvalidArgumentException('Policy quiet hours must use HH:MM format.');
    }
    $requireActive = array_key_exists('require_active_session', $input) ? (!empty($input['require_active_session']) ? 1 : 0) : 1;
    $greeting = strtolower(trim((string)($input['greeting_mode'] ?? 'contextual')));
    if (!in_array($greeting, ['none','first_visit','returning','contextual'], true)) $greeting = 'contextual';
    $followUp = strtolower(trim((string)($input['follow_up_mode'] ?? 'campaign_only')));
    if (!in_array($followUp, ['none','campaign_only','claim_aware','redemption_aware'], true)) $followUp = 'campaign_only';
    $release = max(300, min(2592000, (int)($input['release_after_seconds'] ?? 86400)));
    $suppressClaim = max(0, min(2592000, (int)($input['suppress_after_claim_seconds'] ?? 86400)));
    $suppressRedeem = max(0, min(7776000, (int)($input['suppress_after_redeem_seconds'] ?? 604800)));
    $variants = is_array($input['message_variants'] ?? null) ? $input['message_variants'] : [];
    $cleanVariants = [];
    foreach ($variants as $key => $value) {
        $key = preg_replace('/[^a-z0-9_\-]+/', '', strtolower((string)$key)) ?? '';
        $value = trim((string)$value);
        if ($key !== '' && $value !== '') $cleanVariants[$key] = mb_substr($value, 0, 1000);
    }
    $metadata = [
        'authority' => 'notification_only',
        'direct_message_allowed' => false,
        'wallet_write_allowed' => false,
        'browser_overlap_authority' => false,
        'protected_traits_excluded' => true,
    ];
    $existing = mg_trigger_orchestration_policy_row($pdo, $merchantUserId, $eventType);
    if ($existing) {
        $stmt = $pdo->prepare('UPDATE mg_store_trigger_orchestration_policies SET name=?,status=?,delay_seconds=?,retry_delay_seconds=?,max_attempts=?,quiet_hours_start=?,quiet_hours_end=?,timezone=?,require_active_session=?,greeting_mode=?,follow_up_mode=?,release_after_seconds=?,suppress_after_claim_seconds=?,suppress_after_redeem_seconds=?,message_variants_json=?,metadata_json=?,updated_at=NOW() WHERE id=? AND merchant_user_id=?');
        $stmt->execute([$name,$status,$delay,$retryDelay,$maxAttempts,$quietStart !== '' ? $quietStart : null,$quietEnd !== '' ? $quietEnd : null,$timezone,$requireActive,$greeting,$followUp,$release,$suppressClaim,$suppressRedeem,mg_store_trigger_engine_encode($cleanVariants),mg_store_trigger_engine_encode($metadata),(int)$existing['id'],$merchantUserId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO mg_store_trigger_orchestration_policies (public_id,merchant_user_id,event_type,name,status,delay_seconds,retry_delay_seconds,max_attempts,quiet_hours_start,quiet_hours_end,timezone,require_active_session,greeting_mode,follow_up_mode,release_after_seconds,suppress_after_claim_seconds,suppress_after_redeem_seconds,message_variants_json,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([mg_trigger_orchestration_uuid(),$merchantUserId,$eventType,$name,$status,$delay,$retryDelay,$maxAttempts,$quietStart !== '' ? $quietStart : null,$quietEnd !== '' ? $quietEnd : null,$timezone,$requireActive,$greeting,$followUp,$release,$suppressClaim,$suppressRedeem,mg_store_trigger_engine_encode($cleanVariants),mg_store_trigger_engine_encode($metadata)]);
    }
    $row = mg_trigger_orchestration_policy_row($pdo, $merchantUserId, $eventType);
    if (!$row) throw new RuntimeException('Unable to load orchestration policy.');
    return mg_trigger_orchestration_policy_public($row);
}

function mg_trigger_ingestion_checkpoint(PDO $pdo, int $merchantUserId, string $sourceKey): array
{
    $stmt = $pdo->prepare('SELECT * FROM mg_store_trigger_ingestion_checkpoints WHERE merchant_user_id=? AND source_key=? LIMIT 1');
    $stmt->execute([$merchantUserId,$sourceKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $publicId = mg_trigger_orchestration_uuid();
        $insert = $pdo->prepare("INSERT INTO mg_store_trigger_ingestion_checkpoints (public_id,merchant_user_id,source_key,health_status,cursor_json,created_at,updated_at) VALUES (?,?,?,'never',?,NOW(),NOW())");
        $insert->execute([$publicId,$merchantUserId,$sourceKey,mg_store_trigger_engine_encode(['last_id'=>0,'last_timestamp'=>'1970-01-01 00:00:00'])]);
        $stmt->execute([$merchantUserId,$sourceKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) throw new RuntimeException('Unable to initialize ingestion checkpoint.');
    return $row;
}

function mg_trigger_ingestion_checkpoint_save(PDO $pdo, int $merchantUserId, string $sourceKey, array $cursor, string $health, int $ingested, int $skipped, string $runId, ?Throwable $error = null): void
{
    mg_trigger_ingestion_checkpoint($pdo, $merchantUserId, $sourceKey);
    $stmt = $pdo->prepare('UPDATE mg_store_trigger_ingestion_checkpoints SET cursor_json=?,health_status=?,last_scan_at=NOW(),last_success_at=IF(? IN (\'healthy\',\'warning\'),NOW(),last_success_at),last_error_code=?,last_error_message=?,ingested_count=ingested_count+?,skipped_count=skipped_count+?,last_run_public_id=?,updated_at=NOW() WHERE merchant_user_id=? AND source_key=?');
    $stmt->execute([
        mg_store_trigger_engine_encode($cursor),$health,$health,
        $error ? 'source_scan_failed' : null,$error ? mb_substr($error->getMessage(),0,500) : null,
        $ingested,$skipped,$runId,$merchantUserId,$sourceKey,
    ]);
}

function mg_trigger_ingestion_session(PDO $pdo, int $merchantUserId, int $customerUserId, ?int $sessionId = null): ?array
{
    $params = [$merchantUserId,$customerUserId];
    $where = 's.merchant_user_id=? AND s.customer_user_id=?';
    if ($sessionId !== null && $sessionId > 0) {
        $where .= ' AND s.id=?';
        $params[] = $sessionId;
    }
    $stmt = $pdo->prepare("SELECT s.*,
        COALESCE(bp.relationship_stage,'new') relationship_stage,
        COALESCE(bp.dominant_pattern,'early_signal') dominant_pattern,
        COALESCE(bp.campaign_engagement_probability,0) campaign_engagement_probability,
        COALESCE(bp.inactivity_risk_probability,0) inactivity_risk_probability,
        COALESCE(bp.confidence_score,0) confidence_score,
        COALESCE(bp.sample_size,0) sample_size,
        COALESCE(bp.memory_summary,'') memory_summary,
        (SELECT COUNT(*) FROM mg_store_sessions s2 WHERE s2.merchant_user_id=s.merchant_user_id AND s2.customer_user_id=s.customer_user_id) visit_count
        FROM mg_store_sessions s
        LEFT JOIN mg_merchant_customer_behavior_profiles bp ON bp.merchant_user_id=s.merchant_user_id AND bp.customer_user_id=s.customer_user_id
        WHERE {$where}
        ORDER BY (s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL) DESC,s.id DESC LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_trigger_ingestion_normalize(PDO $pdo, int $merchantUserId, int $customerUserId, ?array $session, array $event): bool
{
    if ($customerUserId < 1) return false;
    $eventType = strtolower(trim((string)($event['event_type'] ?? '')));
    if (!array_key_exists($eventType, mg_trigger_orchestration_event_types())) return false;
    $eventKey = mb_substr(trim((string)($event['event_key'] ?? '')),0,190);
    if ($eventKey === '') throw new InvalidArgumentException('Normalized event key is required.');
    $policy = mg_trigger_orchestration_policy_row($pdo, $merchantUserId, $eventType);
    $delay = $policy && (string)$policy['status'] === 'enabled' ? max(0,(int)$policy['delay_seconds']) : 0;
    $maxAttempts = $policy ? max(1,(int)$policy['max_attempts']) : 12;
    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
    $payload += [
        'probability_score' => (float)($event['probability'] ?? 100),
        'confidence_score' => (float)($event['confidence'] ?? 100),
        'server_authoritative' => true,
        'browser_overlap_used' => false,
        'protected_traits_used' => false,
        'reward_issued' => false,
    ];
    $publicId = mg_trigger_orchestration_uuid();
    $stmt = $pdo->prepare("INSERT IGNORE INTO mg_store_trigger_events
        (public_id,merchant_user_id,customer_user_id,store_session_id,store_session_public_id,event_key,event_type,orchestration_policy_public_id,source_type,source_public_id,source_record_id,event_at,payload_json,status,attempt_count,max_attempts,available_at,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',0,?,DATE_ADD(NOW(),INTERVAL ? SECOND),NOW(),NOW())");
    $stmt->execute([
        $publicId,$merchantUserId,$customerUserId,
        $session ? (int)$session['id'] : null,$session ? (string)$session['public_id'] : null,
        $eventKey,$eventType,$policy ? (string)$policy['public_id'] : null,
        mb_substr((string)($event['source_type'] ?? 'canonical_event'),0,80),
        mb_substr((string)($event['source_public_id'] ?? ''),0,190) ?: null,
        isset($event['source_record_id']) ? (int)$event['source_record_id'] : null,
        (string)($event['event_at'] ?? date('Y-m-d H:i:s')),
        mg_store_trigger_engine_encode($payload),$maxAttempts,$delay,
    ]);
    return $stmt->rowCount() > 0;
}

function mg_trigger_ingestion_map_store_event(array $row): ?array
{
    $type = strtolower((string)$row['event_type']);
    $data = mg_trigger_orchestration_json($row['event_data_json'] ?? null);
    $mapped = match ($type) {
        'entered_store','store_entered' => 'store_entry',
        'store_session_resumed' => 'return_visit',
        'viewed_product','product_viewed' => 'product_interest',
        'claimed_reward','reward_claimed' => 'reward_claimed',
        'redeemed_reward','reward_redeemed' => 'reward_redeemed',
        default => null,
    };
    if ($mapped === null) return null;
    $sourcePublicId = (string)$row['public_id'];
    $eventKey = 'store-event:' . $sourcePublicId;
    if ($mapped === 'reward_claimed' && !empty($data['wallet_item_id'])) $eventKey = 'reward_claimed:' . (string)$data['wallet_item_id'];
    if ($mapped === 'reward_redeemed' && !empty($data['wallet_item_id'])) $eventKey = 'reward_redeemed:' . (string)$data['wallet_item_id'];
    return [
        'event_key'=>$eventKey,'event_type'=>$mapped,'source_type'=>'store_session_event','source_public_id'=>$sourcePublicId,
        'source_record_id'=>(int)$row['id'],'event_at'=>(string)$row['created_at'],
        'probability'=>$mapped === 'product_interest' ? 75 : 100,'confidence'=>$mapped === 'product_interest' ? 85 : 100,
        'payload'=>['source_event_type'=>$type,'source_event_label'=>$row['event_label'] ?? null,'source_event_data'=>$data],
    ];
}

function mg_trigger_ingestion_map_campaign_event(array $row): ?array
{
    $type = strtolower((string)$row['event_type']);
    $context = mg_trigger_orchestration_json($row['event_context_json'] ?? null);
    $mapped = null;
    if ($type === 'campaign.opened') $mapped = 'campaign_opened';
    elseif ($type === 'wallet_item.claimed') $mapped = 'reward_claimed';
    elseif ($type === 'wallet_item.redeemed') $mapped = 'reward_redeemed';
    elseif (preg_match('/(engage|signup|entry|joined|participat|reward_issued|qr_pickup|contest)/', $type) === 1) $mapped = 'campaign_participated';
    if ($mapped === null) return null;
    $walletPublicId = trim((string)($row['wallet_public_id'] ?? $context['wallet_item_id'] ?? ''));
    $eventKey = 'campaign-event:' . (string)$row['public_id'];
    if ($mapped === 'reward_claimed' && $walletPublicId !== '') $eventKey = 'reward_claimed:' . $walletPublicId;
    if ($mapped === 'reward_redeemed' && $walletPublicId !== '') $eventKey = 'reward_redeemed:' . $walletPublicId;
    return [
        'event_key'=>$eventKey,'event_type'=>$mapped,'source_type'=>'campaign_event','source_public_id'=>(string)$row['public_id'],
        'source_record_id'=>(int)$row['id'],'event_at'=>(string)$row['created_at'],'probability'=>100,'confidence'=>100,
        'payload'=>[
            'source_event_type'=>$type,'campaign_public_id'=>$row['campaign_public_id'] ?? null,
            'wallet_item_public_id'=>$walletPublicId !== '' ? $walletPublicId : null,'source_context'=>$context,
        ],
    ];
}

function mg_trigger_ingestion_scan_store_events(PDO $pdo, int $merchantUserId, int $limit, string $runId): array
{
    $checkpoint = mg_trigger_ingestion_checkpoint($pdo,$merchantUserId,'store_session_events');
    $cursor = mg_trigger_orchestration_json($checkpoint['cursor_json'] ?? null);
    $lastId = max(0,(int)($cursor['last_id'] ?? 0));
    $stmt = $pdo->prepare("SELECT e.*,s.public_id session_public_id FROM mg_store_session_events e JOIN mg_store_sessions s ON s.id=e.store_session_id WHERE e.merchant_user_id=? AND e.id>? ORDER BY e.id ASC LIMIT {$limit}");
    $stmt->execute([$merchantUserId,$lastId]);
    $ingested = 0; $skipped = 0; $scanned = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $scanned++; $lastId = max($lastId,(int)$row['id']);
        $mapped = mg_trigger_ingestion_map_store_event($row);
        if (!$mapped) { $skipped++; continue; }
        $session = mg_trigger_ingestion_session($pdo,$merchantUserId,(int)$row['customer_user_id'],(int)$row['store_session_id']);
        if (mg_trigger_ingestion_normalize($pdo,$merchantUserId,(int)$row['customer_user_id'],$session,$mapped)) $ingested++; else $skipped++;
    }
    $cursor = ['last_id'=>$lastId,'last_timestamp'=>date('Y-m-d H:i:s')];
    mg_trigger_ingestion_checkpoint_save($pdo,$merchantUserId,'store_session_events',$cursor,'healthy',$ingested,$skipped,$runId);
    return ['source'=>'store_session_events','scanned'=>$scanned,'ingested'=>$ingested,'skipped'=>$skipped];
}

function mg_trigger_ingestion_scan_campaign_events(PDO $pdo, int $merchantUserId, int $limit, string $runId): array
{
    $checkpoint = mg_trigger_ingestion_checkpoint($pdo,$merchantUserId,'campaign_events');
    $cursor = mg_trigger_orchestration_json($checkpoint['cursor_json'] ?? null);
    $lastId = max(0,(int)($cursor['last_id'] ?? 0));
    $stmt = $pdo->prepare("SELECT ce.*,c.public_id campaign_public_id,wi.public_id wallet_public_id,wi.user_id wallet_user_id,cc.user_id contact_user_id
        FROM campaign_events ce
        LEFT JOIN campaigns c ON c.id=ce.campaign_id
        LEFT JOIN wallet_items wi ON wi.id=ce.wallet_item_id
        LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id
        WHERE ce.merchant_user_id=? AND ce.id>? ORDER BY ce.id ASC LIMIT {$limit}");
    $stmt->execute([$merchantUserId,$lastId]);
    $ingested = 0; $skipped = 0; $scanned = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $scanned++; $lastId = max($lastId,(int)$row['id']);
        $mapped = mg_trigger_ingestion_map_campaign_event($row);
        if (!$mapped) { $skipped++; continue; }
        $context = mg_trigger_orchestration_json($row['event_context_json'] ?? null);
        $customerUserId = (int)($row['wallet_user_id'] ?? $row['contact_user_id'] ?? $context['user_id'] ?? 0);
        if ($customerUserId < 1) { $skipped++; continue; }
        $session = mg_trigger_ingestion_session($pdo,$merchantUserId,$customerUserId);
        if (mg_trigger_ingestion_normalize($pdo,$merchantUserId,$customerUserId,$session,$mapped)) $ingested++; else $skipped++;
    }
    $cursor = ['last_id'=>$lastId,'last_timestamp'=>date('Y-m-d H:i:s')];
    mg_trigger_ingestion_checkpoint_save($pdo,$merchantUserId,'campaign_events',$cursor,'healthy',$ingested,$skipped,$runId);
    return ['source'=>'campaign_events','scanned'=>$scanned,'ingested'=>$ingested,'skipped'=>$skipped];
}

function mg_trigger_ingestion_scan_wallet(PDO $pdo, int $merchantUserId, int $limit, string $runId): array
{
    $checkpoint = mg_trigger_ingestion_checkpoint($pdo,$merchantUserId,'wallet_reconciliation');
    $cursor = mg_trigger_orchestration_json($checkpoint['cursor_json'] ?? null);
    $lastTimestamp = (string)($cursor['last_timestamp'] ?? '1970-01-01 00:00:00');
    $lastId = max(0,(int)($cursor['last_id'] ?? 0));
    $stmt = $pdo->prepare("SELECT * FROM wallet_items WHERE merchant_user_id=? AND user_id IS NOT NULL AND status IN ('claimed','redeemed') AND (updated_at>? OR (updated_at=? AND id>?)) ORDER BY updated_at ASC,id ASC LIMIT {$limit}");
    $stmt->execute([$merchantUserId,$lastTimestamp,$lastTimestamp,$lastId]);
    $ingested = 0; $skipped = 0; $scanned = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $scanned++; $lastTimestamp = (string)$row['updated_at']; $lastId = (int)$row['id'];
        $customerUserId = (int)$row['user_id'];
        $session = mg_trigger_ingestion_session($pdo,$merchantUserId,$customerUserId);
        $events = [];
        if (!empty($row['claimed_at'])) $events[] = ['event_key'=>'reward_claimed:'.(string)$row['public_id'],'event_type'=>'reward_claimed','event_at'=>(string)$row['claimed_at']];
        if (!empty($row['redeemed_at'])) $events[] = ['event_key'=>'reward_redeemed:'.(string)$row['public_id'],'event_type'=>'reward_redeemed','event_at'=>(string)$row['redeemed_at']];
        foreach ($events as $event) {
            $event += ['source_type'=>'wallet_reconciliation','source_public_id'=>(string)$row['public_id'],'source_record_id'=>(int)$row['id'],'probability'=>100,'confidence'=>100,'payload'=>['wallet_status'=>(string)$row['status'],'campaign_id'=>$row['campaign_id'] ?? null,'reward_template_id'=>$row['reward_template_id'] ?? null]];
            if (mg_trigger_ingestion_normalize($pdo,$merchantUserId,$customerUserId,$session,$event)) $ingested++; else $skipped++;
        }
    }
    $cursor = ['last_id'=>$lastId,'last_timestamp'=>$lastTimestamp];
    mg_trigger_ingestion_checkpoint_save($pdo,$merchantUserId,'wallet_reconciliation',$cursor,'healthy',$ingested,$skipped,$runId);
    return ['source'=>'wallet_reconciliation','scanned'=>$scanned,'ingested'=>$ingested,'skipped'=>$skipped];
}

function mg_trigger_ingestion_scan_behavior(PDO $pdo, int $merchantUserId, int $limit, string $runId): array
{
    $checkpoint = mg_trigger_ingestion_checkpoint($pdo,$merchantUserId,'behavior_profiles');
    $cursor = mg_trigger_orchestration_json($checkpoint['cursor_json'] ?? null);
    $lastTimestamp = (string)($cursor['last_timestamp'] ?? '1970-01-01 00:00:00');
    $lastId = max(0,(int)($cursor['last_id'] ?? 0));
    $stmt = $pdo->prepare("SELECT * FROM mg_merchant_customer_behavior_profiles WHERE merchant_user_id=? AND (updated_at>? OR (updated_at=? AND id>?)) ORDER BY updated_at ASC,id ASC LIMIT {$limit}");
    $stmt->execute([$merchantUserId,$lastTimestamp,$lastTimestamp,$lastId]);
    $ingested = 0; $skipped = 0; $scanned = 0; $day = date('Ymd');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $scanned++; $lastTimestamp = (string)$row['updated_at']; $lastId = (int)$row['id'];
        $customerUserId = (int)$row['customer_user_id'];
        $session = mg_trigger_ingestion_session($pdo,$merchantUserId,$customerUserId);
        $basePayload = ['behavior_profile_id'=>(string)$row['public_id'],'relationship_stage'=>(string)$row['relationship_stage'],'dominant_pattern'=>(string)$row['dominant_pattern'],'sample_size'=>(int)$row['sample_size'],'memory_summary'=>(string)$row['memory_summary']];
        $events = [
            ['event_key'=>'campaign_interest:'.(string)$row['public_id'].':'.$day,'event_type'=>'campaign_interest','probability'=>(float)$row['campaign_engagement_probability']],
            ['event_key'=>'inactivity_risk:'.(string)$row['public_id'].':'.$day,'event_type'=>'inactivity_risk','probability'=>(float)$row['inactivity_risk_probability']],
        ];
        foreach ($events as $event) {
            if ((float)$event['probability'] <= 0 || (int)$row['sample_size'] < 1) { $skipped++; continue; }
            $event += ['source_type'=>'behavior_profile','source_public_id'=>(string)$row['public_id'],'source_record_id'=>(int)$row['id'],'event_at'=>(string)$row['updated_at'],'confidence'=>(float)$row['confidence_score'],'payload'=>$basePayload];
            if (mg_trigger_ingestion_normalize($pdo,$merchantUserId,$customerUserId,$session,$event)) $ingested++; else $skipped++;
        }
    }
    $cursor = ['last_id'=>$lastId,'last_timestamp'=>$lastTimestamp];
    mg_trigger_ingestion_checkpoint_save($pdo,$merchantUserId,'behavior_profiles',$cursor,'healthy',$ingested,$skipped,$runId);
    return ['source'=>'behavior_profiles','scanned'=>$scanned,'ingested'=>$ingested,'skipped'=>$skipped];
}

function mg_trigger_scheduler_run_start(PDO $pdo, int $merchantUserId, string $runType, string $mode): array
{
    $publicId = mg_trigger_orchestration_uuid();
    $stmt = $pdo->prepare("INSERT INTO mg_store_trigger_scheduler_runs (public_id,merchant_user_id,run_type,execution_mode,status,started_at,created_at) VALUES (?,?,?,?,'running',NOW(),NOW())");
    $stmt->execute([$publicId,$merchantUserId,$runType,$mode]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId];
}

function mg_trigger_scheduler_run_finish(PDO $pdo, array $run, array $summary, string $status, ?Throwable $error = null): void
{
    $stmt = $pdo->prepare('UPDATE mg_store_trigger_scheduler_runs SET status=?,completed_at=NOW(),sources_scanned=?,records_scanned=?,events_queued=?,events_evaluated=?,notifications_delivered=?,events_blocked=?,events_retried=?,events_dead_lettered=?,error_count=?,summary_json=?,error_message=? WHERE id=?');
    $stmt->execute([
        $status,(int)($summary['sources_scanned'] ?? 0),(int)($summary['records_scanned'] ?? 0),(int)($summary['events_queued'] ?? 0),
        (int)($summary['events_evaluated'] ?? 0),(int)($summary['notifications_delivered'] ?? 0),(int)($summary['events_blocked'] ?? 0),
        (int)($summary['events_retried'] ?? 0),(int)($summary['events_dead_lettered'] ?? 0),(int)($summary['errors'] ?? 0),
        mg_store_trigger_engine_encode($summary),$error ? mb_substr($error->getMessage(),0,1000) : null,(int)$run['id'],
    ]);
}

function mg_trigger_ingestion_run(PDO $pdo, array $merchantUser, int $limitPerSource = 250): array
{
    mg_trigger_orchestration_require_schema($pdo);
    $merchantUserId = (int)($merchantUser['id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant account is required.');
    $settings = mg_trigger_orchestration_settings($pdo,$merchantUserId);
    $run = mg_trigger_scheduler_run_start($pdo,$merchantUserId,'ingestion',(string)$settings['execution_mode']);
    $summary = ['run_id'=>$run['public_id'],'sources_scanned'=>0,'records_scanned'=>0,'events_queued'=>0,'events_evaluated'=>0,'notifications_delivered'=>0,'events_blocked'=>0,'events_retried'=>0,'events_dead_lettered'=>0,'errors'=>0,'sources'=>[],'reward_issued'=>false,'browser_overlap_used'=>false];
    if (!$settings['ingestion_enabled']) {
        $summary['status'] = 'paused';
        mg_trigger_scheduler_run_finish($pdo,$run,$summary,'paused');
        return $summary;
    }
    $pdo->prepare("UPDATE mg_store_trigger_engine_settings SET last_ingestion_at=NOW(),last_ingestion_status='running',updated_at=NOW() WHERE merchant_user_id=?")->execute([$merchantUserId]);
    $adapters = ['store_session_events'=>'mg_trigger_ingestion_scan_store_events','campaign_events'=>'mg_trigger_ingestion_scan_campaign_events','wallet_reconciliation'=>'mg_trigger_ingestion_scan_wallet','behavior_profiles'=>'mg_trigger_ingestion_scan_behavior'];
    foreach ($adapters as $sourceKey => $adapter) {
        try {
            $result = $adapter($pdo,$merchantUserId,max(1,min(1000,$limitPerSource)),$run['public_id']);
            $summary['sources_scanned']++;
            $summary['records_scanned'] += (int)$result['scanned'];
            $summary['events_queued'] += (int)$result['ingested'];
            $summary['sources'][] = $result;
        } catch (Throwable $error) {
            $summary['errors']++;
            $checkpoint = mg_trigger_ingestion_checkpoint($pdo,$merchantUserId,$sourceKey);
            mg_trigger_ingestion_checkpoint_save($pdo,$merchantUserId,$sourceKey,mg_trigger_orchestration_json($checkpoint['cursor_json'] ?? null),'failed',0,0,$run['public_id'],$error);
            $summary['sources'][] = ['source'=>$sourceKey,'scanned'=>0,'ingested'=>0,'skipped'=>0,'error'=>$error->getMessage()];
        }
    }
    $status = $summary['errors'] > 0 ? 'partial' : 'completed';
    $summary['status'] = $status;
    $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET last_ingestion_status=?,last_ingestion_summary_json=?,last_scheduler_heartbeat_at=NOW(),updated_at=NOW() WHERE merchant_user_id=?')->execute([$status,mg_store_trigger_engine_encode($summary),$merchantUserId]);
    mg_trigger_scheduler_run_finish($pdo,$run,$summary,$status);
    mg_event('store_canvas.trigger_ingestion_run',['merchant_user_id'=>$merchantUserId,'queued'=>$summary['events_queued'],'errors'=>$summary['errors'],'reward_issued'=>false,'browser_overlap_used'=>false],$merchantUserId);
    return $summary;
}

function mg_trigger_orchestration_quiet_retry_at(array $settings, ?array $policy): ?string
{
    $start = trim((string)($policy['quiet_hours_start'] ?? $settings['quiet_hours_start'] ?? ''));
    $end = trim((string)($policy['quiet_hours_end'] ?? $settings['quiet_hours_end'] ?? ''));
    if ($start === '' || $end === '') return null;
    $timezone = trim((string)($policy['timezone'] ?? $settings['timezone'] ?? 'UTC')) ?: 'UTC';
    try { $tz = new DateTimeZone($timezone); } catch (Throwable) { $tz = new DateTimeZone('UTC'); }
    $now = new DateTimeImmutable('now',$tz);
    [$sh,$sm] = array_map('intval',array_slice(explode(':',$start),0,2));
    [$eh,$em] = array_map('intval',array_slice(explode(':',$end),0,2));
    $startAt = $now->setTime($sh,$sm);
    $endAt = $now->setTime($eh,$em);
    $quiet = false;
    if ($startAt < $endAt) {
        $quiet = $now >= $startAt && $now < $endAt;
    } else {
        $quiet = $now >= $startAt || $now < $endAt;
        if ($now >= $startAt) $endAt = $endAt->modify('+1 day');
    }
    if (!$quiet) return null;
    return $endAt->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
}

function mg_trigger_orchestration_recent_wallet_suppression(PDO $pdo, int $merchantUserId, int $customerUserId, string $eventType, ?array $policy): ?array
{
    if (!$policy || in_array($eventType,['reward_claimed','reward_redeemed'],true)) return null;
    $claimWindow = max(0,(int)$policy['suppress_after_claim_seconds']);
    $redeemWindow = max(0,(int)$policy['suppress_after_redeem_seconds']);
    $stmt = $pdo->prepare("SELECT status,claimed_at,redeemed_at FROM wallet_items WHERE merchant_user_id=? AND user_id=? AND status IN ('claimed','redeemed') ORDER BY COALESCE(redeemed_at,claimed_at,updated_at) DESC,id DESC LIMIT 1");
    $stmt->execute([$merchantUserId,$customerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if (!empty($row['redeemed_at']) && $redeemWindow > 0) {
        $until = strtotime((string)$row['redeemed_at']) + $redeemWindow;
        if ($until > time()) return ['code'=>'post_redemption_suppression','message'=>'Interaction is suppressed after recent redemption.','available_at'=>date('Y-m-d H:i:s',$until)];
    }
    if (!empty($row['claimed_at']) && $claimWindow > 0) {
        $until = strtotime((string)$row['claimed_at']) + $claimWindow;
        if ($until > time()) return ['code'=>'post_claim_suppression','message'=>'Interaction is suppressed after recent claim.','available_at'=>date('Y-m-d H:i:s',$until)];
    }
    return null;
}

function mg_trigger_orchestration_note(array $rule, array $event, ?array $policy, array $session): string
{
    $note = trim((string)($rule['notification_note'] ?? ''));
    $variants = $policy ? mg_trigger_orchestration_json($policy['message_variants_json'] ?? null) : [];
    $visitCount = (int)($session['visit_count'] ?? 1);
    $keys = [(string)$event['event_type'],$visitCount <= 1 ? 'first_visit' : 'returning','default'];
    foreach ($keys as $key) {
        $candidate = trim((string)($variants[$key] ?? ''));
        if ($candidate !== '') return mb_substr($candidate,0,1000);
    }
    if ($note !== '') return mb_substr($note,0,1000);
    return 'A campaign matched your recent store activity. Open it to review and complete the requirements for the approved reward.';
}

function mg_trigger_orchestration_evaluation_exists(PDO $pdo, int $eventId, int $ruleId, string $mode): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM mg_store_trigger_evaluations WHERE event_id=? AND rule_id=? AND execution_mode=? LIMIT 1');
    $stmt->execute([$eventId,$ruleId,$mode]);
    return (bool)$stmt->fetchColumn();
}

function mg_trigger_orchestration_record(PDO $pdo, array $event, array $rule, string $mode, string $decision, string $reasonCode, string $reasonText, float $probability, float $confidence, array $evidence = [], string $recommendationId = '', string $notificationId = ''): array
{
    return mg_store_trigger_engine_record_evaluation($pdo,[
        'merchant_user_id'=>(int)$event['merchant_user_id'],'customer_user_id'=>(int)$event['customer_user_id'],'event_id'=>(int)$event['id'],'rule_id'=>(int)$rule['id'],
        'campaign_public_id'=>(string)$rule['campaign_public_id'],'trigger_zone_public_id'=>(string)($rule['trigger_zone_public_id'] ?? ''),'execution_mode'=>$mode,
        'decision'=>$decision,'reason_code'=>$reasonCode,'reason_text'=>$reasonText,'probability_score'=>$probability,'confidence_score'=>$confidence,
        'recommendation_id'=>$recommendationId,'notification_id'=>$notificationId,
        'evidence'=>$evidence + ['server_authoritative'=>true,'browser_overlap_used'=>false,'reward_issued'=>false,'orchestration_v1'=>true],
    ]);
}

function mg_trigger_orchestration_evaluate(PDO $pdo, array $merchantUser, array $event, array $rule, array $session, ?array $policy, string $mode, bool $deliveryLimitReached): array
{
    $payload = mg_trigger_orchestration_json($event['payload_json'] ?? null);
    $probability = mg_store_trigger_engine_clamp((float)($payload['probability_score'] ?? 100));
    $confidence = mg_store_trigger_engine_clamp((float)($payload['confidence_score'] ?? 100));
    $evidence = $payload + ['event_public_id'=>(string)$event['public_id'],'rule_public_id'=>(string)$rule['public_id'],'policy_public_id'=>$policy['public_id'] ?? null];
    if ($probability < (float)$rule['minimum_probability']) return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'skipped','probability_below_threshold','Event probability is below the configured threshold.',$probability,$confidence,$evidence);
    if ($confidence < (float)$rule['minimum_confidence']) return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'skipped','confidence_below_threshold','Evidence confidence is below the configured threshold.',$probability,$confidence,$evidence);
    try { mg_store_campaign_recommendation_campaign($pdo,(int)$merchantUser['id'],(string)$rule['campaign_public_id']); }
    catch (Throwable $error) { return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'blocked','campaign_unavailable',$error->getMessage(),$probability,$confidence,$evidence); }
    try { mg_store_manual_ops_assert_message_allowed($pdo,(int)$merchantUser['id'],(int)$event['customer_user_id'],true); }
    catch (Throwable $error) { return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'blocked','communication_blocked',$error->getMessage(),$probability,$confidence,$evidence); }
    $limit = mg_store_trigger_engine_delivery_limits($pdo,(int)$merchantUser['id'],(int)$event['customer_user_id'],(int)$rule['id'],max(300,(int)$rule['cooldown_seconds']),max(1,(int)$rule['max_per_customer_day']));
    if ($limit) return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'blocked',(string)$limit['code'],(string)$limit['message'],$probability,$confidence,$evidence);
    if ($mode === 'dry_run') return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'matched','dry_run_match','Orchestration policy and trigger rule matched in dry-run mode. No notification was sent.',$probability,$confidence,$evidence);
    if ($deliveryLimitReached) return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'blocked','run_delivery_limit','The merchant run-level notification limit was reached.',$probability,$confidence,$evidence);
    $idempotencyKey = 'trigger-orchestration-' . substr(hash('sha256',(string)$rule['public_id'].':'.(string)$event['public_id']),0,48);
    $note = mg_trigger_orchestration_note($rule,$event,$policy,$session);
    try {
        $delivery = mg_store_send_campaign_recommendation_notification($pdo,$merchantUser,(string)$session['public_id'],(string)$rule['campaign_public_id'],$note,$idempotencyKey);
        return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'delivered','notification_delivered','Governed campaign recommendation notification delivered.',$probability,$confidence,$evidence + ['delivery_channel'=>'notification','campaign_completion_required'=>true],(string)($delivery['recommendation_id'] ?? ''),(string)($delivery['notification_id'] ?? ''));
    } catch (Throwable $error) {
        $message = strtolower($error->getMessage());
        $blocked = str_contains($message,'not message') || str_contains($message,'blocked') || str_contains($message,'cooldown') || str_contains($message,'within the last');
        if ($blocked) return mg_trigger_orchestration_record($pdo,$event,$rule,$mode,'blocked','delivery_blocked',$error->getMessage(),$probability,$confidence,$evidence);
        throw $error;
    }
}

function mg_trigger_orchestration_dead_letter(PDO $pdo, array $event, string $reasonCode, string $reasonText): void
{
    $payload = mg_trigger_orchestration_json($event['payload_json'] ?? null);
    $stmt = $pdo->prepare("INSERT INTO mg_store_trigger_dead_letters (public_id,merchant_user_id,trigger_event_id,event_public_id,event_type,source_type,source_public_id,customer_user_id,reason_code,reason_text,attempt_count,payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'open',NOW(),NOW()) ON DUPLICATE KEY UPDATE reason_code=VALUES(reason_code),reason_text=VALUES(reason_text),attempt_count=VALUES(attempt_count),payload_json=VALUES(payload_json),status='open',updated_at=NOW()");
    $stmt->execute([mg_trigger_orchestration_uuid(),(int)$event['merchant_user_id'],(int)$event['id'],(string)$event['public_id'],(string)$event['event_type'],(string)$event['source_type'],$event['source_public_id'] ?: null,(int)$event['customer_user_id'],$reasonCode,mb_substr($reasonText,0,1000),(int)$event['attempt_count'],mg_store_trigger_engine_encode($payload)]);
    $pdo->prepare("UPDATE mg_store_trigger_events SET status='dead_letter',dead_lettered_at=NOW(),processed_at=NOW(),locked_at=NULL,locked_by=NULL,last_error_code=?,last_error_message=?,updated_at=NOW() WHERE id=?")->execute([$reasonCode,mb_substr($reasonText,0,1000),(int)$event['id']]);
}

function mg_trigger_orchestration_retry(PDO $pdo, array $event, string $reasonCode, string $reasonText, int $delaySeconds, ?string $availableAt = null): string
{
    $attempts = (int)$event['attempt_count'] + 1;
    if ($attempts >= max(1,(int)$event['max_attempts'])) {
        $event['attempt_count'] = $attempts;
        mg_trigger_orchestration_dead_letter($pdo,$event,$reasonCode,$reasonText);
        return 'dead_letter';
    }
    $availableAt ??= date('Y-m-d H:i:s',time()+max(300,$delaySeconds));
    $stmt = $pdo->prepare("UPDATE mg_store_trigger_events SET status='retry',attempt_count=?,available_at=?,locked_at=NULL,locked_by=NULL,last_error_code=?,last_error_message=?,updated_at=NOW() WHERE id=?");
    $stmt->execute([$attempts,$availableAt,$reasonCode,mb_substr($reasonText,0,1000),(int)$event['id']]);
    return 'retry';
}

function mg_trigger_orchestration_process_queue(PDO $pdo, array $merchantUser, bool $forceDryRun = false, int $limit = 100): array
{
    mg_trigger_orchestration_require_schema($pdo);
    $merchantUserId = (int)($merchantUser['id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant account is required.');
    $settings = mg_trigger_orchestration_settings($pdo,$merchantUserId);
    $mode = $forceDryRun ? 'dry_run' : (string)$settings['execution_mode'];
    if ($mode === 'paused') throw new RuntimeException('Trigger engine is paused. Select Dry Run or Notification mode first.');
    $run = mg_trigger_scheduler_run_start($pdo,$merchantUserId,'orchestration',$mode);
    $summary = ['run_id'=>$run['public_id'],'mode'=>$mode,'sources_scanned'=>0,'records_scanned'=>0,'events_queued'=>0,'events_evaluated'=>0,'notifications_delivered'=>0,'events_blocked'=>0,'events_retried'=>0,'events_dead_lettered'=>0,'errors'=>0,'ignored'=>0,'duplicates'=>0,'reward_issued'=>false,'browser_overlap_used'=>false,'recent'=>[]];
    if ($settings['emergency_pause'] && $mode === 'notification') {
        $summary['status'] = 'paused';
        $summary['pause_reason'] = 'global_emergency_pause';
        mg_trigger_scheduler_run_finish($pdo,$run,$summary,'paused');
        return $summary;
    }
    $limit = max(1,min(500,$limit));
    $worker = 'orchestration:' . $run['public_id'];
    $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_events WHERE merchant_user_id=? AND status IN ('pending','retry') AND COALESCE(available_at,created_at)<=NOW() ORDER BY event_at ASC,id ASC LIMIT {$limit}");
    $stmt->execute([$merchantUserId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $maxDeliveries = max(1,(int)$settings['max_notifications_per_run']);
    foreach ($events as $event) {
        $lock = $pdo->prepare("UPDATE mg_store_trigger_events SET status='processing',locked_at=NOW(),locked_by=?,updated_at=NOW() WHERE id=? AND status IN ('pending','retry')");
        $lock->execute([$worker,(int)$event['id']]);
        if ($lock->rowCount() < 1) { $summary['duplicates']++; continue; }
        $event['status'] = 'processing';
        try {
            $policy = mg_trigger_orchestration_policy_row($pdo,$merchantUserId,(string)$event['event_type']);
            if ($policy && (string)$policy['status'] === 'paused') {
                $pdo->prepare("UPDATE mg_store_trigger_events SET status='ignored',processed_at=NOW(),locked_at=NULL,locked_by=NULL,last_error_code='policy_paused',last_error_message='Orchestration policy is paused.',updated_at=NOW() WHERE id=?")->execute([(int)$event['id']]);
                $summary['ignored']++; continue;
            }
            $quietRetry = mg_trigger_orchestration_quiet_retry_at($settings,$policy);
            if ($quietRetry !== null && $mode === 'notification') {
                $result = mg_trigger_orchestration_retry($pdo,$event,'quiet_hours','Notification delivery deferred during quiet hours.',900,$quietRetry);
                $summary[$result === 'dead_letter' ? 'events_dead_lettered' : 'events_retried']++; continue;
            }
            $suppression = mg_trigger_orchestration_recent_wallet_suppression($pdo,$merchantUserId,(int)$event['customer_user_id'],(string)$event['event_type'],$policy);
            if ($suppression && $mode === 'notification') {
                $result = mg_trigger_orchestration_retry($pdo,$event,(string)$suppression['code'],(string)$suppression['message'],900,(string)$suppression['available_at']);
                $summary[$result === 'dead_letter' ? 'events_dead_lettered' : 'events_retried']++; continue;
            }
            $session = mg_trigger_ingestion_session($pdo,$merchantUserId,(int)$event['customer_user_id'],$event['store_session_id'] ? (int)$event['store_session_id'] : null);
            $activeSession = $session && !empty($session['active_key']) && in_array((string)$session['status'],['entered','active','idle'],true) && empty($session['exited_at']) && strtotime((string)$session['last_active_at']) >= time() - (MG_STORE_EXPIRE_MINUTES * 60);
            if (!$session || ($mode === 'notification' && !$activeSession)) {
                $retryDelay = $policy ? max(300,(int)$policy['retry_delay_seconds']) : 900;
                $result = mg_trigger_orchestration_retry($pdo,$event,'waiting_for_active_session','Customer interaction is queued until the customer has an active Store Canvas session.',$retryDelay);
                $summary[$result === 'dead_letter' ? 'events_dead_lettered' : 'events_retried']++; continue;
            }
            $ruleStmt = $pdo->prepare("SELECT * FROM mg_store_trigger_engine_rules WHERE merchant_user_id=? AND event_type=? AND status='enabled' ORDER BY priority DESC,updated_at DESC,id DESC LIMIT 100");
            $ruleStmt->execute([$merchantUserId,(string)$event['event_type']]);
            $rules = $ruleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!$rules) {
                $pdo->prepare("UPDATE mg_store_trigger_events SET status='ignored',processed_at=NOW(),locked_at=NULL,locked_by=NULL,last_error_code='no_enabled_rule',last_error_message='No enabled trigger rule matches this event.',updated_at=NOW() WHERE id=?")->execute([(int)$event['id']]);
                $summary['ignored']++; continue;
            }
            $eventEvaluated = false; $eventError = null;
            foreach ($rules as $rule) {
                if (mg_trigger_orchestration_evaluation_exists($pdo,(int)$event['id'],(int)$rule['id'],$mode)) { $summary['duplicates']++; continue; }
                try {
                    $evaluation = mg_trigger_orchestration_evaluate($pdo,$merchantUser,$event,$rule,$session,$policy,$mode,$summary['notifications_delivered'] >= $maxDeliveries);
                    $eventEvaluated = true; $summary['events_evaluated']++;
                    $decision = (string)$evaluation['decision'];
                    if ($decision === 'delivered') $summary['notifications_delivered']++;
                    elseif ($decision === 'blocked') $summary['events_blocked']++;
                    $summary['recent'][] = ['event_id'=>(string)$event['public_id'],'event_type'=>(string)$event['event_type'],'rule_id'=>(string)$rule['public_id'],'rule_name'=>(string)$rule['name'],'decision'=>$decision,'reason_code'=>(string)$evaluation['reason_code'],'reason_text'=>(string)$evaluation['reason_text'],'customer_user_id'=>(int)$event['customer_user_id']];
                } catch (Throwable $error) {
                    $eventError = $error;
                    break;
                }
            }
            if ($eventError) {
                $retryDelay = $policy ? max(300,(int)$policy['retry_delay_seconds']) : 900;
                $result = mg_trigger_orchestration_retry($pdo,$event,'transient_delivery_error',$eventError->getMessage(),$retryDelay);
                $summary[$result === 'dead_letter' ? 'events_dead_lettered' : 'events_retried']++; $summary['errors']++; continue;
            }
            $pdo->prepare("UPDATE mg_store_trigger_events SET status='evaluated',processed_at=NOW(),locked_at=NULL,locked_by=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$event['id']]);
            if (!$eventEvaluated) $summary['duplicates']++;
        } catch (Throwable $error) {
            $result = mg_trigger_orchestration_retry($pdo,$event,'processing_error',$error->getMessage(),900);
            $summary[$result === 'dead_letter' ? 'events_dead_lettered' : 'events_retried']++; $summary['errors']++;
        }
    }
    $summary['status'] = $summary['errors'] > 0 ? 'partial' : 'completed';
    $summary['recent'] = array_slice($summary['recent'],-50);
    $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET last_run_at=NOW(),last_run_status=?,last_run_summary_json=?,last_scheduler_heartbeat_at=NOW(),updated_at=NOW() WHERE merchant_user_id=?')->execute([$summary['status'],mg_store_trigger_engine_encode($summary),$merchantUserId]);
    mg_trigger_scheduler_run_finish($pdo,$run,$summary,$summary['status']);
    mg_event('store_canvas.trigger_orchestration_run',['merchant_user_id'=>$merchantUserId,'mode'=>$mode,'evaluated'=>$summary['events_evaluated'],'delivered'=>$summary['notifications_delivered'],'retried'=>$summary['events_retried'],'dead_lettered'=>$summary['events_dead_lettered'],'reward_issued'=>false,'browser_overlap_used'=>false],$merchantUserId);
    return $summary;
}

function mg_trigger_orchestration_retry_event(PDO $pdo, int $merchantUserId, string $eventPublicId): void
{
    $eventPublicId = mg_store_safe_public_id($eventPublicId,'Trigger event');
    $stmt = $pdo->prepare("UPDATE mg_store_trigger_events SET status='pending',attempt_count=0,available_at=NOW(),locked_at=NULL,locked_by=NULL,processed_at=NULL,last_error_code=NULL,last_error_message=NULL,dead_lettered_at=NULL,updated_at=NOW() WHERE public_id=? AND merchant_user_id=? AND status IN ('retry','dead_letter','error')");
    $stmt->execute([$eventPublicId,$merchantUserId]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Trigger event is not available for retry.');
    $pdo->prepare("UPDATE mg_store_trigger_dead_letters SET status='requeued',resolved_at=NOW(),updated_at=NOW() WHERE merchant_user_id=? AND event_public_id=? AND status='open'")->execute([$merchantUserId,$eventPublicId]);
}

function mg_trigger_orchestration_checkpoints(PDO $pdo, int $merchantUserId): array
{
    $items = [];
    foreach (mg_trigger_orchestration_sources() as $sourceKey => $label) {
        $row = mg_trigger_ingestion_checkpoint($pdo,$merchantUserId,$sourceKey);
        $items[] = ['id'=>(string)$row['public_id'],'source_key'=>$sourceKey,'label'=>$label,'health_status'=>(string)$row['health_status'],'last_scan_at'=>$row['last_scan_at'] ?? null,'last_success_at'=>$row['last_success_at'] ?? null,'last_error_code'=>$row['last_error_code'] ?? null,'last_error_message'=>$row['last_error_message'] ?? null,'ingested_count'=>(int)$row['ingested_count'],'skipped_count'=>(int)$row['skipped_count'],'cursor'=>mg_trigger_orchestration_json($row['cursor_json'] ?? null)];
    }
    return $items;
}

function mg_trigger_orchestration_summary(PDO $pdo, int $merchantUserId): array
{
    $counts = ['queued'=>0,'processing'=>0,'retry'=>0,'evaluated'=>0,'ignored'=>0,'dead_letter'=>0,'error'=>0,'delivered'=>0,'blocked'=>0];
    $stmt = $pdo->prepare('SELECT status,COUNT(*) total FROM mg_store_trigger_events WHERE merchant_user_id=? GROUP BY status');
    $stmt->execute([$merchantUserId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string)$row['status']; if ($key === 'pending') $key = 'queued'; if (array_key_exists($key,$counts)) $counts[$key] = (int)$row['total'];
    }
    $stmt = $pdo->prepare("SELECT decision,COUNT(*) total FROM mg_store_trigger_evaluations WHERE merchant_user_id=? GROUP BY decision");
    $stmt->execute([$merchantUserId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['decision'] === 'delivered') $counts['delivered'] = (int)$row['total'];
        if ((string)$row['decision'] === 'blocked') $counts['blocked'] = (int)$row['total'];
    }
    return $counts;
}

function mg_trigger_orchestration_timeline(PDO $pdo, int $merchantUserId, int $limit = 100): array
{
    $limit = max(1,min(300,$limit));
    $stmt = $pdo->prepare("SELECT e.public_id event_id,e.event_type,e.source_type,e.source_public_id,e.customer_user_id,e.store_session_public_id,e.status event_status,e.attempt_count,e.max_attempts,e.available_at,e.event_at,e.last_error_code,e.last_error_message,e.created_at,
        ev.execution_mode,ev.decision,ev.reason_code,ev.reason_text,ev.recommendation_id,ev.notification_id,ev.created_at evaluation_created_at,
        r.public_id rule_id,r.name rule_name,c.title campaign_title,p.public_id policy_id,p.name policy_name
        FROM mg_store_trigger_events e
        LEFT JOIN mg_store_trigger_evaluations ev ON ev.event_id=e.id AND ev.id=(SELECT MAX(ev2.id) FROM mg_store_trigger_evaluations ev2 WHERE ev2.event_id=e.id)
        LEFT JOIN mg_store_trigger_engine_rules r ON r.id=ev.rule_id
        LEFT JOIN campaigns c ON c.public_id=ev.campaign_public_id AND c.merchant_user_id=e.merchant_user_id
        LEFT JOIN mg_store_trigger_orchestration_policies p ON p.public_id=e.orchestration_policy_public_id AND p.merchant_user_id=e.merchant_user_id
        WHERE e.merchant_user_id=? ORDER BY e.id DESC LIMIT {$limit}");
    $stmt->execute([$merchantUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = ['event_id'=>(string)$row['event_id'],'event_type'=>(string)$row['event_type'],'event_label'=>mg_trigger_orchestration_event_types()[(string)$row['event_type']] ?? ucwords(str_replace('_',' ',(string)$row['event_type'])),'source_type'=>(string)$row['source_type'],'source_public_id'=>(string)($row['source_public_id'] ?? ''),'customer_user_id'=>(int)$row['customer_user_id'],'session_id'=>(string)($row['store_session_public_id'] ?? ''),'event_status'=>(string)$row['event_status'],'attempt_count'=>(int)$row['attempt_count'],'max_attempts'=>(int)$row['max_attempts'],'available_at'=>$row['available_at'] ?? null,'event_at'=>$row['event_at'] ?? null,'last_error_code'=>$row['last_error_code'] ?? null,'last_error_message'=>$row['last_error_message'] ?? null,'execution_mode'=>$row['execution_mode'] ?? null,'decision'=>$row['decision'] ?? null,'reason_code'=>$row['reason_code'] ?? null,'reason_text'=>$row['reason_text'] ?? null,'recommendation_id'=>(string)($row['recommendation_id'] ?? ''),'notification_id'=>(string)($row['notification_id'] ?? ''),'rule_id'=>(string)($row['rule_id'] ?? ''),'rule_name'=>(string)($row['rule_name'] ?? ''),'campaign_title'=>(string)($row['campaign_title'] ?? ''),'policy_id'=>(string)($row['policy_id'] ?? ''),'policy_name'=>(string)($row['policy_name'] ?? ''),'created_at'=>$row['created_at'] ?? null];
    }
    return $items;
}

function mg_trigger_orchestration_recent_runs(PDO $pdo, int $merchantUserId, int $limit = 20): array
{
    $limit = max(1,min(100,$limit));
    $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_scheduler_runs WHERE merchant_user_id=? ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute([$merchantUserId]);
    return array_map(static function(array $row): array {
        return ['id'=>(string)$row['public_id'],'run_type'=>(string)$row['run_type'],'execution_mode'=>(string)$row['execution_mode'],'status'=>(string)$row['status'],'started_at'=>$row['started_at'],'completed_at'=>$row['completed_at'] ?? null,'sources_scanned'=>(int)$row['sources_scanned'],'records_scanned'=>(int)$row['records_scanned'],'events_queued'=>(int)$row['events_queued'],'events_evaluated'=>(int)$row['events_evaluated'],'notifications_delivered'=>(int)$row['notifications_delivered'],'events_blocked'=>(int)$row['events_blocked'],'events_retried'=>(int)$row['events_retried'],'events_dead_lettered'=>(int)$row['events_dead_lettered'],'error_count'=>(int)$row['error_count'],'summary'=>mg_trigger_orchestration_json($row['summary_json'] ?? null),'error_message'=>$row['error_message'] ?? null];
    },$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_trigger_orchestration_payload(PDO $pdo, int $merchantUserId): array
{
    if (!mg_trigger_orchestration_schema_ready($pdo)) {
        return ['schema_ready'=>false,'missing_tables'=>mg_trigger_orchestration_missing_tables($pdo),'settings'=>null,'summary'=>[],'sources'=>[],'policies'=>[],'timeline'=>[],'runs'=>[],'event_types'=>mg_trigger_orchestration_event_types()];
    }
    return [
        'schema_ready'=>true,
        'settings'=>mg_trigger_orchestration_settings($pdo,$merchantUserId),
        'summary'=>mg_trigger_orchestration_summary($pdo,$merchantUserId),
        'sources'=>mg_trigger_orchestration_checkpoints($pdo,$merchantUserId),
        'policies'=>mg_trigger_orchestration_policies($pdo,$merchantUserId),
        'timeline'=>mg_trigger_orchestration_timeline($pdo,$merchantUserId,100),
        'runs'=>mg_trigger_orchestration_recent_runs($pdo,$merchantUserId,20),
        'event_types'=>mg_trigger_orchestration_event_types(),
        'security'=>[
            'server_events_only'=>true,'browser_overlap_authority'=>false,'notification_only'=>true,'direct_messages'=>false,
            'campaign_creation'=>false,'reward_creation'=>false,'wallet_write'=>false,'campaign_completion_reward_authority'=>true,
        ],
    ];
}
