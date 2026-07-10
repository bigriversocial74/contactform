<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_manual_operations.php';

const MG_STORE_ANALYTICS_EVENT_TYPES = [
    'store_entered',
    'store_returned',
    'store_exited',
    'product_viewed',
    'message_sent',
    'reward_issued',
    'reward_viewed',
    'reward_claimed',
    'reward_redeemed',
    'gift_sent',
    'customer_activity',
];

function mg_store_analytics_require_schema(PDO $pdo): void
{
    mg_store_canvas_require_tables(
        $pdo,
        ['mg_store_sessions', 'mg_store_session_events', 'mg_customer_store_history', 'mg_merchant_canvas_journey_events'],
        'Merchant Canvas customer analytics'
    );
}

function mg_store_analytics_json(mixed $value): array
{
    if (is_array($value)) return $value;
    $raw = trim((string)$value);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_store_analytics_datetime(mixed $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') return gmdate('Y-m-d H:i:s');
    $timestamp = strtotime($raw);
    return $timestamp === false ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_store_analytics_public_metadata(array $metadata): array
{
    $allowed = [
        'reason', 'status', 'source_label', 'source_channel', 'campaign_id', 'campaign_title',
        'reward_template_id', 'reward_template_title', 'wallet_item_id', 'product_id', 'product_title',
        'post_id', 'post_headline', 'thread_id', 'message_id', 'duration_seconds', 'exit_reason',
    ];
    $public = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') continue;
        if (is_scalar($metadata[$key])) $public[$key] = mb_substr((string)$metadata[$key], 0, 500);
    }
    return $public;
}

function mg_store_analytics_record(PDO $pdo, array $event): void
{
    mg_store_analytics_require_schema($pdo);
    $merchantUserId = (int)($event['merchant_user_id'] ?? 0);
    $customerUserId = (int)($event['customer_user_id'] ?? 0);
    $eventKey = trim((string)($event['event_key'] ?? ''));
    $eventType = strtolower(trim((string)($event['event_type'] ?? 'customer_activity')));
    if ($merchantUserId < 1 || $customerUserId < 1) throw new InvalidArgumentException('A valid merchant/customer analytics pair is required.');
    if ($eventKey === '' || mb_strlen($eventKey) > 190 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]+$/', $eventKey) !== 1) {
        throw new InvalidArgumentException('Invalid customer journey event key.');
    }
    if (!in_array($eventType, MG_STORE_ANALYTICS_EVENT_TYPES, true)) $eventType = 'customer_activity';

    $sourceKind = strtolower(trim((string)($event['source_kind'] ?? 'store_session')));
    if (preg_match('/^[a-z0-9_]{2,48}$/', $sourceKind) !== 1) $sourceKind = 'store_session';
    $sourcePublicId = trim((string)($event['source_public_id'] ?? ''));
    if ($sourcePublicId === '' || mb_strlen($sourcePublicId) > 190 || preg_match('/[[:cntrl:]]/', $sourcePublicId) === 1) $sourcePublicId = null;
    $label = trim((string)($event['event_label'] ?? ''));
    if ($label === '') $label = ucwords(str_replace('_', ' ', $eventType));
    $metadata = mg_store_analytics_public_metadata(mg_store_analytics_json($event['metadata'] ?? []));

    $stmt = $pdo->prepare(
        'INSERT INTO mg_merchant_canvas_journey_events
         (public_id,event_key,merchant_user_id,customer_user_id,store_session_id,event_type,event_label,source_kind,source_public_id,event_at,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
           store_session_id=VALUES(store_session_id),event_type=VALUES(event_type),event_label=VALUES(event_label),
           source_kind=VALUES(source_kind),source_public_id=VALUES(source_public_id),event_at=VALUES(event_at),
           metadata_json=VALUES(metadata_json),updated_at=NOW()'
    );
    $stmt->execute([
        mg_public_uuid(),
        $eventKey,
        $merchantUserId,
        $customerUserId,
        isset($event['store_session_id']) && (int)$event['store_session_id'] > 0 ? (int)$event['store_session_id'] : null,
        $eventType,
        mb_substr($label, 0, 180),
        $sourceKind,
        $sourcePublicId,
        mg_store_analytics_datetime($event['event_at'] ?? null),
        $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
    ]);
}

function mg_store_analytics_session_context(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    $session = mg_store_manual_ops_session($pdo, $merchantUserId, $sessionPublicId, false);
    $customerUserId = (int)($session['customer_user_id'] ?? 0);
    if ($customerUserId < 1) throw new RuntimeException('Real customer analytics are unavailable for this Store Canvas session.');
    return ['session' => $session, 'customer_user_id' => $customerUserId];
}

function mg_store_analytics_normalize_store_event(string $type): ?string
{
    return match ($type) {
        'entered_store', 'exited_store', 'message_received', 'reward_sent', 'received_reward' => null,
        'store_session_resumed' => 'store_returned',
        'viewed_product', 'product_viewed' => 'product_viewed',
        'claimed_reward', 'reward_claimed' => 'reward_claimed',
        'reward_viewed' => 'reward_viewed',
        'reward_redeemed' => 'reward_redeemed',
        'sent_gift', 'gift_sent' => 'gift_sent',
        default => 'customer_activity',
    };
}

function mg_store_analytics_session_db_id(PDO $pdo, int $merchantUserId, int $customerUserId, string $sessionPublicId): ?int
{
    if ($sessionPublicId === '') return null;
    $stmt = $pdo->prepare('SELECT id FROM mg_store_sessions WHERE public_id=? AND merchant_user_id=? AND customer_user_id=? LIMIT 1');
    $stmt->execute([$sessionPublicId, $merchantUserId, $customerUserId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
}

function mg_store_analytics_sync_customer(PDO $pdo, int $merchantUserId, int $customerUserId): void
{
    mg_store_analytics_require_schema($pdo);
    $hasFeedPosts = mg_store_canvas_table_exists($pdo, 'feed_posts');
    $hasCampaigns = mg_store_canvas_table_exists($pdo, 'campaigns');

    $sessionSql = 'SELECT s.*';
    $sessionSql .= $hasFeedPosts ? ',fp.public_id source_post_public_id,fp.headline source_post_headline' : ',NULL source_post_public_id,NULL source_post_headline';
    $sessionSql .= $hasCampaigns ? ',c.public_id source_campaign_public_id,c.title source_campaign_title' : ',NULL source_campaign_public_id,NULL source_campaign_title';
    $sessionSql .= ' FROM mg_store_sessions s';
    if ($hasFeedPosts) $sessionSql .= ' LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id';
    if ($hasCampaigns) $sessionSql .= ' LEFT JOIN campaigns c ON c.id=s.source_campaign_id AND c.merchant_user_id=s.merchant_user_id';
    $sessionSql .= ' WHERE s.merchant_user_id=? AND s.customer_user_id=? ORDER BY s.id ASC';
    $sessionsStmt = $pdo->prepare($sessionSql);
    $sessionsStmt->execute([$merchantUserId, $customerUserId]);
    foreach ($sessionsStmt->fetchAll(PDO::FETCH_ASSOC) as $session) {
        $sourceId = trim((string)($session['source_post_public_id'] ?? $session['source_campaign_public_id'] ?? ''));
        $sourceLabel = trim((string)($session['source_post_headline'] ?? $session['source_campaign_title'] ?? ''));
        mg_store_analytics_record($pdo, [
            'event_key' => 'session:' . (string)$session['public_id'] . ':entered',
            'merchant_user_id' => $merchantUserId,
            'customer_user_id' => $customerUserId,
            'store_session_id' => (int)$session['id'],
            'event_type' => 'store_entered',
            'event_label' => $sourceLabel !== '' ? 'Entered from ' . $sourceLabel : 'Entered merchant store',
            'source_kind' => $session['source_feed_post_id'] !== null ? 'feed_post' : ($session['source_campaign_id'] !== null ? 'campaign' : 'store_session'),
            'source_public_id' => $sourceId !== '' ? $sourceId : (string)$session['public_id'],
            'event_at' => (string)$session['entered_at'],
            'metadata' => [
                'post_id' => $session['source_post_public_id'] ?? null,
                'post_headline' => $session['source_post_headline'] ?? null,
                'campaign_id' => $session['source_campaign_public_id'] ?? null,
                'campaign_title' => $session['source_campaign_title'] ?? null,
                'source_label' => $sourceLabel,
            ],
        ]);
        if (!empty($session['exited_at'])) {
            mg_store_analytics_record($pdo, [
                'event_key' => 'session:' . (string)$session['public_id'] . ':exited',
                'merchant_user_id' => $merchantUserId,
                'customer_user_id' => $customerUserId,
                'store_session_id' => (int)$session['id'],
                'event_type' => 'store_exited',
                'event_label' => 'Exited merchant store',
                'source_kind' => 'store_session',
                'source_public_id' => (string)$session['public_id'],
                'event_at' => (string)$session['exited_at'],
                'metadata' => ['reason' => $session['exit_reason'] ?? null, 'exit_reason' => $session['exit_reason'] ?? null, 'status' => $session['status'] ?? null],
            ]);
        }
    }

    $eventsStmt = $pdo->prepare(
        'SELECT e.public_id,e.store_session_id,e.event_type,e.event_label,e.event_data_json,e.created_at
         FROM mg_store_session_events e
         WHERE e.merchant_user_id=? AND e.customer_user_id=? ORDER BY e.id ASC'
    );
    $eventsStmt->execute([$merchantUserId, $customerUserId]);
    foreach ($eventsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eventType = mg_store_analytics_normalize_store_event(strtolower((string)$row['event_type']));
        if ($eventType === null) continue;
        mg_store_analytics_record($pdo, [
            'event_key' => 'store-event:' . (string)$row['public_id'],
            'merchant_user_id' => $merchantUserId,
            'customer_user_id' => $customerUserId,
            'store_session_id' => (int)$row['store_session_id'],
            'event_type' => $eventType,
            'event_label' => $row['event_label'] !== null ? (string)$row['event_label'] : null,
            'source_kind' => 'store_event',
            'source_public_id' => (string)$row['public_id'],
            'event_at' => (string)$row['created_at'],
            'metadata' => mg_store_analytics_json($row['event_data_json'] ?? null),
        ]);
    }

    if (mg_store_canvas_table_exists($pdo, 'messages') && mg_store_canvas_table_exists($pdo, 'message_threads')) {
        $messageStmt = $pdo->prepare(
            "SELECT m.public_id,m.source_type,m.source_reference,m.created_at,mt.public_id thread_public_id
             FROM messages m INNER JOIN message_threads mt ON mt.id=m.thread_id
             WHERE m.sender_user_id=? AND m.recipient_user_id=?
               AND (m.source_type='store_canvas_direct' OR m.source_reference LIKE 'store_session:%')
             ORDER BY m.id ASC"
        );
        $messageStmt->execute([$merchantUserId, $customerUserId]);
        foreach ($messageStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sessionPublicId = str_starts_with((string)$row['source_reference'], 'store_session:') ? substr((string)$row['source_reference'], 14) : '';
            mg_store_analytics_record($pdo, [
                'event_key' => 'message:' . (string)$row['public_id'],
                'merchant_user_id' => $merchantUserId,
                'customer_user_id' => $customerUserId,
                'store_session_id' => mg_store_analytics_session_db_id($pdo, $merchantUserId, $customerUserId, $sessionPublicId),
                'event_type' => 'message_sent',
                'event_label' => 'Merchant sent a direct message',
                'source_kind' => 'message',
                'source_public_id' => (string)$row['public_id'],
                'event_at' => (string)$row['created_at'],
                'metadata' => ['message_id' => (string)$row['public_id'], 'thread_id' => (string)$row['thread_public_id'], 'source_channel' => (string)$row['source_type']],
            ]);
        }
    }

    if (mg_store_canvas_table_exists($pdo, 'wallet_items')) {
        $hasTemplates = mg_store_canvas_table_exists($pdo, 'reward_templates');
        $rewardSql = 'SELECT wi.public_id,wi.status,wi.title_snapshot,wi.value_cents_snapshot,wi.currency_snapshot,wi.issued_at,wi.viewed_at,wi.claimed_at,wi.redeemed_at,wi.metadata_json';
        $rewardSql .= $hasCampaigns ? ',c.public_id campaign_public_id,c.title campaign_title' : ',NULL campaign_public_id,NULL campaign_title';
        $rewardSql .= $hasTemplates ? ',rt.public_id reward_template_public_id,rt.title reward_template_title' : ',NULL reward_template_public_id,NULL reward_template_title';
        $rewardSql .= ' FROM wallet_items wi';
        if ($hasCampaigns) $rewardSql .= ' LEFT JOIN campaigns c ON c.id=wi.campaign_id AND c.merchant_user_id=wi.merchant_user_id';
        if ($hasTemplates) $rewardSql .= ' LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id AND rt.merchant_user_id=wi.merchant_user_id';
        $rewardSql .= ' WHERE wi.merchant_user_id=? AND wi.user_id=? ORDER BY wi.id ASC';
        $rewardStmt = $pdo->prepare($rewardSql);
        $rewardStmt->execute([$merchantUserId, $customerUserId]);
        foreach ($rewardStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $metadata = mg_store_analytics_json($row['metadata_json'] ?? null);
            $sessionPublicId = trim((string)($metadata['store_session_id'] ?? ''));
            $baseMetadata = [
                'wallet_item_id' => (string)$row['public_id'],
                'campaign_id' => $row['campaign_public_id'] ?? ($metadata['campaign_id'] ?? null),
                'campaign_title' => $row['campaign_title'] ?? ($metadata['campaign_title'] ?? null),
                'reward_template_id' => $row['reward_template_public_id'] ?? ($metadata['reward_template_id'] ?? null),
                'reward_template_title' => $row['reward_template_title'] ?? ($metadata['reward_template_title'] ?? $row['title_snapshot']),
                'status' => (string)$row['status'],
            ];
            $moments = [
                'issued' => ['reward_issued', 'Reward issued', $row['issued_at']],
                'viewed' => ['reward_viewed', 'Reward viewed', $row['viewed_at']],
                'claimed' => ['reward_claimed', 'Reward claimed', $row['claimed_at']],
                'redeemed' => ['reward_redeemed', 'Reward redeemed', $row['redeemed_at']],
            ];
            foreach ($moments as $suffix => [$eventType, $label, $eventAt]) {
                if (empty($eventAt)) continue;
                mg_store_analytics_record($pdo, [
                    'event_key' => 'wallet:' . (string)$row['public_id'] . ':' . $suffix,
                    'merchant_user_id' => $merchantUserId,
                    'customer_user_id' => $customerUserId,
                    'store_session_id' => mg_store_analytics_session_db_id($pdo, $merchantUserId, $customerUserId, $sessionPublicId),
                    'event_type' => $eventType,
                    'event_label' => $label . ': ' . (string)$row['title_snapshot'],
                    'source_kind' => 'wallet_item',
                    'source_public_id' => (string)$row['public_id'],
                    'event_at' => (string)$eventAt,
                    'metadata' => $baseMetadata,
                ]);
            }
        }
    }
}

function mg_store_analytics_counts(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    $counts = array_fill_keys(MG_STORE_ANALYTICS_EVENT_TYPES, 0);
    $stmt = $pdo->prepare('SELECT event_type,COUNT(*) total FROM mg_merchant_canvas_journey_events WHERE merchant_user_id=? AND customer_user_id=? GROUP BY event_type');
    $stmt->execute([$merchantUserId, $customerUserId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string)$row['event_type'];
        if (array_key_exists($type, $counts)) $counts[$type] = (int)$row['total'];
    }
    return $counts;
}

function mg_store_analytics_summary(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    $visitStmt = $pdo->prepare(
        "SELECT COUNT(*) visit_count,
                COALESCE(SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,entered_at,COALESCE(exited_at,IF(active_key IS NOT NULL,NOW(),last_active_at))))),0) total_session_seconds,
                COALESCE(ROUND(AVG(GREATEST(0,TIMESTAMPDIFF(SECOND,entered_at,COALESCE(exited_at,IF(active_key IS NOT NULL,NOW(),last_active_at)))))),0) average_session_seconds,
                MIN(entered_at) first_visit_at,MAX(entered_at) last_visit_at,MAX(last_active_at) last_active_at
         FROM mg_store_sessions WHERE merchant_user_id=? AND customer_user_id=?"
    );
    $visitStmt->execute([$merchantUserId, $customerUserId]);
    $visits = $visitStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $counts = mg_store_analytics_counts($pdo, $merchantUserId, $customerUserId);
    $issued = (int)($counts['reward_issued'] ?? 0);
    $claimed = (int)($counts['reward_claimed'] ?? 0);
    $redeemed = (int)($counts['reward_redeemed'] ?? 0);
    return [
        'visit_count' => (int)($visits['visit_count'] ?? 0),
        'return_visit_count' => max(0, (int)($visits['visit_count'] ?? 0) - 1),
        'total_session_seconds' => (int)($visits['total_session_seconds'] ?? 0),
        'average_session_seconds' => (int)($visits['average_session_seconds'] ?? 0),
        'first_visit_at' => $visits['first_visit_at'] !== null ? (string)$visits['first_visit_at'] : null,
        'last_visit_at' => $visits['last_visit_at'] !== null ? (string)$visits['last_visit_at'] : null,
        'last_active_at' => $visits['last_active_at'] !== null ? (string)$visits['last_active_at'] : null,
        'messages_sent' => (int)($counts['message_sent'] ?? 0),
        'products_viewed' => (int)($counts['product_viewed'] ?? 0),
        'rewards_issued' => $issued,
        'rewards_viewed' => (int)($counts['reward_viewed'] ?? 0),
        'rewards_claimed' => $claimed,
        'rewards_redeemed' => $redeemed,
        'reward_claim_rate' => $issued > 0 ? round(($claimed / $issued) * 100, 1) : 0.0,
        'reward_redemption_rate' => $issued > 0 ? round(($redeemed / $issued) * 100, 1) : 0.0,
        'gifts_sent' => (int)($counts['gift_sent'] ?? 0),
    ];
}

