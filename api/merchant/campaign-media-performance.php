<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$campaignRef = trim((string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? ''));
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 180], true)) $days = 30;
if ($campaignRef === '' || strlen($campaignRef) > 180 || !preg_match('/^[a-zA-Z0-9_\-]+$/', $campaignRef)) mg_fail('Campaign is required.', 422);

function mg_media_detail_decode_json(mixed $json): array
{
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_media_detail_table_ready(PDO $pdo, string $table, array $columns): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($columns as $column) {
            if (!in_array($column, $found, true)) return false;
        }
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function mg_media_detail_campaign(PDO $pdo, int $merchantId, string $campaignRef): array
{
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare("SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.merchant_user_id = ?
          AND c.campaign_type IN ('watch_video_reward','listen_music_reward')
          AND ((? > 0 AND c.id = ?) OR c.public_id = ? OR c.public_slug = ?)
        LIMIT 1");
    $stmt->execute([$merchantId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Media reward campaign not found.', 404);
    return $campaign;
}

function mg_media_detail_type_label(string $type): string
{
    return $type === 'listen_music_reward' ? 'Listen Music Reward' : 'Watch Video Reward';
}

function mg_media_detail_provider_label(string $type, array $rules): string
{
    if ($type === 'listen_music_reward') {
        return (string)($rules['audio_provider'] ?? 'spotify') === 'uploaded' ? 'Uploaded audio' : 'Spotify listen intent';
    }
    return (string)($rules['video_provider'] ?? 'youtube') === 'uploaded' ? 'Uploaded video' : 'YouTube video';
}

function mg_media_detail_track_label(string $type, array $rules): string
{
    if ($type === 'listen_music_reward') {
        $track = trim((string)($rules['track_title'] ?? ''));
        $artist = trim((string)($rules['artist_name'] ?? ''));
        return trim($track . ($artist !== '' ? ' · ' . $artist : '')) ?: mg_media_detail_provider_label($type, $rules);
    }
    if ((string)($rules['video_provider'] ?? 'youtube') === 'uploaded') return trim((string)($rules['uploaded_asset_id'] ?? 'Uploaded video')) ?: 'Uploaded video';
    return trim((string)($rules['youtube_video_id'] ?? 'YouTube video')) ?: 'YouTube video';
}

function mg_media_detail_public_ref(array $campaign): string
{
    return trim((string)($campaign['public_slug'] ?? '')) !== '' ? (string)$campaign['public_slug'] : (string)$campaign['public_id'];
}

function mg_media_detail_public_url(array $campaign): string
{
    $path = (string)$campaign['campaign_type'] === 'listen_music_reward' ? '/listen-reward.php' : '/watch-reward.php';
    return $path . '?campaign=' . rawurlencode(mg_media_detail_public_ref($campaign));
}

function mg_media_detail_extract_attribution(array $metadata, array $eventContext): array
{
    $embed = is_array($metadata['embed_attribution'] ?? null) ? $metadata['embed_attribution'] : [];
    $eventEmbed = is_array($eventContext['embed_attribution'] ?? null) ? $eventContext['embed_attribution'] : [];
    $originHost = (string)($metadata['origin_host'] ?? $eventContext['origin_host'] ?? $embed['origin_host'] ?? $eventEmbed['origin_host'] ?? '');
    $pageUrl = (string)($metadata['page_url'] ?? $eventContext['page_url'] ?? $embed['page_url'] ?? $eventEmbed['page_url'] ?? '');
    $mode = (string)($metadata['embed_mode'] ?? $eventContext['embed_mode'] ?? $embed['embed_mode'] ?? $eventEmbed['embed_mode'] ?? '');
    $source = (string)($metadata['embed_source'] ?? $eventContext['embed_source'] ?? $embed['embed_source'] ?? $eventEmbed['embed_source'] ?? '');
    $websiteEmbed = !empty($metadata['website_embed']) || !empty($eventContext['website_embed']) || $originHost !== '' || $pageUrl !== '' || $mode !== '';
    return [
        'source' => $websiteEmbed ? ($source !== '' ? $source : 'website_embed') : 'public_page',
        'origin_host' => $originHost ?: null,
        'page_url' => $pageUrl ?: null,
        'embed_mode' => $mode ?: null,
        'label' => $websiteEmbed ? 'Website embed' : 'Public page',
    ];
}

function mg_media_detail_event_progress(array $context): float
{
    return min(100.0, max(0.0, max(
        (float)($context['progress_percent'] ?? 0),
        (float)($context['watch_percent'] ?? 0),
        (float)($context['listen_percent'] ?? 0),
        (float)($context['max_progress_percent'] ?? 0)
    )));
}

try {
    $campaign = mg_media_detail_campaign($pdo, $merchantId, $campaignRef);
    $campaignId = (int)$campaign['id'];
    $campaignType = (string)$campaign['campaign_type'];
    $rules = mg_media_detail_decode_json($campaign['rules_json'] ?? null);
    $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
    $embedReady = mg_media_detail_table_ready($pdo, 'campaign_embed_events', ['campaign_id', 'merchant_user_id', 'event_type', 'origin_host', 'page_url', 'embed_mode', 'created_at']);

    $contactStmt = $pdo->prepare('SELECT id, public_id, email, phone, name, metadata_json, created_at, updated_at FROM campaign_contacts WHERE merchant_user_id = ? AND campaign_id = ? ORDER BY updated_at DESC LIMIT 300');
    $contactStmt->execute([$merchantId, $campaignId]);
    $contacts = [];
    foreach ($contactStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $contact) {
        $contacts[(int)$contact['id']] = [
            'contact_id' => (int)$contact['id'],
            'id' => (string)$contact['public_id'],
            'name' => trim((string)($contact['name'] ?? '')),
            'email' => (string)$contact['email'],
            'phone' => trim((string)($contact['phone'] ?? '')),
            'created_at' => $contact['created_at'] ?? null,
            'updated_at' => $contact['updated_at'] ?? null,
            'metadata' => mg_media_detail_decode_json($contact['metadata_json'] ?? null),
            'starts' => 0,
            'progress_events' => 0,
            'max_progress_percent' => 0.0,
            'milestones_reached' => [],
            'rewards' => [],
            'wallet_items' => 0,
            'claimed' => 0,
            'redeemed' => 0,
            'pppm_handoff' => false,
            'inbox_status' => 'Not issued',
            'last_activity_at' => $contact['updated_at'] ?? $contact['created_at'] ?? null,
            'attribution' => ['source' => 'public_page', 'origin_host' => null, 'page_url' => null, 'embed_mode' => null, 'label' => 'Public page'],
        ];
    }

    $eventsStmt = $pdo->prepare('SELECT id, contact_id, wallet_item_id, event_type, event_context_json, created_at FROM campaign_events WHERE merchant_user_id = ? AND campaign_id = ? AND created_at >= ? ORDER BY id DESC LIMIT 1200');
    $eventsStmt->execute([$merchantId, $campaignId, $cutoff]);
    $recentEvents = [];
    foreach ($eventsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
        $contactId = (int)($event['contact_id'] ?? 0);
        $context = mg_media_detail_decode_json($event['event_context_json'] ?? null);
        $eventType = (string)($event['event_type'] ?? '');
        $progress = mg_media_detail_event_progress($context);
        $recentEvents[] = [
            'event_type' => $eventType,
            'contact_id' => $contactId > 0 && isset($contacts[$contactId]) ? $contacts[$contactId]['id'] : null,
            'contact_email' => $contactId > 0 && isset($contacts[$contactId]) ? $contacts[$contactId]['email'] : null,
            'progress_percent' => $progress,
            'milestone_percent' => (int)($context['milestone_percent'] ?? 0),
            'created_at' => $event['created_at'] ?? null,
        ];
        if ($contactId <= 0 || !isset($contacts[$contactId])) continue;
        if (str_ends_with($eventType, '.started')) $contacts[$contactId]['starts']++;
        if (str_ends_with($eventType, '.progress')) $contacts[$contactId]['progress_events']++;
        if ($progress > $contacts[$contactId]['max_progress_percent']) $contacts[$contactId]['max_progress_percent'] = $progress;
        $milestone = (int)($context['milestone_percent'] ?? 0);
        if ($milestone > 0) $contacts[$contactId]['milestones_reached'][$milestone] = $milestone;
        if (!empty($context['pppm_bridge'])) $contacts[$contactId]['pppm_handoff'] = true;
        $attr = mg_media_detail_extract_attribution($contacts[$contactId]['metadata'], $context);
        if ($attr['source'] !== 'public_page') $contacts[$contactId]['attribution'] = $attr;
        $contacts[$contactId]['last_activity_at'] = max((string)$contacts[$contactId]['last_activity_at'], (string)($event['created_at'] ?? '')) ?: $contacts[$contactId]['last_activity_at'];
    }

    $walletStmt = $pdo->prepare("SELECT wi.id, wi.public_id, wi.contact_id, wi.status, wi.title_snapshot, wi.metadata_json, wi.issued_at, wi.claimed_at, wi.redeemed_at, wi.updated_at FROM wallet_items wi WHERE wi.merchant_user_id = ? AND wi.campaign_id = ? AND wi.source_type = ? AND wi.status <> 'cancelled' ORDER BY wi.id DESC LIMIT 600");
    $walletStmt->execute([$merchantId, $campaignId, $campaignType]);
    $walletItems = [];
    foreach ($walletStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $wallet) {
        $contactId = (int)($wallet['contact_id'] ?? 0);
        $metadata = mg_media_detail_decode_json($wallet['metadata_json'] ?? null);
        $reward = [
            'id' => (string)$wallet['public_id'],
            'title' => (string)($wallet['title_snapshot'] ?? 'Reward'),
            'status' => (string)($wallet['status'] ?? 'issued'),
            'milestone_percent' => (int)($metadata['milestone_percent'] ?? 0),
            'pppm_destination' => (string)($metadata['pppm_destination'] ?? ''),
            'issued_at' => $wallet['issued_at'] ?? null,
            'claimed_at' => $wallet['claimed_at'] ?? null,
            'redeemed_at' => $wallet['redeemed_at'] ?? null,
            'updated_at' => $wallet['updated_at'] ?? null,
        ];
        $walletItems[] = $reward + ['contact_id' => $contactId > 0 && isset($contacts[$contactId]) ? $contacts[$contactId]['id'] : null];
        if ($contactId <= 0 || !isset($contacts[$contactId])) continue;
        $contacts[$contactId]['rewards'][] = $reward;
        $contacts[$contactId]['wallet_items']++;
        if ($reward['status'] === 'claimed') $contacts[$contactId]['claimed']++;
        if ($reward['status'] === 'redeemed') $contacts[$contactId]['redeemed']++;
        if ($reward['pppm_destination'] === 'inbox') $contacts[$contactId]['pppm_handoff'] = true;
        if ($reward['milestone_percent'] > 0) $contacts[$contactId]['milestones_reached'][$reward['milestone_percent']] = $reward['milestone_percent'];
        $contacts[$contactId]['inbox_status'] = $contacts[$contactId]['redeemed'] > 0 ? 'Redeemed' : ($contacts[$contactId]['claimed'] > 0 ? 'Claimed' : 'Inbox issued');
        $contacts[$contactId]['last_activity_at'] = max((string)$contacts[$contactId]['last_activity_at'], (string)($reward['updated_at'] ?? $reward['issued_at'] ?? '')) ?: $contacts[$contactId]['last_activity_at'];
    }

    $embedTotals = ['loaded' => 0, 'opened' => 0, 'submitted' => 0, 'invalid' => 0, 'error' => 0];
    $embedOrigins = [];
    if ($embedReady) {
        $embedStmt = $pdo->prepare("SELECT event_type, origin_host, page_url, embed_mode, created_at FROM campaign_embed_events WHERE merchant_user_id = ? AND campaign_id = ? AND created_at >= ? ORDER BY id DESC LIMIT 500");
        $embedStmt->execute([$merchantId, $campaignId, $cutoff]);
        foreach ($embedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $embedEvent) {
            $type = (string)($embedEvent['event_type'] ?? '');
            if (array_key_exists($type, $embedTotals)) $embedTotals[$type]++;
            $host = trim((string)($embedEvent['origin_host'] ?? '')) ?: 'unknown origin';
            if (!isset($embedOrigins[$host])) $embedOrigins[$host] = ['origin_host' => $host, 'total' => 0, 'loaded' => 0, 'opened' => 0, 'last_seen' => null, 'page_url' => $embedEvent['page_url'] ?? null, 'embed_mode' => $embedEvent['embed_mode'] ?? null];
            $embedOrigins[$host]['total']++;
            if ($type === 'loaded') $embedOrigins[$host]['loaded']++;
            if ($type === 'opened') $embedOrigins[$host]['opened']++;
            $embedOrigins[$host]['last_seen'] = max((string)($embedOrigins[$host]['last_seen'] ?? ''), (string)($embedEvent['created_at'] ?? '')) ?: null;
        }
    }

    $contactRows = array_values(array_map(static function (array $row): array {
        $milestones = array_values($row['milestones_reached']);
        sort($milestones);
        return [
            'id' => $row['id'],
            'name' => $row['name'] ?: 'Customer',
            'email' => $row['email'],
            'phone' => $row['phone'],
            'starts' => $row['starts'],
            'progress_events' => $row['progress_events'],
            'max_progress_percent' => round((float)$row['max_progress_percent'], 2),
            'milestones_reached' => $milestones,
            'wallet_items' => $row['wallet_items'],
            'claimed' => $row['claimed'],
            'redeemed' => $row['redeemed'],
            'inbox_status' => $row['inbox_status'],
            'pppm_handoff' => (bool)$row['pppm_handoff'],
            'attribution' => $row['attribution'],
            'last_activity_at' => $row['last_activity_at'],
            'rewards' => $row['rewards'],
        ];
    }, $contacts));
    usort($contactRows, static fn(array $a, array $b): int => strcmp((string)($b['last_activity_at'] ?? ''), (string)($a['last_activity_at'] ?? '')));

    $totals = [
        'contacts' => count($contactRows),
        'starts' => array_sum(array_column($contactRows, 'starts')),
        'progress_events' => array_sum(array_column($contactRows, 'progress_events')),
        'wallet_items' => count($walletItems),
        'claimed' => array_sum(array_column($contactRows, 'claimed')),
        'redeemed' => array_sum(array_column($contactRows, 'redeemed')),
        'max_progress_percent' => $contactRows ? max(array_column($contactRows, 'max_progress_percent')) : 0,
        'avg_progress_percent' => $contactRows ? round(array_sum(array_column($contactRows, 'max_progress_percent')) / max(1, count($contactRows)), 2) : 0,
        'embed' => $embedTotals,
    ];

    mg_ok([
        'days' => $days,
        'embed_analytics_ready' => $embedReady,
        'campaign' => [
            'id' => (string)$campaign['public_id'],
            'slug' => $campaign['public_slug'] ?? null,
            'title' => (string)$campaign['title'],
            'description' => $campaign['description'] ?? null,
            'campaign_type' => $campaignType,
            'campaign_type_label' => mg_media_detail_type_label($campaignType),
            'provider_label' => mg_media_detail_provider_label($campaignType, $rules),
            'track_label' => mg_media_detail_track_label($campaignType, $rules),
            'status' => (string)$campaign['status'],
            'reward_template_title' => $campaign['reward_template_title'] ?? null,
            'public_url' => mg_media_detail_public_url($campaign),
            'embed_qa_url' => '/merchant-campaign-embed-qa.php?campaign=' . rawurlencode(mg_media_detail_public_ref($campaign)),
            'embed_analytics_url' => '/merchant-campaign-embed-analytics.php?campaign=' . rawurlencode(mg_media_detail_public_ref($campaign)),
        ],
        'totals' => $totals,
        'contacts' => $contactRows,
        'embed_origins' => array_values($embedOrigins),
        'recent_events' => array_slice($recentEvents, 0, 50),
    ], 'Campaign media performance loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_media_performance.unavailable', 'Campaign media performance unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to load campaign media performance.', 500);
}
