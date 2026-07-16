<?php
declare(strict_types=1);

function mg_personal_agent_recovery_schema_ready(PDO $pdo): bool
{
    return mg_personal_agent_opportunity_schema_ready($pdo)
        && mg_personal_agent_table_exists($pdo, 'personal_agent_recovery_preferences')
        && mg_personal_agent_table_exists($pdo, 'personal_agent_opportunity_followups');
}

function mg_personal_agent_recovery_require_schema(PDO $pdo): void
{
    if (!mg_personal_agent_recovery_schema_ready($pdo)) {
        throw new RuntimeException('Personal Agent follow-up recovery database migration is required.');
    }
}

function mg_personal_agent_recovery_default_preferences(): array
{
    return [
        'enabled'=>true,
        'saved_followups_enabled'=>true,
        'cart_recovery_enabled'=>true,
        'campaign_expiry_enabled'=>true,
        'unavailable_alternative_enabled'=>true,
        'max_notifications_per_week'=>3,
        'cooldown_hours'=>48,
        'default_snooze_hours'=>24,
        'timezone'=>'UTC',
        'quiet_hours_start'=>'21:00',
        'quiet_hours_end'=>'08:00',
    ];
}

function mg_personal_agent_recovery_time_value(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $value, $match) !== 1) {
        throw new InvalidArgumentException('Quiet hours must use HH:MM time.');
    }
    return $match[1] . ':' . $match[2] . ':00';
}

function mg_personal_agent_recovery_timezone(mixed $value): string
{
    $timezone = trim((string)$value) ?: 'UTC';
    try { new DateTimeZone($timezone); }
    catch (Throwable) { throw new InvalidArgumentException('Choose a valid timezone.'); }
    return mb_substr($timezone, 0, 64);
}