function mg_store_analytics_attribution(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    $hasFeedPosts = mg_store_canvas_table_exists($pdo, 'feed_posts');
    $hasCampaigns = mg_store_canvas_table_exists($pdo, 'campaigns');
    $sql = 'SELECT s.public_id session_public_id,s.entered_at';
    $sql .= $hasFeedPosts ? ',fp.public_id post_public_id,fp.headline post_headline' : ',NULL post_public_id,NULL post_headline';
    $sql .= $hasCampaigns ? ',c.public_id campaign_public_id,c.title campaign_title,c.campaign_type' : ',NULL campaign_public_id,NULL campaign_title,NULL campaign_type';
    $sql .= ' FROM mg_store_sessions s';
    if ($hasFeedPosts) $sql .= ' LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id';
    if ($hasCampaigns) $sql .= ' LEFT JOIN campaigns c ON c.id=s.source_campaign_id AND c.merchant_user_id=s.merchant_user_id';
    $sql .= ' WHERE s.merchant_user_id=? AND s.customer_user_id=? ORDER BY s.entered_at DESC,s.id DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $customerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'session_id' => (string)($row['session_public_id'] ?? ''),
        'entered_at' => $row['entered_at'] ?? null,
        'post' => !empty($row['post_public_id']) ? ['id' => (string)$row['post_public_id'], 'headline' => (string)($row['post_headline'] ?? 'Merchant feed post')] : null,
        'campaign' => !empty($row['campaign_public_id']) ? ['id' => (string)$row['campaign_public_id'], 'title' => (string)($row['campaign_title'] ?? 'Campaign'), 'type' => (string)($row['campaign_type'] ?? '')] : null,
    ];
}

