<?php
declare(strict_types=1);

function mg_personal_agent_opportunity_schema_ready(PDO $pdo): bool
{
    return mg_personal_agent_table_exists($pdo, 'personal_agent_opportunities')
        && mg_personal_agent_table_exists($pdo, 'personal_agent_opportunity_events');
}

function mg_personal_agent_opportunity_require_schema(PDO $pdo): void
{
    if (!mg_personal_agent_opportunity_schema_ready($pdo)) {
        throw new RuntimeException('Personal Agent opportunity attribution database migration is required.');
    }
}

function mg_personal_agent_opportunity_token(): string
{
    return bin2hex(random_bytes(32));
}

function mg_personal_agent_opportunity_entity_type(mixed $value): string
{
    $type = strtolower(mg_personal_agent_text($value, 40));
    return in_array($type, ['merchant','product','campaign','reward','event','experience'], true) ? $type : 'product';
}

function mg_personal_agent_opportunity_state(mixed $value): string
{
    $state = strtolower(mg_personal_agent_text($value, 20));
    return in_array($state, ['active','saved','hidden','completed'], true) ? $state : 'active';
}

function mg_personal_agent_opportunity_internal_url(mixed $value): string
{
    $url = trim((string)$value);
    if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) return '';
    if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return '';
    return mb_substr($url, 0, 600);
}

function mg_personal_agent_opportunity_url(string $url, string $token, string $action = ''): string
{
    $url = mg_personal_agent_opportunity_internal_url($url);
    if ($url === '') return '';
    $separator = str_contains($url, '?') ? '&' : '?';
    $url .= $separator . 'agent_attribution=' . rawurlencode($token);
    if ($action !== '') $url .= '&agent_action=' . rawurlencode($action);
    return $url;
}