function mg_personal_agent_recovery_preferences(PDO $pdo, int $userId): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $defaults = mg_personal_agent_recovery_default_preferences();
    $pdo->prepare('INSERT IGNORE INTO personal_agent_recovery_preferences (public_id,user_id,quiet_hours_start,quiet_hours_end,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
        ->execute([mg_public_uuid(),$userId,$defaults['quiet_hours_start'] . ':00',$defaults['quiet_hours_end'] . ':00']);
    $stmt = $pdo->prepare('SELECT * FROM personal_agent_recovery_preferences WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'id'=>(string)($row['public_id'] ?? ''),
        'enabled'=>(bool)($row['enabled'] ?? $defaults['enabled']),
        'saved_followups_enabled'=>(bool)($row['saved_followups_enabled'] ?? $defaults['saved_followups_enabled']),
        'cart_recovery_enabled'=>(bool)($row['cart_recovery_enabled'] ?? $defaults['cart_recovery_enabled']),
        'campaign_expiry_enabled'=>(bool)($row['campaign_expiry_enabled'] ?? $defaults['campaign_expiry_enabled']),
        'unavailable_alternative_enabled'=>(bool)($row['unavailable_alternative_enabled'] ?? $defaults['unavailable_alternative_enabled']),
        'max_notifications_per_week'=>max(1,min(14,(int)($row['max_notifications_per_week'] ?? $defaults['max_notifications_per_week']))),
        'cooldown_hours'=>max(1,min(336,(int)($row['cooldown_hours'] ?? $defaults['cooldown_hours']))),
        'default_snooze_hours'=>max(1,min(168,(int)($row['default_snooze_hours'] ?? $defaults['default_snooze_hours']))),
        'timezone'=>(string)($row['timezone'] ?? $defaults['timezone']),
        'quiet_hours_start'=>substr((string)($row['quiet_hours_start'] ?? $defaults['quiet_hours_start']),0,5),
        'quiet_hours_end'=>substr((string)($row['quiet_hours_end'] ?? $defaults['quiet_hours_end']),0,5),
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}

function mg_personal_agent_recovery_update_preferences(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $current = mg_personal_agent_recovery_preferences($pdo,$userId);
    $bool = static fn(string $key): int => filter_var($input[$key] ?? $current[$key],FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $max = max(1,min(14,(int)($input['max_notifications_per_week'] ?? $current['max_notifications_per_week'])));
    $cooldown = max(1,min(336,(int)($input['cooldown_hours'] ?? $current['cooldown_hours'])));
    $snooze = max(1,min(168,(int)($input['default_snooze_hours'] ?? $current['default_snooze_hours'])));
    $timezone = mg_personal_agent_recovery_timezone($input['timezone'] ?? $current['timezone']);
    $quietStart = mg_personal_agent_recovery_time_value($input['quiet_hours_start'] ?? $current['quiet_hours_start']);
    $quietEnd = mg_personal_agent_recovery_time_value($input['quiet_hours_end'] ?? $current['quiet_hours_end']);
    $stmt = $pdo->prepare('UPDATE personal_agent_recovery_preferences SET enabled=?,saved_followups_enabled=?,cart_recovery_enabled=?,campaign_expiry_enabled=?,unavailable_alternative_enabled=?,max_notifications_per_week=?,cooldown_hours=?,default_snooze_hours=?,timezone=?,quiet_hours_start=?,quiet_hours_end=?,updated_at=NOW() WHERE user_id=?');
    $stmt->execute([
        $bool('enabled'),$bool('saved_followups_enabled'),$bool('cart_recovery_enabled'),$bool('campaign_expiry_enabled'),
        $bool('unavailable_alternative_enabled'),$max,$cooldown,$snooze,$timezone,$quietStart,$quietEnd,$userId,
    ]);
    mg_audit('user_agent.recovery_preferences_updated','personal_agent_recovery_preferences',[
        'enabled'=>$bool('enabled')===1,'max_notifications_per_week'=>$max,'cooldown_hours'=>$cooldown,
    ],$userId);
    return mg_personal_agent_recovery_preferences($pdo,$userId);
}

function mg_personal_agent_recovery_trigger(string $value): string
{
    $value = strtolower(mg_personal_agent_text($value,50));
    $allowed = ['manual','saved','cart_abandoned','checkout_abandoned','campaign_expiring','unavailable_alternative'];
    if (!in_array($value,$allowed,true)) throw new InvalidArgumentException('Unsupported recovery trigger.');
    return $value;
}

function mg_personal_agent_recovery_trigger_enabled(array $preferences, string $trigger): bool
{
    if ($trigger === 'manual') return true;
    if (empty($preferences['enabled'])) return false;
    return match ($trigger) {
        'saved' => !empty($preferences['saved_followups_enabled']),
        'cart_abandoned','checkout_abandoned' => !empty($preferences['cart_recovery_enabled']),
        'campaign_expiring' => !empty($preferences['campaign_expiry_enabled']),
        'unavailable_alternative' => !empty($preferences['unavailable_alternative_enabled']),
        default => false,
    };
}

function mg_personal_agent_recovery_datetime(mixed $value, string $timezone = 'UTC'): string
{
    try {
        $date = $value instanceof DateTimeInterface
            ? new DateTimeImmutable($value->format(DateTimeInterface::ATOM))
            : new DateTimeImmutable(trim((string)$value) ?: 'now', new DateTimeZone($timezone));
    } catch (Throwable) {
        throw new InvalidArgumentException('Enter a valid follow-up date and time.');
    }
    $now = new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $date = $date->setTimezone(new DateTimeZone('UTC'));
    if ($date < $now) $date = $now;
    if ($date > $now->modify('+1 year')) throw new InvalidArgumentException('Follow-ups may be scheduled up to one year ahead.');
    return $date->format('Y-m-d H:i:s');
}

function mg_personal_agent_recovery_public(array $row): array
{
    return [
        'id'=>(string)($row['public_id'] ?? ''),
        'opportunity_id'=>(string)($row['opportunity_public_id'] ?? ''),
        'attribution_token'=>(string)($row['attribution_token'] ?? ''),
        'entity_type'=>(string)($row['entity_type'] ?? ''),
        'entity_id'=>(string)($row['entity_public_id'] ?? ''),
        'title'=>(string)($row['opportunity_title'] ?? $row['title'] ?? ''),
        'destination_url'=>(string)($row['destination_url'] ?? ''),
        'trigger_type'=>(string)($row['trigger_type'] ?? ''),
        'status'=>(string)($row['status'] ?? ''),
        'scheduled_for'=>$row['scheduled_for'] ?? null,
        'delivered_at'=>$row['delivered_at'] ?? null,
        'snoozed_until'=>$row['snoozed_until'] ?? null,
        'dismissed_at'=>$row['dismissed_at'] ?? null,
        'muted_at'=>$row['muted_at'] ?? null,
        'converted_at'=>$row['converted_at'] ?? null,
        'metadata'=>mg_personal_agent_json($row['metadata_json'] ?? null),
        'created_at'=>$row['created_at'] ?? null,
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}

function mg_personal_agent_recovery_followup_row(PDO $pdo, int $userId, string $publicId, bool $lock = false): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $stmt = $pdo->prepare('SELECT f.*,o.public_id opportunity_public_id,o.attribution_token,o.entity_type,o.entity_public_id,o.title opportunity_title,o.destination_url,o.state opportunity_state,o.source_context_json FROM personal_agent_opportunity_followups f INNER JOIN personal_agent_opportunities o ON o.id=f.opportunity_id WHERE f.user_id=? AND f.public_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$userId,$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Opportunity follow-up not found.');
    return $row;
}

function mg_personal_agent_recovery_is_muted(PDO $pdo, int $opportunityId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM personal_agent_opportunity_followups WHERE opportunity_id=? AND status='muted' LIMIT 1");
    $stmt->execute([$opportunityId]);
    return (bool)$stmt->fetchColumn();
}

function mg_personal_agent_recovery_schedule(PDO $pdo, array $opportunity, string $trigger, mixed $scheduledFor, array $metadata = [], ?string $idempotencyKey = null): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $trigger = mg_personal_agent_recovery_trigger($trigger);
    $userId = (int)($opportunity['user_id'] ?? 0);
    if ($userId < 1 || empty($opportunity['id'])) throw new InvalidArgumentException('A valid opportunity is required.');
    $preferences = mg_personal_agent_recovery_preferences($pdo,$userId);
    if (!mg_personal_agent_recovery_trigger_enabled($preferences,$trigger)) return [];
    if (mg_personal_agent_recovery_is_muted($pdo,(int)$opportunity['id'])) return [];
    $when = mg_personal_agent_recovery_datetime($scheduledFor,(string)$preferences['timezone']);
    $idempotencyKey = mg_personal_agent_text($idempotencyKey ?: implode(':',[$trigger,(string)$opportunity['public_id'],str_replace(['-',':',' '],'',$when)]),190);
    if ($idempotencyKey === '') throw new InvalidArgumentException('Follow-up idempotency key is required.');
    $publicId = mg_public_uuid();
    $metadataJson = $metadata !== [] ? mg_personal_agent_json_encode($metadata) : null;
    $stmt = $pdo->prepare("INSERT INTO personal_agent_opportunity_followups (public_id,opportunity_id,user_id,merchant_user_id,trigger_type,status,scheduled_for,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'scheduled',?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),scheduled_for=IF(status IN ('converted','muted','dismissed'),scheduled_for,VALUES(scheduled_for)),status=IF(status IN ('converted','muted','dismissed'),status,'scheduled'),snoozed_until=NULL,metadata_json=VALUES(metadata_json),last_error=NULL,updated_at=NOW()") ;
    $stmt->execute([$publicId,(int)$opportunity['id'],$userId,$opportunity['merchant_user_id'] ?: null,$trigger,$when,$idempotencyKey,$metadataJson]);
    $id = (int)$pdo->lastInsertId();
    $load = $pdo->prepare('SELECT f.*,o.public_id opportunity_public_id,o.attribution_token,o.entity_type,o.entity_public_id,o.title opportunity_title,o.destination_url FROM personal_agent_opportunity_followups f INNER JOIN personal_agent_opportunities o ON o.id=f.opportunity_id WHERE f.id=? LIMIT 1');
    $load->execute([$id]);
    $row = $load->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($row !== []) {
        mg_personal_agent_opportunity_event($pdo,$opportunity,'followup_scheduled',[
            'action_type'=>$trigger,'followup_public_id'=>(string)$row['public_id'],'scheduled_for'=>$when,
        ],'followup-scheduled:' . (string)$row['public_id']);
    }
    return mg_personal_agent_recovery_public($row);
}

function mg_personal_agent_recovery_cancel(PDO $pdo, int $opportunityId, array $triggers = [], string $status = 'cancelled'): void
{
    $where = "opportunity_id=? AND status IN ('scheduled','due','snoozed','delivered','failed')";
    $params = [$opportunityId];
    if ($triggers !== []) {
        $where .= ' AND trigger_type IN (' . implode(',',array_fill(0,count($triggers),'?')) . ')';
        array_push($params,...$triggers);
    }
    $sql = "UPDATE personal_agent_opportunity_followups SET status=?,updated_at=NOW() WHERE {$where}";
    array_unshift($params,$status);
    $pdo->prepare($sql)->execute($params);
}

function mg_personal_agent_recovery_mark_converted(PDO $pdo, array $opportunity, string $eventType): void
{
    $stmt = $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='converted',converted_at=NOW(),updated_at=NOW() WHERE opportunity_id=? AND status IN ('scheduled','due','delivered','snoozed','failed')");
    $stmt->execute([(int)$opportunity['id']]);
    if ($stmt->rowCount() > 0) {
        mg_personal_agent_opportunity_event($pdo,$opportunity,'recovery_converted',[
            'action_type'=>$eventType,'converted_followups'=>$stmt->rowCount(),
        ],'recovery-converted:' . (string)$opportunity['public_id'] . ':' . $eventType);
    }
}

function mg_personal_agent_recovery_on_event(PDO $pdo, array $opportunity, string $eventType, array $event, array $metadata): void
{
    if (!mg_personal_agent_recovery_schema_ready($pdo)) return;
    $eventId = (string)($event['id'] ?? mg_public_uuid());
    $created = (string)($event['created_at'] ?? gmdate('Y-m-d H:i:s'));
    try {
        switch ($eventType) {
            case 'saved':
                mg_personal_agent_recovery_schedule($pdo,$opportunity,'saved',(new DateTimeImmutable($created,new DateTimeZone('UTC')))->modify('+72 hours'),[
                    'source_event_id'=>$eventId,'reason'=>'saved_opportunity',
                ],'saved:' . $eventId);
                break;
            case 'unsaved':
                mg_personal_agent_recovery_cancel($pdo,(int)$opportunity['id'],['saved']);
                break;
            case 'cart_added':
                mg_personal_agent_recovery_schedule($pdo,$opportunity,'cart_abandoned',(new DateTimeImmutable($created,new DateTimeZone('UTC')))->modify('+6 hours'),[
                    'source_event_id'=>$eventId,'reason'=>'cart_added',
                ],'cart-abandoned:' . $eventId);
                break;
            case 'checkout_started':
                mg_personal_agent_recovery_cancel($pdo,(int)$opportunity['id'],['cart_abandoned']);
                mg_personal_agent_recovery_schedule($pdo,$opportunity,'checkout_abandoned',(new DateTimeImmutable($created,new DateTimeZone('UTC')))->modify('+2 hours'),[
                    'source_event_id'=>$eventId,'order_public_id'=>$metadata['order_public_id'] ?? null,'reason'=>'checkout_started',
                ],'checkout-abandoned:' . $eventId);
                break;
            case 'purchase_completed':
            case 'campaign_join_completed':
                mg_personal_agent_recovery_mark_converted($pdo,$opportunity,$eventType);
                break;
            case 'hidden':
                $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='muted',muted_at=NOW(),updated_at=NOW() WHERE opportunity_id=? AND status NOT IN ('converted','cancelled')")
                    ->execute([(int)$opportunity['id']]);
                break;
        }
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning','user_agent.recovery_event_hook_failed','Opportunity recovery automation could not process an attribution event.',[
                'event_type'=>$eventType,'opportunity_id'=>$opportunity['public_id'] ?? null,'exception_type'=>$error::class,
            ],(int)($opportunity['user_id'] ?? 0));
        }
    }
}