function mg_store_analytics_segments(array $summary, array $crm): array
{
    $tags = array_values(array_filter((array)($crm['tags'] ?? []), 'is_string'));
    $segments = [];
    $add = static function (string $key, string $label, string $reason) use (&$segments): void {
        $segments[] = ['key' => $key, 'label' => $label, 'reason' => $reason];
    };
    if ((int)$summary['visit_count'] <= 1) $add('new_visitor', 'New visitor', 'First recorded merchant visit.');
    if ((int)$summary['visit_count'] > 1) $add('returning_visitor', 'Returning visitor', 'Multiple recorded merchant visits.');
    if ((int)$summary['total_session_seconds'] >= 300 || (int)$summary['products_viewed'] >= 2 || (int)$summary['messages_sent'] >= 2) $add('engaged', 'Engaged', 'Meaningful time or repeated merchant interaction.');
    if ((int)$summary['rewards_issued'] > 0) $add('reward_recipient', 'Reward recipient', 'At least one merchant reward was issued.');
    if ((int)$summary['rewards_claimed'] > 0) $add('reward_claimant', 'Reward claimant', 'At least one merchant reward was claimed.');
    if (in_array('vip', $tags, true)) $add('vip', 'VIP', 'Merchant-assigned VIP tag.');
    if (in_array('high_intent', $tags, true) || (int)$summary['products_viewed'] >= 2 || (int)$summary['rewards_claimed'] > 0) $add('high_intent', 'High intent', 'Merchant tag or strong product/reward intent signals.');
    if (in_array('needs_follow_up', $tags, true) || ((int)$summary['rewards_issued'] > 0 && (int)$summary['rewards_claimed'] === 0)) $add('needs_follow_up', 'Needs follow-up', 'Merchant tag or an issued reward has not been claimed.');
    if (!empty($crm['do_not_message'])) $add('do_not_message', 'Do Not Message', 'Server-enforced communication block is active.');
    return $segments;
}