function mg_personal_agent_opportunity_public(array $row): array
{
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'attribution_token' => (string)($row['attribution_token'] ?? ''),
        'thread_id' => (string)($row['thread_public_id'] ?? ''),
        'assistant_message_id' => (string)($row['assistant_message_public_id'] ?? ''),
        'merchant_user_id' => isset($row['merchant_user_id']) ? (int)$row['merchant_user_id'] : null,
        'entity_type' => (string)($row['entity_type'] ?? ''),
        'entity_id' => (string)($row['entity_public_id'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'destination_url' => (string)($row['destination_url'] ?? ''),
        'state' => (string)($row['state'] ?? 'active'),
        'source_context' => mg_personal_agent_json($row['source_context_json'] ?? null),
        'last_action_at' => $row['last_action_at'] ?? null,
        'completed_at' => $row['completed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_personal_agent_opportunity_upsert(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_opportunity_require_schema($pdo);
    $entityType = mg_personal_agent_opportunity_entity_type($input['entity_type'] ?? 'product');
    $entityId = mg_personal_agent_text($input['entity_id'] ?? '', 190);
    $messageId = mg_personal_agent_nullable_text($input['assistant_message_id'] ?? null, 36);
    $threadId = mg_personal_agent_nullable_text($input['thread_id'] ?? null, 36);
    $title = mg_personal_agent_text($input['title'] ?? 'Opportunity', 255);
    $url = mg_personal_agent_opportunity_internal_url($input['destination_url'] ?? '');
    $merchantId = max(0, (int)($input['merchant_user_id'] ?? 0));
    $context = is_array($input['source_context'] ?? null) ? $input['source_context'] : [];
    if ($entityId === '' || $url === '') throw new InvalidArgumentException('Opportunity identity and destination are required.');

    if ($messageId !== null) {
        $stmt = $pdo->prepare('SELECT * FROM personal_agent_opportunities WHERE user_id=? AND assistant_message_public_id=? AND entity_type=? AND entity_public_id=? LIMIT 1');
        $stmt->execute([$userId,$messageId,$entityType,$entityId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $pdo->prepare('UPDATE personal_agent_opportunities SET merchant_user_id=?,title=?,destination_url=?,source_context_json=?,updated_at=NOW() WHERE id=?')
                ->execute([$merchantId > 0 ? $merchantId : null,$title,$url,mg_personal_agent_json_encode($context),(int)$existing['id']]);
            $stmt->execute([$userId,$messageId,$entityType,$entityId]);
            return mg_personal_agent_opportunity_public($stmt->fetch(PDO::FETCH_ASSOC) ?: $existing);
        }
    }

    $publicId = mg_public_uuid();
    $token = mg_personal_agent_opportunity_token();
    $pdo->prepare('INSERT INTO personal_agent_opportunities
      (public_id,attribution_token,user_id,thread_public_id,assistant_message_public_id,merchant_user_id,entity_type,entity_public_id,title,destination_url,state,source_context_json,created_at,updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,\'active\',?,NOW(),NOW())')
        ->execute([$publicId,$token,$userId,$threadId,$messageId,$merchantId > 0 ? $merchantId : null,$entityType,$entityId,$title,$url,mg_personal_agent_json_encode($context)]);
    $stmt = $pdo->prepare('SELECT * FROM personal_agent_opportunities WHERE public_id=? AND user_id=? LIMIT 1');
    $stmt->execute([$publicId,$userId]);
    return mg_personal_agent_opportunity_public($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

function mg_personal_agent_opportunity_find(PDO $pdo, int $userId, string $publicId = '', string $token = '', bool $lock = false): array
{
    mg_personal_agent_opportunity_require_schema($pdo);
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($publicId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM personal_agent_opportunities WHERE public_id=? AND user_id=? LIMIT 1' . $suffix);
        $stmt->execute([$publicId,$userId]);
    } elseif ($token !== '') {
        $stmt = $pdo->prepare('SELECT * FROM personal_agent_opportunities WHERE attribution_token=? AND user_id=? LIMIT 1' . $suffix);
        $stmt->execute([$token,$userId]);
    } else {
        throw new InvalidArgumentException('Opportunity identifier is required.');
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Opportunity not found.');
    return $row;
}

function mg_personal_agent_opportunity_event(PDO $pdo, array $opportunity, string $eventType, array $metadata = [], ?string $idempotencyKey = null): array
{
    $eventType = strtolower(mg_personal_agent_text($eventType, 80));
    $allowed = [
        'recommendation_created','recommendation_viewed','action_clicked','saved','unsaved','hidden','restored',
        'cart_added','checkout_started','purchase_completed','gift_started','campaign_join_started','campaign_join_completed','merchant_viewed','followup_requested'
    ];
    if (!in_array($eventType, $allowed, true)) throw new InvalidArgumentException('Unsupported opportunity event.');
    $action = mg_personal_agent_nullable_text($metadata['action_type'] ?? null, 50);
    $orderId = mg_personal_agent_nullable_text($metadata['order_public_id'] ?? null, 190);
    $campaignId = mg_personal_agent_nullable_text($metadata['campaign_public_id'] ?? null, 190);
    $productVersionId = mg_personal_agent_nullable_text($metadata['product_version_public_id'] ?? null, 190);
    unset($metadata['action_type'],$metadata['order_public_id'],$metadata['campaign_public_id'],$metadata['product_version_public_id']);
    $publicId = mg_public_uuid();
    try {
        $pdo->prepare('INSERT INTO personal_agent_opportunity_events
          (public_id,opportunity_id,user_id,merchant_user_id,event_type,action_type,entity_type,entity_public_id,order_public_id,campaign_public_id,product_version_public_id,idempotency_key,metadata_json,created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([
                $publicId,(int)$opportunity['id'],(int)$opportunity['user_id'],$opportunity['merchant_user_id'] ?: null,$eventType,$action,
                (string)$opportunity['entity_type'],(string)$opportunity['entity_public_id'],$orderId,$campaignId,$productVersionId,$idempotencyKey,
                mg_personal_agent_json_encode($metadata),
            ]);
    } catch (PDOException $error) {
        if ($idempotencyKey !== null && (string)$error->getCode() === '23000') {
            $stmt = $pdo->prepare('SELECT * FROM personal_agent_opportunity_events WHERE idempotency_key=? LIMIT 1');
            $stmt->execute([$idempotencyKey]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        throw $error;
    }
    $pdo->prepare('UPDATE personal_agent_opportunities SET last_action_at=NOW(),state=CASE WHEN ?=\'purchase_completed\' THEN \'completed\' ELSE state END,completed_at=CASE WHEN ?=\'purchase_completed\' THEN NOW() ELSE completed_at END,updated_at=NOW() WHERE id=?')
        ->execute([$eventType,$eventType,(int)$opportunity['id']]);
    return ['id'=>$publicId,'event_type'=>$eventType,'action_type'=>$action,'created_at'=>gmdate('Y-m-d H:i:s')];
}

function mg_personal_agent_opportunity_change_state(PDO $pdo, int $userId, string $publicId, string $state): array
{
    $state = mg_personal_agent_opportunity_state($state);
    $pdo->beginTransaction();
    try {
        $row = mg_personal_agent_opportunity_find($pdo,$userId,$publicId,'',true);
        $from = (string)$row['state'];
        $pdo->prepare('UPDATE personal_agent_opportunities SET state=?,last_action_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$state,(int)$row['id']]);
        $event = match ($state) {
            'saved' => 'saved',
            'hidden' => 'hidden',
            'active' => $from === 'saved' ? 'unsaved' : 'restored',
            default => 'recommendation_viewed',
        };
        mg_personal_agent_opportunity_event($pdo,$row,$event,['action_type'=>$state],$event . ':' . $publicId . ':' . $userId . ':' . date('YmdHi'));
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $fresh = mg_personal_agent_opportunity_find($pdo,$userId,$publicId);
    return mg_personal_agent_opportunity_public($fresh);
}

function mg_personal_agent_opportunity_list(PDO $pdo, int $userId, string $state = 'saved', int $limit = 50): array
{
    mg_personal_agent_opportunity_require_schema($pdo);
    $state = mg_personal_agent_opportunity_state($state);
    $limit = max(1,min(100,$limit));
    $stmt = $pdo->prepare("SELECT * FROM personal_agent_opportunities WHERE user_id=? AND state=? ORDER BY COALESCE(last_action_at,updated_at) DESC,id DESC LIMIT {$limit}");
    $stmt->execute([$userId,$state]);
    return array_map('mg_personal_agent_opportunity_public',$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_personal_agent_opportunity_merchant_analytics(PDO $pdo, int $merchantUserId, int $days = 90): array
{
    mg_personal_agent_opportunity_require_schema($pdo);
    $days = max(1,min(365,$days));
    $stmt = $pdo->prepare("SELECT event_type,action_type,entity_type,entity_public_id,COUNT(*) total,COUNT(DISTINCT user_id) customers,MAX(created_at) last_at
      FROM personal_agent_opportunity_events WHERE merchant_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)
      GROUP BY event_type,action_type,entity_type,entity_public_id ORDER BY total DESC,last_at DESC LIMIT 200");
    $stmt->execute([$merchantUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $summary = ['recommendations'=>0,'views'=>0,'action_clicks'=>0,'saves'=>0,'cart_adds'=>0,'checkouts'=>0,'purchases'=>0,'campaign_joins'=>0,'unique_customers'=>0];
    $customers = [];
    $customerStmt = $pdo->prepare("SELECT DISTINCT user_id FROM personal_agent_opportunity_events WHERE merchant_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)");
    $customerStmt->execute([$merchantUserId]);
    foreach ($customerStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) $customers[(int)$id] = true;
    foreach ($rows as $row) {
        $count = (int)$row['total'];
        switch ((string)$row['event_type']) {
            case 'recommendation_created': $summary['recommendations'] += $count; break;
            case 'recommendation_viewed': $summary['views'] += $count; break;
            case 'action_clicked': $summary['action_clicks'] += $count; break;
            case 'saved': $summary['saves'] += $count; break;
            case 'cart_added': $summary['cart_adds'] += $count; break;
            case 'checkout_started': $summary['checkouts'] += $count; break;
            case 'purchase_completed': $summary['purchases'] += $count; break;
            case 'campaign_join_completed': $summary['campaign_joins'] += $count; break;
        }
    }
    $summary['unique_customers'] = count($customers);
    $summary['click_through_rate'] = $summary['recommendations'] > 0 ? round(($summary['action_clicks'] / $summary['recommendations']) * 100,2) : 0.0;
    $summary['checkout_conversion_rate'] = $summary['action_clicks'] > 0 ? round(($summary['checkouts'] / $summary['action_clicks']) * 100,2) : 0.0;
    $summary['purchase_conversion_rate'] = $summary['action_clicks'] > 0 ? round(($summary['purchases'] / $summary['action_clicks']) * 100,2) : 0.0;
    return ['summary'=>$summary,'breakdown'=>$rows,'days'=>$days];
}