function mg_personal_agent_recovery_followups(PDO $pdo, int $userId, string $status = 'active', int $limit = 100): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $limit = max(1,min(200,$limit));
    $allowed = ['active','scheduled','due','delivered','snoozed','dismissed','muted','converted','cancelled','failed','all'];
    if (!in_array($status,$allowed,true)) $status = 'active';
    $where = '';
    $params = [$userId];
    if ($status === 'active') $where = " AND f.status IN ('scheduled','due','delivered','snoozed','failed')";
    elseif ($status !== 'all') { $where = ' AND f.status=?'; $params[] = $status; }
    $stmt = $pdo->prepare("SELECT f.*,o.public_id opportunity_public_id,o.attribution_token,o.entity_type,o.entity_public_id,o.title opportunity_title,o.destination_url,o.state opportunity_state,o.source_context_json FROM personal_agent_opportunity_followups f INNER JOIN personal_agent_opportunities o ON o.id=f.opportunity_id WHERE f.user_id=?{$where} ORDER BY CASE f.status WHEN 'due' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'snoozed' THEN 2 WHEN 'delivered' THEN 3 ELSE 4 END,COALESCE(f.snoozed_until,f.scheduled_for) ASC,f.id DESC LIMIT {$limit}");
    $stmt->execute($params);
    return array_map('mg_personal_agent_recovery_public',$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_personal_agent_recovery_action(PDO $pdo, int $userId, string $followupPublicId, string $action, array $input = []): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $action = strtolower(mg_personal_agent_text($action,30));
    if (!in_array($action,['snooze','dismiss','mute','resume'],true)) throw new InvalidArgumentException('Unsupported follow-up action.');
    $pdo->beginTransaction();
    try {
        $row = mg_personal_agent_recovery_followup_row($pdo,$userId,$followupPublicId,true);
        if ($action === 'snooze') {
            $preferences = mg_personal_agent_recovery_preferences($pdo,$userId);
            $hours = max(1,min(168,(int)($input['snooze_hours'] ?? $preferences['default_snooze_hours'])));
            $when = mg_personal_agent_recovery_datetime((new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+' . $hours . ' hours'));
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='snoozed',scheduled_for=?,snoozed_until=?,updated_at=NOW() WHERE id=?")
                ->execute([$when,$when,(int)$row['id']]);
            $eventType = 'followup_snoozed';
        } elseif ($action === 'dismiss') {
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='dismissed',dismissed_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([(int)$row['id']]);
            $eventType = 'followup_dismissed';
        } elseif ($action === 'mute') {
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='muted',muted_at=NOW(),updated_at=NOW() WHERE opportunity_id=? AND status NOT IN ('converted','cancelled')")
                ->execute([(int)$row['opportunity_id']]);
            $eventType = 'followup_muted';
        } else {
            $when = mg_personal_agent_recovery_datetime((new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 hour'));
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='scheduled',scheduled_for=?,snoozed_until=NULL,dismissed_at=NULL,muted_at=NULL,last_error=NULL,updated_at=NOW() WHERE id=?")
                ->execute([$when,(int)$row['id']]);
            $eventType = 'followup_scheduled';
        }
        $opportunity = mg_personal_agent_opportunity_find($pdo,$userId,(string)$row['opportunity_public_id']);
        mg_personal_agent_opportunity_event($pdo,$opportunity,$eventType,[
            'action_type'=>$action,'followup_public_id'=>$followupPublicId,
        ],$eventType . ':' . $followupPublicId . ':' . date('YmdHis'));
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return mg_personal_agent_recovery_public(mg_personal_agent_recovery_followup_row($pdo,$userId,$followupPublicId));
}

function mg_personal_agent_recovery_latest_opportunity(PDO $pdo, int $userId, string $threadPublicId = '', string $mode = 'recent'): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $where = "o.user_id=? AND o.state<>'hidden'";
    $params = [$userId];
    if ($threadPublicId !== '') { $where .= ' AND o.thread_public_id=?'; $params[] = $threadPublicId; }
    if ($mode === 'saved') $where .= " AND o.state='saved'";
    elseif ($mode === 'unfinished') $where .= " AND o.state<>'completed' AND EXISTS(SELECT 1 FROM personal_agent_opportunity_events e WHERE e.opportunity_id=o.id AND e.event_type IN ('cart_added','checkout_started','gift_started','campaign_join_started'))";
    $stmt = $pdo->prepare("SELECT o.* FROM personal_agent_opportunities o WHERE {$where} ORDER BY COALESCE(o.last_action_at,o.updated_at) DESC,o.id DESC LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function mg_personal_agent_recovery_context(PDO $pdo, int $userId): array
{
    if (!mg_personal_agent_recovery_schema_ready($pdo)) return ['available'=>false];
    $saved = mg_personal_agent_opportunity_list($pdo,$userId,'saved',12);
    $recentStmt = $pdo->prepare("SELECT * FROM personal_agent_opportunities WHERE user_id=? AND state<>'hidden' ORDER BY COALESCE(last_action_at,updated_at) DESC,id DESC LIMIT 12");
    $recentStmt->execute([$userId]);
    $recent = array_map('mg_personal_agent_opportunity_public',$recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    $unfinishedStmt = $pdo->prepare("SELECT o.*,a.cart_added_at,a.checkout_started_at,a.gift_started_at,a.campaign_started_at FROM personal_agent_opportunities o INNER JOIN (SELECT opportunity_id,MAX(CASE WHEN event_type='cart_added' THEN created_at END) cart_added_at,MAX(CASE WHEN event_type='checkout_started' THEN created_at END) checkout_started_at,MAX(CASE WHEN event_type='gift_started' THEN created_at END) gift_started_at,MAX(CASE WHEN event_type='campaign_join_started' THEN created_at END) campaign_started_at,MAX(CASE WHEN event_type IN ('purchase_completed','campaign_join_completed') THEN created_at END) completed_event_at FROM personal_agent_opportunity_events GROUP BY opportunity_id) a ON a.opportunity_id=o.id WHERE o.user_id=? AND o.state NOT IN ('hidden','completed') AND a.completed_event_at IS NULL AND COALESCE(a.checkout_started_at,a.cart_added_at,a.gift_started_at,a.campaign_started_at) IS NOT NULL ORDER BY COALESCE(a.checkout_started_at,a.cart_added_at,a.gift_started_at,a.campaign_started_at) DESC LIMIT 12");
    $unfinishedStmt->execute([$userId]);
    $unfinished = array_map(static function(array $row): array {
        $item = mg_personal_agent_opportunity_public($row);
        $item['cart_added_at'] = $row['cart_added_at'] ?? null;
        $item['checkout_started_at'] = $row['checkout_started_at'] ?? null;
        $item['gift_started_at'] = $row['gift_started_at'] ?? null;
        $item['campaign_started_at'] = $row['campaign_started_at'] ?? null;
        return $item;
    },$unfinishedStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    return [
        'available'=>true,
        'preferences'=>mg_personal_agent_recovery_preferences($pdo,$userId),
        'saved_opportunities'=>$saved,
        'recent_opportunities'=>$recent,
        'unfinished_opportunities'=>$unfinished,
        'followups'=>mg_personal_agent_recovery_followups($pdo,$userId,'active',20),
    ];
}

function mg_personal_agent_recovery_scan(PDO $pdo, int $limit = 100): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $limit = max(1,min(500,$limit));
    $scheduled = 0;
    $items = [];

    $savedSql = "SELECT o.*,e.public_id source_event_id,e.created_at source_event_at FROM personal_agent_opportunities o INNER JOIN personal_agent_opportunity_events e ON e.id=(SELECT MAX(se.id) FROM personal_agent_opportunity_events se WHERE se.opportunity_id=o.id AND se.event_type='saved') WHERE o.state='saved' AND e.created_at<=DATE_SUB(NOW(),INTERVAL 24 HOUR) ORDER BY e.created_at LIMIT {$limit}";
    foreach ($pdo->query($savedSql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $followup = mg_personal_agent_recovery_schedule($pdo,$row,'saved',(new DateTimeImmutable((string)$row['source_event_at'],new DateTimeZone('UTC')))->modify('+72 hours'),['source_event_id'=>$row['source_event_id'],'scanner'=>true],'saved:' . $row['source_event_id']);
        if ($followup !== []) { $scheduled++; $items[] = $followup; }
        if ($scheduled >= $limit) break;
    }

    if ($scheduled < $limit) {
        $eventSql = "SELECT o.*,a.cart_event_id,a.cart_added_at,a.checkout_event_id,a.checkout_started_at,a.completed_at FROM personal_agent_opportunities o INNER JOIN (SELECT opportunity_id,MAX(CASE WHEN event_type='cart_added' THEN id END) cart_event_id,MAX(CASE WHEN event_type='cart_added' THEN created_at END) cart_added_at,MAX(CASE WHEN event_type='checkout_started' THEN id END) checkout_event_id,MAX(CASE WHEN event_type='checkout_started' THEN created_at END) checkout_started_at,MAX(CASE WHEN event_type IN ('purchase_completed','campaign_join_completed') THEN created_at END) completed_at FROM personal_agent_opportunity_events GROUP BY opportunity_id) a ON a.opportunity_id=o.id WHERE o.state NOT IN ('hidden','completed') AND a.completed_at IS NULL AND (a.cart_added_at<=DATE_SUB(NOW(),INTERVAL 6 HOUR) OR a.checkout_started_at<=DATE_SUB(NOW(),INTERVAL 2 HOUR)) ORDER BY COALESCE(a.checkout_started_at,a.cart_added_at) LIMIT {$limit}";
        foreach ($pdo->query($eventSql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($scheduled >= $limit) break;
            if (!empty($row['checkout_started_at'])) {
                $key = 'checkout-scan:' . (string)$row['public_id'] . ':' . (string)$row['checkout_event_id'];
                $followup = mg_personal_agent_recovery_schedule($pdo,$row,'checkout_abandoned','now',['scanner'=>true,'source_event_id'=>$row['checkout_event_id']],$key);
            } else {
                $key = 'cart-scan:' . (string)$row['public_id'] . ':' . (string)$row['cart_event_id'];
                $followup = mg_personal_agent_recovery_schedule($pdo,$row,'cart_abandoned','now',['scanner'=>true,'source_event_id'=>$row['cart_event_id']],$key);
            }
            if ($followup !== []) { $scheduled++; $items[] = $followup; }
        }
    }

    if ($scheduled < $limit && mg_personal_agent_table_exists($pdo,'campaigns')) {
        $campaignSql = "SELECT o.*,c.ends_at FROM personal_agent_opportunities o INNER JOIN campaigns c ON c.public_id=o.entity_public_id WHERE o.entity_type='campaign' AND o.state IN ('active','saved') AND c.status='active' AND c.ends_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 48 HOUR) ORDER BY c.ends_at LIMIT {$limit}";
        foreach ($pdo->query($campaignSql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($scheduled >= $limit) break;
            $key = 'campaign-expiring:' . (string)$row['public_id'] . ':' . date('YmdH',strtotime((string)$row['ends_at']));
            $followup = mg_personal_agent_recovery_schedule($pdo,$row,'campaign_expiring','now',['scanner'=>true,'ends_at'=>$row['ends_at']],$key);
            if ($followup !== []) { $scheduled++; $items[] = $followup; }
        }
    }

    if ($scheduled < $limit && mg_personal_agent_table_exists($pdo,'catalog_products')) {
        $productSql = "SELECT o.* FROM personal_agent_opportunities o LEFT JOIN catalog_products p ON p.public_id=o.entity_public_id LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE o.entity_type='product' AND o.state IN ('active','saved') AND (p.id IS NULL OR p.status<>'published' OR v.id IS NULL OR v.version_status<>'published') ORDER BY o.updated_at DESC LIMIT {$limit}";
        foreach ($pdo->query($productSql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($scheduled >= $limit) break;
            $key = 'unavailable:' . (string)$row['public_id'] . ':' . date('Ymd');
            $followup = mg_personal_agent_recovery_schedule($pdo,$row,'unavailable_alternative','now',['scanner'=>true],$key);
            if ($followup !== []) { $scheduled++; $items[] = $followup; }
        }
    }

    return ['scheduled'=>$scheduled,'items'=>array_slice($items,0,100)];
}

function mg_personal_agent_recovery_quiet_release(array $preferences, DateTimeImmutable $nowUtc): DateTimeImmutable
{
    $timezone = new DateTimeZone((string)$preferences['timezone']);
    $local = $nowUtc->setTimezone($timezone);
    $start = (string)($preferences['quiet_hours_start'] ?? '');
    $end = (string)($preferences['quiet_hours_end'] ?? '');
    if (preg_match('/^(\d{2}):(\d{2})$/',$start,$sm) !== 1 || preg_match('/^(\d{2}):(\d{2})$/',$end,$em) !== 1) return $nowUtc;
    $startAt = $local->setTime((int)$sm[1],(int)$sm[2]);
    $endAt = $local->setTime((int)$em[1],(int)$em[2]);
    if ($startAt == $endAt) return $nowUtc;
    $release = null;
    if ($startAt < $endAt && $local >= $startAt && $local < $endAt) $release = $endAt;
    elseif ($startAt > $endAt && $local >= $startAt) $release = $endAt->modify('+1 day');
    elseif ($startAt > $endAt && $local < $endAt) $release = $endAt;
    return $release ? $release->setTimezone(new DateTimeZone('UTC')) : $nowUtc;
}

function mg_personal_agent_recovery_message(array $row): array
{
    $title = (string)($row['opportunity_title'] ?? 'Saved opportunity');
    return match ((string)$row['trigger_type']) {
        'saved' => ['A saved opportunity is waiting','Your Personal Agent saved “' . $title . '” so you can review it when the timing is right.'],
        'cart_abandoned' => ['Your saved cart is still available','You added “' . $title . '” to your cart but did not start checkout.'],
        'checkout_abandoned' => ['Your checkout is unfinished','You started checkout for “' . $title . '” and can continue without searching again.'],
        'campaign_expiring' => ['A saved campaign is ending soon','“' . $title . '” is approaching its end date. Review it before the opportunity closes.'],
        'unavailable_alternative' => ['A saved item changed','“' . $title . '” may no longer be available. Your Agent can help find a similar local option.'],
        default => ['Personal Agent reminder','You asked to be reminded about “' . $title . '”.'],
    };
}

function mg_personal_agent_recovery_process_due(PDO $pdo, int $limit = 50): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    require_once dirname(__DIR__,2) . '/api/communications/_communications.php';
    $limit = max(1,min(200,$limit));
    $processed = 0; $delivered = 0; $deferred = 0; $cancelled = 0; $failed = 0; $items = [];
    for ($index=0;$index<$limit;$index++) {
        $pdo->beginTransaction();
        try {
            $sql = "SELECT f.*,o.public_id opportunity_public_id,o.attribution_token,o.user_id opportunity_user_id,o.entity_type,o.entity_public_id,o.title opportunity_title,o.destination_url,o.state opportunity_state,o.source_context_json FROM personal_agent_opportunity_followups f INNER JOIN personal_agent_opportunities o ON o.id=f.opportunity_id WHERE f.status IN ('scheduled','snoozed','due','failed') AND COALESCE(f.snoozed_until,f.scheduled_for)<=NOW() AND f.attempt_count<5 ORDER BY COALESCE(f.snoozed_until,f.scheduled_for),f.id LIMIT 1 FOR UPDATE";
            $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $pdo->commit(); break; }
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='due',attempt_count=attempt_count+1,updated_at=NOW() WHERE id=?")
                ->execute([(int)$row['id']]);
            $row['attempt_count'] = (int)$row['attempt_count'] + 1;
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        $processed++;
        try {
            $preferences = mg_personal_agent_recovery_preferences($pdo,(int)$row['user_id']);
            if ((string)$row['opportunity_state'] === 'completed') {
                $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='converted',converted_at=COALESCE(converted_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
                $cancelled++; continue;
            }
            if ((string)$row['opportunity_state'] === 'hidden' || !mg_personal_agent_recovery_trigger_enabled($preferences,(string)$row['trigger_type'])) {
                $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
                $cancelled++; continue;
            }
            $weekly = $pdo->prepare("SELECT COUNT(*) FROM personal_agent_opportunity_followups WHERE user_id=? AND status IN ('delivered','converted') AND delivered_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
            $weekly->execute([(int)$row['user_id']]);
            if ((int)$weekly->fetchColumn() >= (int)$preferences['max_notifications_per_week']) {
                $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='scheduled',scheduled_for=DATE_ADD(NOW(),INTERVAL 24 HOUR),snoozed_until=NULL,last_error='frequency_cap',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
                $deferred++; continue;
            }
            $cooldown = $pdo->prepare("SELECT MAX(delivered_at) FROM personal_agent_opportunity_followups WHERE opportunity_id=? AND delivered_at IS NOT NULL AND id<>?");
            $cooldown->execute([(int)$row['opportunity_id'],(int)$row['id']]);
            $last = (string)($cooldown->fetchColumn() ?: '');
            if ($last !== '') {
                $release = (new DateTimeImmutable($last,new DateTimeZone('UTC')))->modify('+' . (int)$preferences['cooldown_hours'] . ' hours');
                if ($release > new DateTimeImmutable('now',new DateTimeZone('UTC'))) {
                    $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='scheduled',scheduled_for=?,snoozed_until=NULL,last_error='cooldown',updated_at=NOW() WHERE id=?")
                        ->execute([$release->format('Y-m-d H:i:s'),(int)$row['id']]);
                    $deferred++; continue;
                }
            }
            $now = new DateTimeImmutable('now',new DateTimeZone('UTC'));
            $quietRelease = mg_personal_agent_recovery_quiet_release($preferences,$now);
            if ($quietRelease > $now->modify('+1 minute')) {
                $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='scheduled',scheduled_for=?,snoozed_until=NULL,last_error='quiet_hours',updated_at=NOW() WHERE id=?")
                    ->execute([$quietRelease->format('Y-m-d H:i:s'),(int)$row['id']]);
                $deferred++; continue;
            }
            [$title,$body] = mg_personal_agent_recovery_message($row);
            $actionUrl = '/lists.php?opportunity=' . rawurlencode((string)$row['opportunity_public_id']) . '&recovery=' . rawurlencode((string)$row['public_id']);
            $notificationId = mg_create_notification($pdo,(int)$row['user_id'],'personal_agent_recovery',$title,$body,$actionUrl,[
                'allow_self'=>true,
                'event_key'=>'personal_agent.recovery.' . (string)$row['public_id'],
                'merchant_user_id'=>$row['merchant_user_id'] ? (int)$row['merchant_user_id'] : null,
                'opportunity_id'=>(string)$row['opportunity_public_id'],
                'followup_id'=>(string)$row['public_id'],
                'trigger_type'=>(string)$row['trigger_type'],
            ]);
            if ($notificationId === '') {
                $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='cancelled',last_error='No enabled notification channel.',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
                $cancelled++; continue;
            }
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status='delivered',notification_public_id=?,delivered_at=NOW(),snoozed_until=NULL,last_error=NULL,updated_at=NOW() WHERE id=?")
                ->execute([$notificationId,(int)$row['id']]);
            $opportunity = mg_personal_agent_opportunity_find($pdo,(int)$row['user_id'],(string)$row['opportunity_public_id']);
            mg_personal_agent_opportunity_event($pdo,$opportunity,'followup_delivered',[
                'action_type'=>(string)$row['trigger_type'],'followup_public_id'=>(string)$row['public_id'],'notification_public_id'=>$notificationId,
            ],'followup-delivered:' . (string)$row['public_id']);
            $delivered++;
            $items[] = ['followup_id'=>$row['public_id'],'notification_id'=>$notificationId,'trigger_type'=>$row['trigger_type']];
        } catch (Throwable $error) {
            $retry = (int)$row['attempt_count'] < 5;
            $pdo->prepare("UPDATE personal_agent_opportunity_followups SET status=?,scheduled_for=IF(?,DATE_ADD(NOW(),INTERVAL 1 HOUR),scheduled_for),last_error=?,updated_at=NOW() WHERE id=?")
                ->execute([$retry ? 'failed' : 'failed',$retry ? 1 : 0,mb_substr($error->getMessage(),0,500),(int)$row['id']]);
            $failed++;
        }
    }
    return ['processed'=>$processed,'delivered'=>$delivered,'deferred'=>$deferred,'cancelled'=>$cancelled,'failed'=>$failed,'items'=>$items];
}

function mg_personal_agent_recovery_intent(string $message): string
{
    $message = mb_strtolower(trim(preg_replace('/\s+/u',' ',$message) ?? $message));
    if ($message === '') return '';
    if (str_contains($message,'remind me')) return 'remind';
    if (mg_personal_agent_message_mentions($message,['show my saved gifts','show my saved opportunities','saved gifts','saved opportunities'])) return 'saved';
    if (mg_personal_agent_message_mentions($message,['what was i looking at','what did i look at','show my recent opportunities','recent recommendations'])) return 'recent';
    if (mg_personal_agent_message_mentions($message,['complete the gift i started','finish the gift i started','finish checkout','continue my purchase','resume checkout'])) return 'resume';
    if (mg_personal_agent_message_mentions($message,['find something similar','show something similar','something like that','similar local option'])) return 'similar';
    return '';
}

function mg_personal_agent_recovery_parse_remind_at(string $message, string $timezone): DateTimeImmutable
{
    $tz = new DateTimeZone($timezone);
    $now = new DateTimeImmutable('now',$tz);
    $lower = mb_strtolower($message);
    if (preg_match('/\bin\s+(\d{1,3})\s*(hour|hours|day|days|week|weeks)\b/u',$lower,$match) === 1) {
        $amount = max(1,min(365,(int)$match[1]));
        $unit = str_starts_with($match[2],'hour') ? 'hours' : (str_starts_with($match[2],'week') ? 'weeks' : 'days');
        return $now->modify('+' . $amount . ' ' . $unit);
    }
    $time = [9,0];
    if (preg_match('/\bat\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/u',$lower,$clock) === 1) {
        $hour = min(23,(int)$clock[1]); $minute = min(59,(int)($clock[2] ?? 0));
        if (($clock[3] ?? '') === 'pm' && $hour < 12) $hour += 12;
        if (($clock[3] ?? '') === 'am' && $hour === 12) $hour = 0;
        $time = [$hour,$minute];
    }
    if (str_contains($lower,'tomorrow')) return $now->modify('+1 day')->setTime($time[0],$time[1]);
    if (preg_match('/\bnext\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/u',$lower,$weekday) === 1) {
        return $now->modify('next ' . $weekday[1])->setTime($time[0],$time[1]);
    }
    if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/',$lower,$dateMatch) === 1) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$dateMatch[1],$tz);
        if ($date) return $date->setTime($time[0],$time[1]);
    }
    return $now->modify('+1 day')->setTime($time[0],$time[1]);
}

function mg_personal_agent_recovery_merchant_analytics(PDO $pdo, int $merchantUserId, int $days = 90): array
{
    mg_personal_agent_recovery_require_schema($pdo);
    $days = max(1,min(365,$days));
    $stmt = $pdo->prepare("SELECT trigger_type,status,COUNT(*) total,COUNT(DISTINCT opportunity_id) opportunities,MAX(updated_at) last_at FROM personal_agent_opportunity_followups WHERE merchant_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY) GROUP BY trigger_type,status ORDER BY total DESC,last_at DESC");
    $stmt->execute([$merchantUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $summary = ['scheduled'=>0,'delivered'=>0,'snoozed'=>0,'dismissed'=>0,'muted'=>0,'converted'=>0,'abandoned_opportunities'=>0,'recovered_purchases'=>0,'recovered_campaign_joins'=>0,'recovered_revenue_cents'=>0,'average_hours_to_conversion'=>0.0];
    foreach ($rows as $row) {
        $count = (int)$row['total'];
        if (isset($summary[$row['status']])) $summary[$row['status']] += $count;
        if (in_array((string)$row['trigger_type'],['cart_abandoned','checkout_abandoned'],true)) $summary['abandoned_opportunities'] += (int)$row['opportunities'];
    }
    $delivered = $pdo->prepare("SELECT f.opportunity_id,MIN(f.delivered_at) delivered_at,MIN(o.created_at) recommended_at FROM personal_agent_opportunity_followups f INNER JOIN personal_agent_opportunities o ON o.id=f.opportunity_id WHERE f.merchant_user_id=? AND f.delivered_at IS NOT NULL AND f.created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY) GROUP BY f.opportunity_id");
    $delivered->execute([$merchantUserId]);
    $touches = [];
    foreach ($delivered->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $touches[(int)$row['opportunity_id']] = $row;
    if ($touches !== []) {
        $ids = array_keys($touches);
        $events = $pdo->prepare("SELECT opportunity_id,event_type,order_public_id,created_at FROM personal_agent_opportunity_events WHERE opportunity_id IN (" . implode(',',array_fill(0,count($ids),'?')) . ") AND event_type IN ('purchase_completed','campaign_join_completed') ORDER BY created_at");
        $events->execute($ids);
        $orders = [];
        $durations = [];
        foreach ($events->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
            $touch = $touches[(int)$event['opportunity_id']] ?? null;
            if (!$touch || strtotime((string)$event['created_at']) < strtotime((string)$touch['delivered_at'])) continue;
            if ($event['event_type'] === 'purchase_completed') {
                $summary['recovered_purchases']++;
                if (!empty($event['order_public_id'])) $orders[(string)$event['order_public_id']] = true;
            } else $summary['recovered_campaign_joins']++;
            $start = strtotime((string)$touch['recommended_at']); $end = strtotime((string)$event['created_at']);
            if ($start && $end && $end >= $start) $durations[] = ($end-$start)/3600;
            unset($touches[(int)$event['opportunity_id']]);
        }
        if ($orders !== [] && mg_personal_agent_table_exists($pdo,'commerce_orders')) {
            $orderIds = array_keys($orders);
            $orderStmt = $pdo->prepare('SELECT COALESCE(SUM(total_cents),0) FROM commerce_orders WHERE public_id IN (' . implode(',',array_fill(0,count($orderIds),'?')) . ')');
            $orderStmt->execute($orderIds);
            $summary['recovered_revenue_cents'] = (int)$orderStmt->fetchColumn();
        }
        if ($durations !== []) $summary['average_hours_to_conversion'] = round(array_sum($durations)/count($durations),1);
    }
    $summary['delivery_conversion_rate'] = $summary['delivered'] > 0 ? round(($summary['converted']/$summary['delivered'])*100,2) : 0.0;
    return ['summary'=>$summary,'by_trigger'=>$rows,'days'=>$days];
}