function mg_store_analytics_journey(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    $stmt = $pdo->prepare('SELECT event_type,event_label,source_kind,source_public_id,event_at,metadata_json FROM mg_merchant_canvas_journey_events WHERE merchant_user_id=? AND customer_user_id=? ORDER BY event_at DESC,id DESC LIMIT 100');
    $stmt->execute([$merchantUserId, $customerUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'type' => (string)$row['event_type'],
            'label' => (string)($row['event_label'] ?? ''),
            'source_kind' => (string)$row['source_kind'],
            'source_id' => $row['source_public_id'] !== null ? (string)$row['source_public_id'] : null,
            'event_at' => (string)$row['event_at'],
            'metadata' => mg_store_analytics_public_metadata(mg_store_analytics_json($row['metadata_json'] ?? null)),
        ];
    }
    return $items;
}

function mg_store_analytics_visits(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    $hasFeedPosts = mg_store_canvas_table_exists($pdo, 'feed_posts');
    $hasCampaigns = mg_store_canvas_table_exists($pdo, 'campaigns');
    $sql = "SELECT s.public_id,s.status,s.entered_at,s.last_active_at,s.exited_at,s.exit_reason,GREATEST(0,TIMESTAMPDIFF(SECOND,s.entered_at,COALESCE(s.exited_at,IF(s.active_key IS NOT NULL,NOW(),s.last_active_at)))) duration_seconds";
    $sql .= $hasFeedPosts ? ',fp.public_id post_public_id,fp.headline post_headline' : ',NULL post_public_id,NULL post_headline';
    $sql .= $hasCampaigns ? ',c.public_id campaign_public_id,c.title campaign_title' : ',NULL campaign_public_id,NULL campaign_title';
    $sql .= ' FROM mg_store_sessions s';
    if ($hasFeedPosts) $sql .= ' LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id';
    if ($hasCampaigns) $sql .= ' LEFT JOIN campaigns c ON c.id=s.source_campaign_id AND c.merchant_user_id=s.merchant_user_id';
    $sql .= ' WHERE s.merchant_user_id=? AND s.customer_user_id=? ORDER BY s.entered_at DESC,s.id DESC LIMIT 25';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $customerUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'session_id' => (string)$row['public_id'],
            'status' => (string)$row['status'],
            'entered_at' => (string)$row['entered_at'],
            'last_active_at' => (string)$row['last_active_at'],
            'exited_at' => $row['exited_at'] !== null ? (string)$row['exited_at'] : null,
            'exit_reason' => $row['exit_reason'] !== null ? (string)$row['exit_reason'] : null,
            'duration_seconds' => (int)$row['duration_seconds'],
            'source' => !empty($row['post_public_id']) ? ['type' => 'feed_post', 'id' => (string)$row['post_public_id'], 'label' => (string)($row['post_headline'] ?? 'Merchant feed post')] : (!empty($row['campaign_public_id']) ? ['type' => 'campaign', 'id' => (string)$row['campaign_public_id'], 'label' => (string)($row['campaign_title'] ?? 'Campaign')] : null),
        ];
    }
    return $items;
}

