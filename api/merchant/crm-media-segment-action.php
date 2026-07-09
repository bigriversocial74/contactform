<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_segment_action_table_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute(['merchant_crm_segments']);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_segment_action_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $raw = (string)$raw;
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_segment_action_behavior_label(string $segment): string
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

function mg_segment_action_row(PDO $pdo, int $merchantId, string $segmentId): array
{
    if ($segmentId === '' || strlen($segmentId) > 80) mg_fail('Segment is required.', 422);
    $stmt = $pdo->prepare("SELECT s.*, c.public_id campaign_public_id, c.public_slug campaign_slug, c.title campaign_title, c.campaign_type
        FROM merchant_crm_segments s
        LEFT JOIN campaigns c ON c.id = s.campaign_id
        WHERE s.merchant_user_id = ? AND s.public_id = ? AND s.segment_scope = 'media' AND s.status = 'active'
        LIMIT 1");
    $stmt->execute([$merchantId, $segmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Saved media segment not found.', 404);
    return $row;
}

function mg_segment_action_campaign_ref(array $row, array $rules): string
{
    $ref = trim((string)($rules['campaign_ref'] ?? ''));
    if ($ref !== '') return $ref;
    $slug = trim((string)($row['campaign_slug'] ?? ''));
    return $slug !== '' ? $slug : (string)($row['campaign_public_id'] ?? '');
}

function mg_segment_action_contact_rows(PDO $pdo, int $merchantId, int $campaignId, int $days): array
{
    $days = in_array($days, [7, 30, 90, 180], true) ? $days : 30;
    $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
    $stmt = $pdo->prepare("SELECT cc.public_id, cc.email, cc.phone, cc.name, cc.metadata_json, cc.updated_at,
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
        GROUP BY cc.id, cc.public_id, cc.email, cc.phone, cc.name, cc.metadata_json, cc.updated_at
        ORDER BY cc.updated_at DESC, cc.id DESC
        LIMIT 2000");
    $stmt->execute([$cutoff, $cutoff, $cutoff, $merchantId, $campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_segment_action_bucket(array $row): string
{
    if ((int)($row['redeemed'] ?? 0) > 0) return 'redeemed';
    if ((int)($row['claimed'] ?? 0) > 0) return 'claimed_unredeemed';
    if ((int)($row['wallet_items'] ?? 0) > 0) return 'milestone_unclaimed';
    if ((int)($row['starts'] ?? 0) > 0 || (int)($row['progress_events'] ?? 0) > 0 || (float)($row['max_progress_percent'] ?? 0) > 0) return 'started_incomplete';
    return 'no_activity';
}

function mg_segment_action_matches(array $row, string $segment, string $search): bool
{
    if ($segment !== 'all' && mg_segment_action_bucket($row) !== $segment) return false;
    $search = strtolower(trim($search));
    if ($search === '') return true;
    $metadata = mg_segment_action_json($row['metadata_json'] ?? null);
    $haystack = strtolower(implode(' ', [
        (string)($row['name'] ?? ''),
        (string)($row['email'] ?? ''),
        (string)($row['phone'] ?? ''),
        (string)($metadata['origin_host'] ?? ''),
        (string)($metadata['embed_mode'] ?? ''),
        (string)($metadata['embed_source'] ?? ''),
        mg_segment_action_behavior_label(mg_segment_action_bucket($row)),
    ]));
    return str_contains($haystack, $search);
}

function mg_segment_action_members(PDO $pdo, int $merchantId, array $row): array
{
    $rules = mg_segment_action_json($row['rules_json'] ?? null);
    $campaignId = (int)($row['campaign_id'] ?? 0);
    $days = (int)($rules['days'] ?? 30);
    $behavior = (string)($rules['behavior_segment'] ?? 'all');
    $search = (string)($rules['search'] ?? '');
    if ($campaignId <= 0) return [];
    $members = [];
    foreach (mg_segment_action_contact_rows($pdo, $merchantId, $campaignId, $days) as $contact) {
        if (!mg_segment_action_matches($contact, $behavior, $search)) continue;
        $metadata = mg_segment_action_json($contact['metadata_json'] ?? null);
        $members[] = [
            'id' => (string)$contact['public_id'],
            'name' => (string)($contact['name'] ?? ''),
            'email' => (string)$contact['email'],
            'phone' => (string)($contact['phone'] ?? ''),
            'behavior_bucket' => mg_segment_action_bucket($contact),
            'behavior_label' => mg_segment_action_behavior_label(mg_segment_action_bucket($contact)),
            'progress_percent' => round((float)($contact['max_progress_percent'] ?? 0), 2),
            'starts' => (int)($contact['starts'] ?? 0),
            'progress_events' => (int)($contact['progress_events'] ?? 0),
            'wallet_items' => (int)($contact['wallet_items'] ?? 0),
            'claimed' => (int)($contact['claimed'] ?? 0),
            'redeemed' => (int)($contact['redeemed'] ?? 0),
            'origin_host' => (string)($metadata['origin_host'] ?? ''),
            'embed_mode' => (string)($metadata['embed_mode'] ?? ''),
            'last_activity_at' => $contact['updated_at'] ?? null,
        ];
    }
    return $members;
}

function mg_segment_action_payload(PDO $pdo, int $merchantId, array $row, bool $refresh = false): array
{
    $rules = mg_segment_action_json($row['rules_json'] ?? null);
    $members = mg_segment_action_members($pdo, $merchantId, $row);
    $previousCount = (int)($row['last_count'] ?? 0);
    $currentCount = count($members);
    $countDelta = $currentCount - $previousCount;
    if ($refresh || $currentCount !== $previousCount) {
        $pdo->prepare('UPDATE merchant_crm_segments SET last_count=?, last_refreshed_at=NOW(), updated_at=updated_at WHERE id=?')->execute([$currentCount, (int)$row['id']]);
        $row['last_count'] = $currentCount;
        $row['last_refreshed_at'] = date('Y-m-d H:i:s');
    }
    $campaignRef = mg_segment_action_campaign_ref($row, $rules);
    $days = (int)($rules['days'] ?? 30);
    $behavior = (string)($rules['behavior_segment'] ?? 'all');
    $search = (string)($rules['search'] ?? '');
    $contactIds = array_map(static fn(array $member): string => (string)$member['id'], $members);
    $base = 'campaign=' . rawurlencode($campaignRef) . '&saved_segment=' . rawurlencode((string)$row['public_id']);
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
        'behavior_label' => mg_segment_action_behavior_label($behavior),
        'search' => $search,
        'previous_count' => $previousCount,
        'current_count' => $currentCount,
        'count_delta' => $countDelta,
        'last_count' => (int)($row['last_count'] ?? $currentCount),
        'last_refreshed_at' => $row['last_refreshed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'members' => $members,
        'contact_ids' => $contactIds,
        'health' => [
            'label' => $countDelta === 0 ? 'Stable' : ($countDelta > 0 ? 'Growing' : 'Shrinking'),
            'summary' => $countDelta === 0 ? 'No contact count change since last refresh.' : ($countDelta > 0 ? $countDelta . ' new matching contact(s).' : abs($countDelta) . ' contact(s) moved out of this segment.'),
        ],
        'activity_log' => [
            ['type' => 'segment.created', 'label' => 'Segment created', 'at' => $row['created_at'] ?? null],
            ['type' => 'segment.refreshed', 'label' => 'Dynamic count refreshed', 'at' => $row['last_refreshed_at'] ?? null, 'count' => $currentCount],
            ['type' => 'segment.rules', 'label' => mg_segment_action_behavior_label($behavior), 'at' => $row['updated_at'] ?? null, 'count' => $currentCount],
        ],
        'urls' => [
            'open_rules' => '/merchant-campaign-media-performance.php?campaign=' . rawurlencode($campaignRef) . '&days=' . $days . '&segment=' . rawurlencode($behavior) . '&q=' . rawurlencode($search) . '&saved_segment=' . rawurlencode((string)$row['public_id']),
            'export' => '/api/merchant/campaign-media-performance-export.php?campaign=' . rawurlencode($campaignRef) . '&days=' . $days . '&segment=' . rawurlencode($behavior),
            'crm' => '/merchant-crm.php?' . $base,
            'message' => '/merchant-crm.php?' . $base . '&action=message_segment',
            'reward' => '/merchant-crm.php?' . $base . '&action=reward_segment',
            'followup' => '/merchant-crm.php?' . $base . '&action=followup_segment',
            'action_center' => '/merchant-crm-segment-action-center.php?segment=' . rawurlencode((string)$row['public_id']),
        ],
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    if (!mg_segment_action_table_ready($pdo)) {
        if ($method === 'GET') mg_ok(['segment' => null, 'schema_ready' => false], 'Import database/merchant_crm_media_segments_v1.sql first.');
        mg_fail('Saved CRM media segments are not installed. Import database/merchant_crm_media_segments_v1.sql.', 503);
    }

    if ($method === 'GET') {
        $segmentId = trim((string)($_GET['segment'] ?? $_GET['segment_id'] ?? $_GET['saved_segment'] ?? ''));
        $row = mg_segment_action_row($pdo, $merchantId, $segmentId);
        mg_ok(['segment' => mg_segment_action_payload($pdo, $merchantId, $row), 'schema_ready' => true]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'refresh')));
    $segmentId = trim((string)($input['segment_id'] ?? $input['segment'] ?? ''));
    $row = mg_segment_action_row($pdo, $merchantId, $segmentId);

    if ($action === 'refresh') {
        mg_ok(['segment' => mg_segment_action_payload($pdo, $merchantId, $row, true)], 'Segment refreshed.');
    }

    if ($action === 'rename') {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        if ($name === '' || mb_strlen($name) > 140) mg_fail('Segment name is required and must be under 140 characters.', 422);
        if (mb_strlen($description) > 500) mg_fail('Segment description is too long.', 422);
        $stmt = $pdo->prepare('UPDATE merchant_crm_segments SET name=?, description=?, updated_at=NOW() WHERE id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$name, $description !== '' ? $description : null, (int)$row['id'], $merchantId]);
        $fresh = mg_segment_action_row($pdo, $merchantId, $segmentId);
        mg_ok(['segment' => mg_segment_action_payload($pdo, $merchantId, $fresh)], 'Segment renamed.');
    }

    if ($action === 'duplicate') {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') $name = (string)$row['name'] . ' Copy';
        if (mb_strlen($name) > 140) $name = mb_substr($name, 0, 140);
        $candidate = $name;
        for ($i = 2; $i <= 12; $i++) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM merchant_crm_segments WHERE merchant_user_id=? AND name=? LIMIT 1');
            $check->execute([$merchantId, $candidate]);
            if ((int)$check->fetchColumn() === 0) break;
            $candidate = mb_substr($name, 0, 132) . ' ' . $i;
        }
        $publicId = mg_merchant_uuid();
        $members = mg_segment_action_members($pdo, $merchantId, $row);
        $stmt = $pdo->prepare("INSERT INTO merchant_crm_segments (public_id,merchant_user_id,campaign_id,segment_scope,name,description,rules_json,status,last_count,last_refreshed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())");
        $stmt->execute([$publicId, $merchantId, (int)$row['campaign_id'], 'media', $candidate, $row['description'] ?? null, (string)$row['rules_json'], 'active', count($members)]);
        $fresh = mg_segment_action_row($pdo, $merchantId, $publicId);
        mg_ok(['segment' => mg_segment_action_payload($pdo, $merchantId, $fresh)], 'Segment duplicated.');
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE merchant_crm_segments SET status='archived', updated_at=NOW() WHERE id=? AND merchant_user_id=? LIMIT 1");
        $stmt->execute([(int)$row['id'], $merchantId]);
        mg_ok(['deleted' => true], 'Segment deleted.');
    }

    mg_fail('Unsupported segment action.', 422);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_media_segment_action.failed', 'CRM media segment action failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId ?? null);
    mg_fail('Unable to process segment action.', 500);
}
