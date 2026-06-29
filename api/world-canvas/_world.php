<?php
/**
 * World Canvas read-model helpers.
 *
 * The world layer exposes aggregate/anonymized activity. Merchant CRM details stay
 * inside Merchant Store Canvas. Avatar nodes are anonymous world participants that
 * can visually attract to similar avatars, campaigns, merchants, and locations.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';

function mg_world_canvas_table(PDO $pdo, string $table): bool
{
    return mg_store_canvas_table_exists($pdo, $table);
}

function mg_world_canvas_store_ready(PDO $pdo): bool
{
    return mg_store_canvas_missing_tables($pdo, ['mg_store_sessions', 'mg_store_session_events', 'mg_customer_store_history']) === [];
}

function mg_world_canvas_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_world_canvas_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function mg_world_canvas_short_number(int|float $value): string
{
    $value = (float) $value;
    if ($value >= 1000000) return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . 'M';
    if ($value >= 1000) return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'K';
    return number_format($value);
}

function mg_world_canvas_slug_tag(mixed $value): string
{
    $tag = strtolower(trim((string) $value));
    $tag = preg_replace('/[^a-z0-9]+/', '-', $tag) ?? '';
    return trim($tag, '-');
}

function mg_world_canvas_tags(array $parts): array
{
    $tags = [];
    foreach ($parts as $part) {
        foreach (preg_split('/[\s,;|\/]+/', strtolower((string) $part), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $word = mg_world_canvas_slug_tag($word);
            if ($word !== '' && strlen($word) > 2 && !in_array($word, ['the','and','for','with','from','into','store','canvas','local'], true)) {
                $tags[] = $word;
            }
        }
    }
    return array_slice(array_values(array_unique($tags)), 0, 8);
}

function mg_world_canvas_position(string $seed, int $index, string $type = 'node'): array
{
    $hash = (int) sprintf('%u', crc32($seed . '|' . $type));
    $typeOffset = match ($type) {
        'merchant' => 0,
        'avatar' => 11,
        'campaign' => 17,
        'reward' => 31,
        'claim' => 43,
        default => 9,
    };
    return [
        'x' => 9 + (($hash + ($index * 19) + $typeOffset) % 78),
        'y' => 13 + (((int) floor($hash / 101) + ($index * 23) + $typeOffset) % 66),
    ];
}

function mg_world_canvas_node(string $type, string $detailId, string $title, string $subtitle, string $meta, int|string $value, array $position, array $extra = []): array
{
    $base = [
        'id' => $type . ':' . $detailId,
        'type' => $type,
        'detail_id' => $detailId,
        'title' => $title,
        'subtitle' => $subtitle,
        'meta' => $meta,
        'value' => is_int($value) ? mg_world_canvas_short_number($value) : $value,
        'raw_value' => is_int($value) ? $value : 0,
        'x' => $position['x'] ?? 50,
        'y' => $position['y'] ?? 50,
        'affinity_tags' => [],
        'location_key' => '',
        'conversation_key' => '',
    ];
    return array_merge($base, $extra);
}

function mg_world_canvas_summary(PDO $pdo): array
{
    $storeReady = mg_world_canvas_store_ready($pdo);
    $campaignReady = mg_world_canvas_table($pdo, 'campaigns') && mg_world_canvas_table($pdo, 'campaign_events');
    $walletReady = mg_world_canvas_table($pdo, 'wallet_items');

    $liveStores = $storeReady ? mg_world_canvas_count($pdo, "SELECT COUNT(DISTINCT merchant_user_id) FROM mg_store_sessions WHERE active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL") : 0;
    $activeCustomers = $storeReady ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_sessions WHERE active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL") : 0;
    $storeEventsToday = $storeReady ? mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM mg_store_session_events WHERE created_at >= CURDATE()') : 0;
    $claimsToday = $storeReady ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE created_at >= CURDATE() AND event_type LIKE '%claim%'") : 0;
    $historyRows = $storeReady ? mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM mg_customer_store_history') : 0;
    $campaignEvents = $campaignReady ? mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM campaign_events WHERE created_at >= CURDATE()') : 0;
    $giftsMoving = $walletReady
        ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM wallet_items WHERE issued_at >= CURDATE() AND status <> 'cancelled'")
        : ($storeReady ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE created_at >= CURDATE() AND (event_type LIKE '%reward%' OR event_type LIKE '%gift%')") : 0);

    $demandPulse = min(9999, ($liveStores * 12) + ($activeCustomers * 7) + ($giftsMoving * 3) + ($claimsToday * 6) + ($campaignEvents * 2) + min(250, $storeEventsToday));

    return [
        'schema_ready' => $storeReady,
        'campaign_ready' => $campaignReady,
        'wallet_ready' => $walletReady,
        'live_stores' => $liveStores,
        'active_customers' => $activeCustomers,
        'gifts_moving' => $giftsMoving,
        'claims_today' => $claimsToday,
        'campaign_events' => $campaignEvents,
        'store_events_today' => $storeEventsToday,
        'history_rows' => $historyRows,
        'demand_pulse' => $demandPulse,
    ];
}

function mg_world_canvas_merchant_nodes(PDO $pdo, int $viewerUserId): array
{
    if (!mg_world_canvas_store_ready($pdo)) return [];
    $rows = mg_world_canvas_rows($pdo, "SELECT s.merchant_user_id,
               COUNT(*) active_sessions,
               COUNT(DISTINCT s.customer_user_id) unique_customers,
               MAX(s.last_active_at) last_active_at,
               pp.public_id profile_public_id,
               pp.display_name merchant_name,
               pp.avatar_url avatar_url,
               pp.slug profile_slug,
               pp.profile_type profile_type,
               ms.slug store_slug
        FROM mg_store_sessions s
        LEFT JOIN public_profiles pp ON pp.user_id = s.merchant_user_id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id = s.merchant_user_id AND ms.status = 'published'
        WHERE s.active_key IS NOT NULL
          AND s.status IN ('entered','active','idle')
          AND s.exited_at IS NULL
        GROUP BY s.merchant_user_id, pp.public_id, pp.display_name, pp.avatar_url, pp.slug, pp.profile_type, ms.slug
        ORDER BY active_sessions DESC, last_active_at DESC
        LIMIT 24");

    $nodes = [];
    foreach ($rows as $index => $row) {
        $merchantUserId = (int) ($row['merchant_user_id'] ?? 0);
        $publicId = trim((string) ($row['profile_public_id'] ?? ''));
        if ($publicId === '') $publicId = 'merchant-' . substr(hash('sha256', (string) $merchantUserId), 0, 16);
        $title = trim((string) ($row['merchant_name'] ?? '')) ?: 'Merchant Store';
        $active = (int) ($row['active_sessions'] ?? 0);
        $campaigns = mg_world_canvas_table($pdo, 'campaigns') ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())", [$merchantUserId]) : 0;
        $events = mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND created_at >= CURDATE()', [$merchantUserId]);
        $locationKey = 'merchant:' . $merchantUserId;
        $tags = mg_world_canvas_tags([$title, $row['profile_type'] ?? '', 'merchant', 'store', 'campaign']);
        $nodes[] = mg_world_canvas_node(
            'merchant',
            $publicId,
            $title,
            $active . ' live avatar' . ($active === 1 ? '' : 's'),
            $campaigns . ' active campaign' . ($campaigns === 1 ? '' : 's') . ' · ' . $events . ' event' . ($events === 1 ? '' : 's') . ' today',
            $active,
            mg_world_canvas_position($publicId, $index, 'merchant'),
            [
                'avatar_url' => mg_store_avatar_url($row['avatar_url'] ?? null),
                'store_url' => trim((string) ($row['store_slug'] ?? '')) !== '' ? '/store.php?s=' . rawurlencode((string) $row['store_slug']) : null,
                'profile_url' => trim((string) ($row['profile_slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string) $row['profile_slug']) : null,
                'owned' => $merchantUserId === $viewerUserId,
                'tone' => $active >= 5 ? 'hot' : ($active >= 2 ? 'live' : 'soft'),
                'affinity_tags' => $tags,
                'location_key' => $locationKey,
                'conversation_key' => $locationKey,
            ]
        );
    }
    return $nodes;
}

function mg_world_canvas_avatar_nodes(PDO $pdo, int $viewerUserId): array
{
    if (!mg_world_canvas_store_ready($pdo)) return [];
    $rows = mg_world_canvas_rows($pdo, "SELECT s.public_id, s.customer_user_id, s.merchant_user_id, s.status, s.entered_at, s.last_active_at,
               pp.display_name merchant_name, fp.headline source_headline,
               TIMESTAMPDIFF(SECOND, s.entered_at, NOW()) seconds_inside,
               (SELECT event_type FROM mg_store_session_events e WHERE e.store_session_id=s.id ORDER BY e.id DESC LIMIT 1) last_event_type,
               (SELECT event_label FROM mg_store_session_events e WHERE e.store_session_id=s.id ORDER BY e.id DESC LIMIT 1) last_event_label
        FROM mg_store_sessions s
        LEFT JOIN public_profiles pp ON pp.user_id = s.merchant_user_id
        LEFT JOIN feed_posts fp ON fp.id = s.source_feed_post_id
        WHERE s.active_key IS NOT NULL
          AND s.status IN ('entered','active','idle')
          AND s.exited_at IS NULL
        ORDER BY s.last_active_at DESC, s.id DESC
        LIMIT 36");

    $labels = ['Local Explorer','Reward Browser','Gift Sender','Claim Ready','Deal Watcher','Community Shopper','Campaign Visitor','Supporter'];
    $nodes = [];
    foreach ($rows as $index => $row) {
        $publicId = (string) ($row['public_id'] ?? '');
        if ($publicId === '') continue;
        $eventType = (string) ($row['last_event_type'] ?? 'entered_store');
        $eventLabel = trim((string) ($row['last_event_label'] ?? '')) ?: ucwords(str_replace('_', ' ', $eventType));
        $source = trim((string) ($row['source_headline'] ?? ''));
        $merchant = trim((string) ($row['merchant_name'] ?? '')) ?: 'Merchant location';
        $locationKey = 'merchant:' . (int) ($row['merchant_user_id'] ?? 0);
        $conversationKey = $locationKey . ':' . mg_world_canvas_slug_tag($source !== '' ? $source : $eventType);
        $tags = mg_world_canvas_tags([$source, $eventType, $eventLabel, $merchant, (string) ($row['status'] ?? '')]);
        if ($tags === []) $tags = ['local', 'reward'];
        $label = $labels[$index % count($labels)];
        $owned = (int) ($row['customer_user_id'] ?? 0) === $viewerUserId || (int) ($row['merchant_user_id'] ?? 0) === $viewerUserId;
        $nodes[] = mg_world_canvas_node(
            'avatar',
            $publicId,
            $owned ? 'Your live avatar' : $label,
            $eventLabel . ' · ' . $merchant,
            $source !== '' ? $source : 'Same-location avatars attract into conversation clusters',
            'AV',
            mg_world_canvas_position($publicId, $index, 'avatar'),
            [
                'tone' => $owned ? 'owned' : ((string) ($row['status'] ?? '') === 'idle' ? 'soft' : 'live'),
                'owned' => $owned,
                'seconds_inside' => (int) ($row['seconds_inside'] ?? 0),
                'affinity_tags' => $tags,
                'location_key' => $locationKey,
                'conversation_key' => $conversationKey,
                'is_anonymous' => true,
            ]
        );
    }
    return $nodes;
}

function mg_world_canvas_campaign_nodes(PDO $pdo): array
{
    if (!mg_world_canvas_table($pdo, 'campaigns')) return [];
    $hasTemplates = mg_world_canvas_table($pdo, 'reward_templates');
    $rows = mg_world_canvas_rows($pdo, "SELECT c.public_id, c.title, c.campaign_type, c.issued_count, c.quantity_limit, c.ends_at, c.merchant_user_id,
               pp.display_name merchant_name" . ($hasTemplates ? ', rt.title reward_template_title' : ', NULL reward_template_title') . "
        FROM campaigns c
        LEFT JOIN public_profiles pp ON pp.user_id = c.merchant_user_id
        " . ($hasTemplates ? 'LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id' : '') . "
        WHERE c.status='active'
          AND (c.starts_at IS NULL OR c.starts_at<=NOW())
          AND (c.ends_at IS NULL OR c.ends_at>=NOW())
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 16");

    $nodes = [];
    foreach ($rows as $index => $row) {
        $publicId = (string) ($row['public_id'] ?? '');
        if ($publicId === '') continue;
        $issued = (int) ($row['issued_count'] ?? 0);
        $limit = $row['quantity_limit'] === null ? null : (int) $row['quantity_limit'];
        $subtitle = trim((string) ($row['merchant_name'] ?? '')) ?: 'Merchant campaign';
        $meta = trim((string) ($row['reward_template_title'] ?? '')) ?: (string) ($row['campaign_type'] ?? 'campaign');
        if ($limit !== null) $meta .= ' · ' . $issued . '/' . $limit . ' issued';
        else $meta .= ' · ' . $issued . ' issued';
        $locationKey = 'merchant:' . (int) ($row['merchant_user_id'] ?? 0);
        $tags = mg_world_canvas_tags([$row['title'] ?? '', $row['campaign_type'] ?? '', $row['reward_template_title'] ?? '', $subtitle]);
        $nodes[] = mg_world_canvas_node(
            'campaign',
            $publicId,
            (string) ($row['title'] ?? 'Campaign'),
            $subtitle,
            $meta,
            $issued,
            mg_world_canvas_position($publicId, $index, 'campaign'),
            [
                'tone' => $issued > 25 ? 'hot' : 'live',
                'affinity_tags' => $tags,
                'location_key' => $locationKey,
                'conversation_key' => $locationKey . ':' . mg_world_canvas_slug_tag((string) ($row['campaign_type'] ?? 'campaign')),
            ]
        );
    }
    return $nodes;
}

function mg_world_canvas_reward_nodes(PDO $pdo, int $viewerUserId): array
{
    if (!mg_world_canvas_table($pdo, 'wallet_items')) return [];
    $rows = mg_world_canvas_rows($pdo, "SELECT wi.public_id, wi.title_snapshot, wi.status, wi.issued_at, wi.user_id, wi.merchant_user_id,
               pp.display_name merchant_name, c.title campaign_title, c.campaign_type
        FROM wallet_items wi
        LEFT JOIN public_profiles pp ON pp.user_id = wi.merchant_user_id
        LEFT JOIN campaigns c ON c.id = wi.campaign_id
        WHERE wi.issued_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND wi.status <> 'cancelled'
        ORDER BY wi.issued_at DESC, wi.id DESC
        LIMIT 16");

    $nodes = [];
    foreach ($rows as $index => $row) {
        $publicId = (string) ($row['public_id'] ?? '');
        if ($publicId === '') continue;
        $title = trim((string) ($row['title_snapshot'] ?? '')) ?: 'Reward movement';
        $merchant = trim((string) ($row['merchant_name'] ?? '')) ?: 'Microgifter merchant';
        $campaign = trim((string) ($row['campaign_title'] ?? ''));
        $locationKey = 'merchant:' . (int) ($row['merchant_user_id'] ?? 0);
        $tags = mg_world_canvas_tags([$title, $merchant, $campaign, $row['campaign_type'] ?? '', $row['status'] ?? '']);
        $nodes[] = mg_world_canvas_node(
            'reward',
            $publicId,
            $title,
            'Reward issued · ' . $merchant,
            $campaign !== '' ? $campaign : 'Sent through Microgifter wallet',
            'Gift',
            mg_world_canvas_position($publicId, $index, 'reward'),
            [
                'tone' => ((int) ($row['user_id'] ?? 0) === $viewerUserId || (int) ($row['merchant_user_id'] ?? 0) === $viewerUserId) ? 'owned' : 'soft',
                'status' => (string) ($row['status'] ?? 'issued'),
                'affinity_tags' => $tags,
                'location_key' => $locationKey,
                'conversation_key' => $locationKey . ':' . mg_world_canvas_slug_tag($campaign !== '' ? $campaign : $title),
            ]
        );
    }
    return $nodes;
}

function mg_world_canvas_claim_nodes(PDO $pdo): array
{
    if (!mg_world_canvas_store_ready($pdo)) return [];
    $rows = mg_world_canvas_rows($pdo, "SELECT e.public_id, e.event_type, e.event_label, e.created_at, e.merchant_user_id,
               pp.display_name merchant_name, fp.headline source_headline
        FROM mg_store_session_events e
        INNER JOIN mg_store_sessions s ON s.id = e.store_session_id
        LEFT JOIN public_profiles pp ON pp.user_id = e.merchant_user_id
        LEFT JOIN feed_posts fp ON fp.id = s.source_feed_post_id
        WHERE e.event_type LIKE '%claim%'
          AND e.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY e.created_at DESC, e.id DESC
        LIMIT 12");

    $nodes = [];
    foreach ($rows as $index => $row) {
        $publicId = (string) ($row['public_id'] ?? '');
        if ($publicId === '') continue;
        $merchant = trim((string) ($row['merchant_name'] ?? '')) ?: 'Merchant location';
        $label = trim((string) ($row['event_label'] ?? '')) ?: 'Claim verified';
        $source = trim((string) ($row['source_headline'] ?? ''));
        $locationKey = 'merchant:' . (int) ($row['merchant_user_id'] ?? 0);
        $nodes[] = mg_world_canvas_node(
            'claim',
            $publicId,
            $label,
            'Claim activity · ' . $merchant,
            $source !== '' ? $source : 'Store Canvas claim signal',
            'Claim',
            mg_world_canvas_position($publicId, $index, 'claim'),
            [
                'tone' => 'hot',
                'affinity_tags' => mg_world_canvas_tags([$label, $source, $merchant, 'claim', 'redeem']),
                'location_key' => $locationKey,
                'conversation_key' => $locationKey . ':claim',
            ]
        );
    }
    return $nodes;
}

function mg_world_canvas_events(PDO $pdo): array
{
    $events = [];
    if (mg_world_canvas_store_ready($pdo)) {
        $rows = mg_world_canvas_rows($pdo, "SELECT e.public_id, e.event_type, e.event_label, e.created_at,
                   pp.display_name merchant_name, fp.headline source_headline
            FROM mg_store_session_events e
            INNER JOIN mg_store_sessions s ON s.id = e.store_session_id
            LEFT JOIN public_profiles pp ON pp.user_id = e.merchant_user_id
            LEFT JOIN feed_posts fp ON fp.id = s.source_feed_post_id
            WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT 40");
        foreach ($rows as $row) {
            $type = (string) ($row['event_type'] ?? 'event');
            $events[] = [
                'id' => (string) ($row['public_id'] ?? ''),
                'type' => str_contains($type, 'claim') ? 'claim' : (str_contains($type, 'reward') || str_contains($type, 'gift') ? 'reward' : 'avatar'),
                'label' => trim((string) ($row['event_label'] ?? '')) ?: ucwords(str_replace('_', ' ', $type)),
                'title' => trim((string) ($row['merchant_name'] ?? '')) ?: 'Microgifter activity',
                'meta' => trim((string) ($row['source_headline'] ?? '')) ?: 'Store Canvas signal',
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
    }

    if (mg_world_canvas_table($pdo, 'campaign_events')) {
        $rows = mg_world_canvas_rows($pdo, "SELECT ce.public_id, ce.event_type, ce.created_at, c.title campaign_title, pp.display_name merchant_name
            FROM campaign_events ce
            LEFT JOIN campaigns c ON c.id = ce.campaign_id
            LEFT JOIN public_profiles pp ON pp.user_id = ce.merchant_user_id
            WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY ce.created_at DESC, ce.id DESC
            LIMIT 20");
        foreach ($rows as $row) {
            $events[] = [
                'id' => (string) ($row['public_id'] ?? ''),
                'type' => 'campaign',
                'label' => ucwords(str_replace('_', ' ', (string) ($row['event_type'] ?? 'campaign_event'))),
                'title' => trim((string) ($row['campaign_title'] ?? '')) ?: 'Campaign event',
                'meta' => trim((string) ($row['merchant_name'] ?? '')) ?: 'Microgifter campaign',
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
    }

    usort($events, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    return array_slice($events, 0, 48);
}

function mg_world_canvas_payload(PDO $pdo, array $viewer): array
{
    $viewerUserId = (int) ($viewer['id'] ?? 0);
    $summary = mg_world_canvas_summary($pdo);
    $nodes = array_merge(
        mg_world_canvas_merchant_nodes($pdo, $viewerUserId),
        mg_world_canvas_avatar_nodes($pdo, $viewerUserId),
        mg_world_canvas_campaign_nodes($pdo),
        mg_world_canvas_reward_nodes($pdo, $viewerUserId),
        mg_world_canvas_claim_nodes($pdo)
    );

    return [
        'summary' => $summary,
        'nodes' => $nodes,
        'events' => mg_world_canvas_events($pdo),
        'attraction_model' => [
            'enabled' => true,
            'same_location_weight' => 0.46,
            'same_conversation_weight' => 0.38,
            'shared_affinity_weight' => 0.28,
            'repel_distance' => 11,
        ],
        'visibility' => [
            'customer_activity_anonymized' => true,
            'merchant_crm_private' => true,
            'avatar_to_avatar_conversation' => true,
            'similar_locations_attract' => true,
            'viewer_user_id' => $viewerUserId,
        ],
    ];
}
