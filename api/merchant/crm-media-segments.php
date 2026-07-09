<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_crm_media_segments_table_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute(['merchant_crm_segments']);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_crm_media_segments_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $raw = (string)$raw;
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_crm_media_segments_campaign(PDO $pdo, int $merchantId, string $campaignRef): array
{
    $campaignRef = trim($campaignRef);
    if ($campaignRef === '' || strlen($campaignRef) > 180 || !preg_match('/^[a-zA-Z0-9_\-]+$/', $campaignRef)) mg_fail('Campaign is required.', 422);
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare("SELECT id, public_id, public_slug, title, campaign_type FROM campaigns WHERE merchant_user_id = ? AND campaign_type IN ('watch_video_reward','listen_music_reward') AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1");
    $stmt->execute([$merchantId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Media reward campaign not found.', 404);
    return $campaign;
}

function mg_crm_media_segments_ref(array $campaign): string
{
    $slug = trim((string)($campaign['public_slug'] ?? ''));
    return $slug !== '' ? $slug : (string)$campaign['public_id'];
}

function mg_crm_media_segments_behavior_label(string $segment): string
{
    return match ($segment) {
        'started_incomplete' => 'Started, did not finish',
        'milestone_unclaimed' => 'Milestone hit, not claimed',
        'claimed_unredeemed' => 'Claimed, not redeemed',
        'redeemed' => 'Redeemed / completed',
        'no_activity' => 'No tracked activity',
        default => 'All contacts',
    };
}

function mg_crm_media_segments_contact_rows(PDO $pdo, int $merchantId, int $campaignId, int $days): array
{
    $days = in_array($days, [7, 30, 90, 180], true) ? $days : 30;
    $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
    $stmt = $pdo->prepare("SELECT cc.public_id, cc.email, cc.phone, cc.name, cc.metadata_json,
               COUNT(DISTINCT CASE WHEN ce.event_type IN ('watch_reward.started','listen_reward.started') AND ce.created_at >= ? THEN ce.id END) starts,
               COUNT(DISTINCT CASE WHEN ce.event_type IN ('watch_reward.progress','listen_reward.progress') AND ce.created_at >= ? THEN ce.id END) progress_events,
               MAX(CASE WHEN ce.created_at >= ? THEN GREATEST(
                    COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.progress_percent')) AS DECIMAL(6,2)),0),
                    COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.watch_percent')) AS DECIMAL(6,2)),0),
                    COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.listen_percent')) AS DECIMAL(6,2)),0),
                    COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.max_progress_percent')) AS DECIMAL(6,2)),0)
               ) ELSE 0 END) max_progress_percent,
               COUNT(DISTINCT wi.id) wallet_items,
               COUNT(DISTINCT CASE WHEN wi.status='claimed' THEN wi.id END) claimed,
               COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redeemed
        FROM campaign_contacts cc
        LEFT JOIN campaign_events ce ON ce.contact_id = cc.id
        LEFT JOIN wallet_items wi ON wi.contact_id = cc.id AND wi.status <> 'cancelled'
        WHERE cc.merchant_user_id = ? AND cc.campaign_id = ?
        GROUP BY cc.id, cc.public_id, cc.email, cc.phone, cc.name, cc.metadata_json
        ORDER BY cc.updated_at DESC, cc.id DESC
        LIMIT 2000");
    $stmt->execute([$cutoff, $cutoff, $cutoff, $merchantId, $campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_crm_media_segments_bucket(array $row): string
{
    if ((int)($row['redeemed'] ?? 0) > 0) return 'redeemed';
    if ((int)($row['claimed'] ?? 0) > 0) return 'claimed_unredeemed';
    if ((int)($row['wallet_items'] ?? 0) > 0) return 'milestone_unclaimed';
    if ((int)($row['starts'] ?? 0) > 0 || (int)($row['progress_events'] ?? 0) > 0 || (float)($row['max_progress_percent'] ?? 0) > 0) return 'started_incomplete';
    return 'no_activity';
}

function mg_crm_media_segments_row_matches(array $row, string $segment, string $search): bool
{
    if ($segment !== 'all' && mg_crm_media_segments_bucket($row) !== $segment) return false;
    $search = strtolower(trim($search));
    if ($search === '') return true;
    $metadata = mg_crm_media_segments_json($row['metadata_json'] ?? null);
    $haystack = strtolower(implode(' ', [
        (string)($row['name'] ?? ''),
        (string)($row['email'] ?? ''),
        (string)($row['phone'] ?? ''),
        (string)($metadata['origin_host'] ?? ''),
        (string)($metadata['embed_mode'] ?? ''),
        (string)($metadata['embed_source'] ?? ''),
        mg_crm_media_segments_behavior_label(mg_crm_media_segments_bucket($row)),
    ]));
    return str_contains($haystack, $search);
}

function mg_crm_media_segments_members(PDO $pdo, int $merchantId, array $segmentRow): array
{
    $rules = mg_crm_media_segments_json($segmentRow['rules_json'] ?? null);
    $campaignId = (int)($segmentRow['campaign_id'] ?? 0);
    $days = (int)($rules['days'] ?? 30);
    $behavior = (string)($rules['behavior_segment'] ?? 'all');
    $search = (string)($rules['search'] ?? '');
    if ($campaignId <= 0) return [];
    $rows = mg_crm_media_segments_contact_rows($pdo, $merchantId, $campaignId, $days);
    $out = [];
    foreach ($rows as $row) {
        if (!mg_crm_media_segments_row_matches($row, $behavior, $search)) continue;
        $out[] = [
            'id' => (string)$row['public_id'],
            'name' => (string)($row['name'] ?? ''),
            'email' => (string)$row['email'],
            'behavior_bucket' => mg_crm_media_segments_bucket($row),
            'progress_percent' => round((float)($row['max_progress_percent'] ?? 0), 2),
            'wallet_items' => (int)($row['wallet_items'] ?? 0),
            'claimed' => (int)($row['claimed'] ?? 0),
            'redeemed' => (int)($row['redeemed'] ?? 0),
        ];
    }
    return $out;
}

function mg_crm_media_segments_count(PDO $pdo, int $merchantId, array $segmentRow): int
{
    return count(mg_crm_media_segments_members($pdo, $merchantId, $segmentRow));
}

function mg_crm_media_segments_payload(PDO $pdo, int $merchantId, array $row, bool $includeMembers = false): array
{
    $rules = mg_crm_media_segments_json($row['rules_json'] ?? null);
    $campaignRef = (string)($rules['campaign_ref'] ?? $row['campaign_public_id'] ?? '');
    $days = (int)($rules['days'] ?? 30);
    $behavior = (string)($rules['behavior_segment'] ?? 'all');
    $search = (string)($rules['search'] ?? '');
    $base = 'campaign=' . rawurlencode($campaignRef) . '&days=' . $days . '&segment=' . rawurlencode($behavior) . '&q=' . rawurlencode($search) . '&saved_segment=' . rawurlencode((string)$row['public_id']);
    $members = $includeMembers ? mg_crm_media_segments_members($pdo, $merchantId, $row) : [];
    return [
        'id' => (string)$row['public_id'],
        'name' => (string)$row['name'],
        'description' => (string)($row['description'] ?? ''),
        'campaign_id' => (string)($row['campaign_public_id'] ?? ''),
        'campaign_slug' => $row['campaign_slug'] ?? null,
        'campaign_title' => (string)($row['campaign_title'] ?? 'Media campaign'),
        'campaign_type' => (string)($row['campaign_type'] ?? ''),
        'days' => $days,
        'behavior_segment' => $behavior,
        'behavior_label' => mg_crm_media_segments_behavior_label($behavior),
        'search' => $search,
        'last_count' => (int)($row['last_count'] ?? 0),
        'last_refreshed_at' => $row['last_refreshed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'open_url' => '/merchant-campaign-media-performance.php?' . $base,
        'export_url' => '/api/merchant/campaign-media-performance-export.php?campaign=' . rawurlencode($campaignRef) . '&days=' . $days . '&segment=' . rawurlencode($behavior),
        'crm_url' => '/merchant-crm.php?campaign=' . rawurlencode($campaignRef) . '&saved_segment=' . rawurlencode((string)$row['public_id']),
        'message_url' => '/merchant-crm.php?campaign=' . rawurlencode($campaignRef) . '&saved_segment=' . rawurlencode((string)$row['public_id']) . '&action=message_segment',
        'reward_url' => '/merchant-crm.php?campaign=' . rawurlencode($campaignRef) . '&saved_segment=' . rawurlencode((string)$row['public_id']) . '&action=reward_segment',
        'members' => $members,
        'contact_ids' => array_map(static fn($member) => $member['id'], $members),
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    if (!mg_crm_media_segments_table_ready($pdo)) {
        if ($method === 'GET') mg_ok(['segments' => [], 'schema_ready' => false], 'Import database/merchant_crm_media_segments_v1.sql to enable saved CRM media segments.');
        mg_fail('Saved CRM media segments are not installed. Import database/merchant_crm_media_segments_v1.sql.', 503);
    }

    if ($method === 'GET') {
        $campaignRef = trim((string)($_GET['campaign'] ?? ''));
        $segmentId = trim((string)($_GET['segment_id'] ?? $_GET['saved_segment'] ?? ''));
        $includeMembers = (int)($_GET['include_contacts'] ?? 0) === 1;
        $sql = "SELECT s.*, c.public_id campaign_public_id, c.public_slug campaign_slug, c.title campaign_title, c.campaign_type
                FROM merchant_crm_segments s
                LEFT JOIN campaigns c ON c.id = s.campaign_id
                WHERE s.merchant_user_id = ? AND s.segment_scope = 'media' AND s.status = 'active'";
        $params = [$merchantId];
        if ($segmentId !== '') { $sql .= ' AND s.public_id = ?'; $params[] = $segmentId; }
        if ($campaignRef !== '') { $sql .= ' AND (c.public_id = ? OR c.public_slug = ?)'; $params[] = $campaignRef; $params[] = $campaignRef; }
        $sql .= ' ORDER BY s.updated_at DESC, s.id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $segments = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $count = mg_crm_media_segments_count($pdo, $merchantId, $row);
            if ($count !== (int)($row['last_count'] ?? -1)) {
                $pdo->prepare('UPDATE merchant_crm_segments SET last_count=?, last_refreshed_at=NOW(), updated_at=updated_at WHERE id=?')->execute([$count, (int)$row['id']]);
                $row['last_count'] = $count;
                $row['last_refreshed_at'] = date('Y-m-d H:i:s');
            }
            $segments[] = mg_crm_media_segments_payload($pdo, $merchantId, $row, $includeMembers);
        }
        mg_ok(['segments' => $segments, 'schema_ready' => true, 'count' => count($segments)]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'save')));

    if ($action === 'delete') {
        $segmentId = trim((string)($input['segment_id'] ?? $input['id'] ?? ''));
        if ($segmentId === '' || strlen($segmentId) > 80) mg_fail('Segment is required.', 422);
        $stmt = $pdo->prepare("UPDATE merchant_crm_segments SET status='archived', updated_at=NOW() WHERE merchant_user_id=? AND public_id=? AND segment_scope='media' LIMIT 1");
        $stmt->execute([$merchantId, $segmentId]);
        mg_ok(['deleted' => $stmt->rowCount() > 0], 'Saved segment deleted.');
    }

    $name = trim((string)($input['name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $campaign = mg_crm_media_segments_campaign($pdo, $merchantId, trim((string)($input['campaign'] ?? '')));
    $days = (int)($input['days'] ?? 30);
    if (!in_array($days, [7, 30, 90, 180], true)) $days = 30;
    $behavior = strtolower(trim((string)($input['behavior_segment'] ?? $input['segment'] ?? 'all')));
    if (!in_array($behavior, ['all','started_incomplete','milestone_unclaimed','claimed_unredeemed','redeemed','no_activity'], true)) $behavior = 'all';
    $search = trim((string)($input['search'] ?? ''));
    if ($name === '' || mb_strlen($name) > 140) mg_fail('Segment name is required and must be under 140 characters.', 422);
    if (mb_strlen($description) > 500 || mb_strlen($search) > 120) mg_fail('Segment details are too long.', 422);

    $rules = [
        'kind' => 'media_campaign_behavior',
        'campaign_ref' => mg_crm_media_segments_ref($campaign),
        'campaign_public_id' => (string)$campaign['public_id'],
        'campaign_slug' => $campaign['public_slug'] ?? null,
        'campaign_type' => (string)$campaign['campaign_type'],
        'behavior_segment' => $behavior,
        'search' => $search,
        'days' => $days,
        'dynamic' => true,
    ];
    $draftRow = ['campaign_id' => (int)$campaign['id'], 'rules_json' => json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
    $count = mg_crm_media_segments_count($pdo, $merchantId, $draftRow);
    $publicId = mg_merchant_uuid();

    $stmt = $pdo->prepare("INSERT INTO merchant_crm_segments (public_id,merchant_user_id,campaign_id,segment_scope,name,description,rules_json,status,last_count,last_refreshed_at,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE campaign_id=VALUES(campaign_id), description=VALUES(description), rules_json=VALUES(rules_json), status='active', last_count=VALUES(last_count), last_refreshed_at=NOW(), updated_at=NOW()");
    $stmt->execute([$publicId, $merchantId, (int)$campaign['id'], 'media', $name, $description !== '' ? $description : null, json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'active', $count]);

    $select = $pdo->prepare("SELECT s.*, c.public_id campaign_public_id, c.public_slug campaign_slug, c.title campaign_title, c.campaign_type
        FROM merchant_crm_segments s LEFT JOIN campaigns c ON c.id=s.campaign_id
        WHERE s.merchant_user_id=? AND s.name=? AND s.segment_scope='media' LIMIT 1");
    $select->execute([$merchantId, $name]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    mg_ok(['segment' => mg_crm_media_segments_payload($pdo, $merchantId, $row ?: [], true)], 'Saved CRM media segment created.');
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_media_segments.failed', 'Saved CRM media segments unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId ?? null);
    mg_fail('Unable to process saved CRM media segment.', 500);
}