function mg_store_analytics_messages(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    if (!mg_store_canvas_table_exists($pdo, 'messages') || !mg_store_canvas_table_exists($pdo, 'message_threads')) return [];
    $stmt = $pdo->prepare("SELECT m.public_id,m.body,m.source_type,m.created_at,mt.public_id thread_public_id FROM messages m INNER JOIN message_threads mt ON mt.id=m.thread_id WHERE m.sender_user_id=? AND m.recipient_user_id=? AND (m.source_type='store_canvas_direct' OR m.source_reference LIKE 'store_session:%') ORDER BY m.created_at DESC,m.id DESC LIMIT 25");
    $stmt->execute([$merchantUserId, $customerUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id' => (string)$row['public_id'],
            'thread_id' => (string)$row['thread_public_id'],
            'body_preview' => mb_substr(trim((string)$row['body']), 0, 240),
            'source_type' => (string)$row['source_type'],
            'status' => 'sent',
            'created_at' => (string)$row['created_at'],
        ];
    }
    return $items;
}

function mg_store_analytics_rewards(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    if (!mg_store_canvas_table_exists($pdo, 'wallet_items')) return [];
    $hasCampaigns = mg_store_canvas_table_exists($pdo, 'campaigns');
    $hasTemplates = mg_store_canvas_table_exists($pdo, 'reward_templates');
    $sql = 'SELECT wi.public_id,wi.status,wi.title_snapshot,wi.value_cents_snapshot,wi.currency_snapshot,wi.issued_at,wi.viewed_at,wi.claimed_at,wi.redeemed_at,wi.expires_at';
    $sql .= $hasCampaigns ? ',c.public_id campaign_public_id,c.title campaign_title' : ',NULL campaign_public_id,NULL campaign_title';
    $sql .= $hasTemplates ? ',rt.public_id template_public_id,rt.title template_title' : ',NULL template_public_id,NULL template_title';
    $sql .= ' FROM wallet_items wi';
    if ($hasCampaigns) $sql .= ' LEFT JOIN campaigns c ON c.id=wi.campaign_id AND c.merchant_user_id=wi.merchant_user_id';
    if ($hasTemplates) $sql .= ' LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id AND rt.merchant_user_id=wi.merchant_user_id';
    $sql .= ' WHERE wi.merchant_user_id=? AND wi.user_id=? ORDER BY wi.issued_at DESC,wi.id DESC LIMIT 25';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $customerUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title_snapshot'],
            'status' => (string)$row['status'],
            'value_cents' => (int)$row['value_cents_snapshot'],
            'currency' => (string)$row['currency_snapshot'],
            'issued_at' => (string)$row['issued_at'],
            'viewed_at' => $row['viewed_at'] !== null ? (string)$row['viewed_at'] : null,
            'claimed_at' => $row['claimed_at'] !== null ? (string)$row['claimed_at'] : null,
            'redeemed_at' => $row['redeemed_at'] !== null ? (string)$row['redeemed_at'] : null,
            'expires_at' => $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
            'campaign' => !empty($row['campaign_public_id']) ? ['id' => (string)$row['campaign_public_id'], 'title' => (string)($row['campaign_title'] ?? 'Campaign')] : null,
            'template' => !empty($row['template_public_id']) ? ['id' => (string)$row['template_public_id'], 'title' => (string)($row['template_title'] ?? $row['title_snapshot'])] : null,
        ];
    }
    return $items;
}

function mg_store_analytics_customer_payload(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    $context = mg_store_analytics_session_context($pdo, $merchantUserId, $sessionPublicId);
    $customerUserId = (int)$context['customer_user_id'];
    mg_store_analytics_sync_customer($pdo, $merchantUserId, $customerUserId);
    $crm = mg_store_manual_ops_crm_get($pdo, $merchantUserId, $customerUserId, false);
    $summary = mg_store_analytics_summary($pdo, $merchantUserId, $customerUserId);
    return [
        'schema_ready' => true,
        'analytics' => $summary,
        'segments' => mg_store_analytics_segments($summary, $crm),
        'attribution' => mg_store_analytics_attribution($pdo, $merchantUserId, $customerUserId),
        'journey' => mg_store_analytics_journey($pdo, $merchantUserId, $customerUserId),
        'visits' => mg_store_analytics_visits($pdo, $merchantUserId, $customerUserId),
        'messages' => mg_store_analytics_messages($pdo, $merchantUserId, $customerUserId),
        'rewards' => mg_store_analytics_rewards($pdo, $merchantUserId, $customerUserId),
        'generated_at' => gmdate('c'),
    ];
}
