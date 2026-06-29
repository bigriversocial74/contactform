<?php
/**
 * World Canvas selected-node detail endpoint.
 */
declare(strict_types=1);

require_once __DIR__ . '/_world.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

function mg_world_canvas_safe_detail_type(mixed $value): string
{
    $type = strtolower(trim((string)$value));
    return in_array($type, ['merchant', 'avatar', 'campaign', 'reward', 'claim', 'session'], true) ? $type : '';
}

function mg_world_canvas_safe_detail_id(mixed $value): string
{
    $id = trim((string)$value);
    if ($id === '' || strlen($id) > 120 || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1) {
        throw new InvalidArgumentException('World Canvas node is required.');
    }
    return $id;
}

function mg_world_canvas_merchant_detail(PDO $pdo, string $profilePublicId, array $viewer): array
{
    $stmt = $pdo->prepare('SELECT pp.public_id, pp.user_id, pp.display_name, pp.avatar_url, pp.slug, pp.profile_type, ms.slug store_slug, ms.status store_status FROM public_profiles pp LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=pp.user_id WHERE pp.public_id=? LIMIT 1');
    $stmt->execute([$profilePublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Merchant node is no longer available.');

    $merchantUserId = (int)$row['user_id'];
    $viewerUserId = (int)($viewer['id'] ?? 0);
    $liveSessions = mg_world_canvas_store_ready($pdo) ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_sessions WHERE merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL", [$merchantUserId]) : 0;
    $eventsToday = mg_world_canvas_store_ready($pdo) ? mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND created_at >= CURDATE()', [$merchantUserId]) : 0;
    $claimsToday = mg_world_canvas_store_ready($pdo) ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND created_at >= CURDATE() AND event_type LIKE '%claim%'", [$merchantUserId]) : 0;
    $campaigns = mg_world_canvas_table($pdo, 'campaigns') ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())", [$merchantUserId]) : 0;
    $rewards = mg_world_canvas_table($pdo, 'wallet_items') ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND issued_at >= CURDATE() AND status <> 'cancelled'", [$merchantUserId]) : 0;

    return [
        'type' => 'merchant',
        'title' => trim((string)($row['display_name'] ?? '')) ?: 'Merchant Store',
        'subtitle' => 'Public merchant node',
        'avatar_url' => mg_store_avatar_url($row['avatar_url'] ?? null),
        'stats' => [
            ['label' => 'Live avatars', 'value' => $liveSessions],
            ['label' => 'Events today', 'value' => $eventsToday],
            ['label' => 'Claims today', 'value' => $claimsToday],
            ['label' => 'Active campaigns', 'value' => $campaigns],
            ['label' => 'Rewards today', 'value' => $rewards],
        ],
        'actions' => [
            ['label' => 'View storefront', 'href' => trim((string)($row['store_slug'] ?? '')) !== '' ? '/store.php?s=' . rawurlencode((string)$row['store_slug']) : ''],
            ['label' => 'View profile', 'href' => trim((string)($row['slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$row['slug']) : ''],
            ['label' => 'Open Store Canvas', 'href' => $merchantUserId === $viewerUserId ? '/merchant-canvas.php' : ''],
        ],
        'note' => $merchantUserId === $viewerUserId ? 'This is your merchant node. Store Canvas opens the private customer session view.' : 'Customer identities and CRM context stay private to the merchant-owned Store Canvas.',
    ];
}

function mg_world_canvas_avatar_detail(PDO $pdo, string $sessionPublicId, array $viewer): array
{
    if (!mg_world_canvas_store_ready($pdo)) throw new RuntimeException('Store Canvas data is not available.');
    $lat = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_latitude') ? 's.avatar_latitude' : 'NULL';
    $lng = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_longitude') ? 's.avatar_longitude' : 'NULL';
    $accuracy = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_accuracy_meters') ? 's.avatar_geo_accuracy_meters' : 'NULL';
    $sourceCol = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_source') ? 's.avatar_geo_source' : 'NULL';
    $stmt = $pdo->prepare("SELECT s.public_id, s.customer_user_id, s.merchant_user_id, s.status, s.entered_at, s.last_active_at, s.metadata_json, {$lat} avatar_latitude, {$lng} avatar_longitude, {$accuracy} avatar_geo_accuracy_meters, {$sourceCol} avatar_geo_source, pp.display_name merchant_name, fp.headline source_headline, TIMESTAMPDIFF(SECOND, s.entered_at, NOW()) seconds_inside, (SELECT event_type FROM mg_store_session_events e WHERE e.store_session_id=s.id ORDER BY e.id DESC LIMIT 1) last_event_type, (SELECT event_label FROM mg_store_session_events e WHERE e.store_session_id=s.id ORDER BY e.id DESC LIMIT 1) last_event_label FROM mg_store_sessions s LEFT JOIN public_profiles pp ON pp.user_id=s.merchant_user_id LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id WHERE s.public_id=? LIMIT 1");
    $stmt->execute([$sessionPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Avatar node is no longer available.');
    $viewerUserId = (int)($viewer['id'] ?? 0);
    $owned = (int)($row['customer_user_id'] ?? 0) === $viewerUserId || (int)($row['merchant_user_id'] ?? 0) === $viewerUserId;
    $geo = mg_world_canvas_geo_from_session($row);
    $eventType = (string)($row['last_event_type'] ?? 'entered_store');
    $eventLabel = trim((string)($row['last_event_label'] ?? '')) ?: ucwords(str_replace('_', ' ', $eventType));

    return [
        'type' => 'avatar',
        'title' => $owned ? 'Your live avatar' : 'Anonymous world avatar',
        'subtitle' => $eventLabel . ' · ' . (trim((string)($row['merchant_name'] ?? '')) ?: 'Merchant location'),
        'stats' => [
            ['label' => 'Status', 'value' => (string)($row['status'] ?? 'active')],
            ['label' => 'Seconds inside', 'value' => (int)($row['seconds_inside'] ?? 0)],
            ['label' => 'Source', 'value' => trim((string)($row['source_headline'] ?? '')) ?: 'Store Canvas'],
            ['label' => 'Geo anchor', 'value' => $geo !== null ? 'Lat/long saved' : 'Affinity placed'],
            ['label' => 'Accuracy', 'value' => $geo && $geo['accuracy_meters'] !== null ? $geo['accuracy_meters'] . 'm' : '—'],
        ],
        'actions' => [],
        'note' => $geo !== null ? 'This avatar is anchored from saved latitude/longitude, then lightly attracted toward similar avatars and same-location conversations.' : 'This avatar has no saved coordinates yet, so World Canvas places it by location, conversation, and affinity tags.',
    ];
}

function mg_world_canvas_campaign_detail(PDO $pdo, string $campaignPublicId): array
{
    if (!mg_world_canvas_table($pdo, 'campaigns')) throw new RuntimeException('Campaign data is not available.');
    $hasTemplates = mg_world_canvas_table($pdo, 'reward_templates');
    $stmt = $pdo->prepare("SELECT c.*, pp.display_name merchant_name" . ($hasTemplates ? ', rt.title reward_template_title' : ', NULL reward_template_title') . " FROM campaigns c LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id " . ($hasTemplates ? 'LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id ' : '') . "WHERE c.public_id=? LIMIT 1");
    $stmt->execute([$campaignPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Campaign node is no longer available.');
    $campaignId = (int)($row['id'] ?? 0);
    $events = mg_world_canvas_table($pdo, 'campaign_events') ? mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM campaign_events WHERE campaign_id=?', [$campaignId]) : 0;
    $rewards = mg_world_canvas_table($pdo, 'wallet_items') ? mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM wallet_items WHERE campaign_id=? AND status <> \'cancelled\'', [$campaignId]) : (int)($row['issued_count'] ?? 0);
    $limit = $row['quantity_limit'] === null ? 'No cap' : number_format((int)$row['quantity_limit']);
    return ['type' => 'campaign', 'title' => (string)($row['title'] ?? 'Campaign'), 'subtitle' => trim((string)($row['merchant_name'] ?? '')) ?: 'Microgifter campaign', 'stats' => [['label' => 'Type', 'value' => (string)($row['campaign_type'] ?? 'campaign')], ['label' => 'Issued', 'value' => $rewards], ['label' => 'Limit', 'value' => $limit], ['label' => 'Events', 'value' => $events], ['label' => 'Reward', 'value' => trim((string)($row['reward_template_title'] ?? '')) ?: 'Reward template']], 'actions' => [], 'note' => 'Campaign details are shown as aggregate world activity. Merchant-only campaign controls stay inside merchant tools.'];
}

function mg_world_canvas_signal_detail(PDO $pdo, string $type, string $id, array $viewer): array
{
    if ($type === 'reward' && mg_world_canvas_table($pdo, 'wallet_items')) {
        $stmt = $pdo->prepare('SELECT wi.public_id, wi.title_snapshot, wi.status, wi.issued_at, wi.user_id, wi.merchant_user_id, pp.display_name merchant_name, c.title campaign_title FROM wallet_items wi LEFT JOIN public_profiles pp ON pp.user_id=wi.merchant_user_id LEFT JOIN campaigns c ON c.id=wi.campaign_id WHERE wi.public_id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $viewerUserId = (int)($viewer['id'] ?? 0);
            $owned = ((int)($row['user_id'] ?? 0) === $viewerUserId || (int)($row['merchant_user_id'] ?? 0) === $viewerUserId);
            return ['type' => 'reward', 'title' => trim((string)($row['title_snapshot'] ?? '')) ?: 'Reward movement', 'subtitle' => 'Reward signal · ' . (trim((string)($row['merchant_name'] ?? '')) ?: 'Microgifter merchant'), 'stats' => [['label' => 'Status', 'value' => (string)($row['status'] ?? 'issued')], ['label' => 'Campaign', 'value' => trim((string)($row['campaign_title'] ?? '')) ?: 'Not attached'], ['label' => 'Issued', 'value' => (string)($row['issued_at'] ?? '')]], 'actions' => [], 'note' => $owned ? 'This reward is connected to your account or merchant workspace.' : 'This public world signal hides recipient details unless you own the relationship.'];
        }
    }
    if (($type === 'claim' || $type === 'session') && mg_world_canvas_store_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT e.public_id, e.event_type, e.event_label, e.created_at, pp.display_name merchant_name, fp.headline source_headline FROM mg_store_session_events e INNER JOIN mg_store_sessions s ON s.id=e.store_session_id LEFT JOIN public_profiles pp ON pp.user_id=e.merchant_user_id LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id WHERE e.public_id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['type' => $type, 'title' => trim((string)($row['event_label'] ?? '')) ?: ucwords(str_replace('_', ' ', (string)($row['event_type'] ?? 'Canvas signal'))), 'subtitle' => trim((string)($row['merchant_name'] ?? '')) ?: 'Store Canvas event', 'stats' => [['label' => 'Signal', 'value' => (string)($row['event_type'] ?? 'event')], ['label' => 'Source', 'value' => trim((string)($row['source_headline'] ?? '')) ?: 'World Canvas'], ['label' => 'Time', 'value' => (string)($row['created_at'] ?? '')]], 'actions' => [], 'note' => 'Customer identity is anonymized in World Canvas. Open Merchant Store Canvas for owned CRM context.'];
        }
    }
    throw new RuntimeException('World Canvas node is no longer available.');
}

try {
    mg_rate_limit('world_canvas.detail', 'user:' . (int)$user['id'], 240, 60);
    $type = mg_world_canvas_safe_detail_type($_GET['type'] ?? '');
    if ($type === '') throw new InvalidArgumentException('World Canvas node type is required.');
    $id = mg_world_canvas_safe_detail_id($_GET['id'] ?? '');
    if ($type === 'merchant') $detail = mg_world_canvas_merchant_detail($pdo, $id, $user);
    elseif ($type === 'avatar') $detail = mg_world_canvas_avatar_detail($pdo, $id, $user);
    elseif ($type === 'campaign') $detail = mg_world_canvas_campaign_detail($pdo, $id);
    else $detail = mg_world_canvas_signal_detail($pdo, $type, $id, $user);
    mg_ok(['detail' => $detail]);
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.detail_failed', 'World Canvas detail failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to load World Canvas detail.', 500);
}
