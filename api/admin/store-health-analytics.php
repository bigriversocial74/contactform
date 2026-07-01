<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/app.php';
require_once dirname(__DIR__, 2) . '/includes/admin-auth.php';

$user = mg_require_admin_page_key('admin.system_health');
$pdo = mg_db();

function mg_sha_admin_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_sha_admin_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_sha_admin_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!mg_sha_admin_table_exists($pdo, 'merchant_store_health_actions')) {
    echo json_encode(['ok' => false, 'error' => 'merchant_store_health_actions table is not installed', 'missing_table' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

$total = mg_sha_admin_scalar($pdo, 'SELECT COUNT(*) FROM merchant_store_health_actions');
$started = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='started'");
$completed = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='completed'");
$snoozed = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='snoozed'");
$dismissed = mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM merchant_store_health_actions WHERE status='dismissed'");
$activeMerchants = mg_sha_admin_scalar($pdo, 'SELECT COUNT(DISTINCT merchant_user_id) FROM merchant_store_health_actions');
$completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
$startRate = $total > 0 ? round(($started / $total) * 100, 1) : 0.0;
$dismissRate = $total > 0 ? round(($dismissed / $total) * 100, 1) : 0.0;

$statusRows = mg_sha_admin_rows($pdo, 'SELECT status, COUNT(*) total FROM merchant_store_health_actions GROUP BY status ORDER BY total DESC');
$typeRows = mg_sha_admin_rows($pdo, 'SELECT action_type, COUNT(*) total, SUM(status="started") started, SUM(status="completed") completed, SUM(status="snoozed") snoozed, SUM(status="dismissed") dismissed FROM merchant_store_health_actions GROUP BY action_type ORDER BY total DESC LIMIT 12');
$merchantRows = mg_sha_admin_rows($pdo, 'SELECT a.merchant_user_id, COALESCE(NULLIF(u.display_name,""), NULLIF(u.full_name,""), u.email, CONCAT("Merchant #",a.merchant_user_id)) merchant_name, COUNT(*) total, SUM(a.status="started") started, SUM(a.status="completed") completed, SUM(a.status="snoozed") snoozed, SUM(a.status="dismissed") dismissed, MAX(a.updated_at) last_action_at FROM merchant_store_health_actions a LEFT JOIN users u ON u.id=a.merchant_user_id GROUP BY a.merchant_user_id, merchant_name ORDER BY total DESC, last_action_at DESC LIMIT 15');
$recentRows = mg_sha_admin_rows($pdo, 'SELECT a.public_id, a.merchant_user_id, COALESCE(NULLIF(u.display_name,""), NULLIF(u.full_name,""), u.email, CONCAT("Merchant #",a.merchant_user_id)) merchant_name, a.action_key, a.action_type, a.condition_key, a.title, a.priority, a.status, a.condition_count, a.updated_at FROM merchant_store_health_actions a LEFT JOIN users u ON u.id=a.merchant_user_id ORDER BY a.updated_at DESC LIMIT 20');
$dailyRows = mg_sha_admin_rows($pdo, 'SELECT DATE(updated_at) action_date, COUNT(*) total, SUM(status="started") started, SUM(status="completed") completed, SUM(status="snoozed") snoozed, SUM(status="dismissed") dismissed FROM merchant_store_health_actions WHERE updated_at >= DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY) GROUP BY DATE(updated_at) ORDER BY action_date DESC');

$hasWalletItems = mg_sha_admin_table_exists($pdo, 'wallet_items');
$hasContacts = mg_sha_admin_table_exists($pdo, 'campaign_contacts');
$hasInvites = mg_sha_admin_table_exists($pdo, 'crm_reward_invites');
$impactReady = $hasWalletItems && $hasContacts;

$rewardOutcomes = ['issued' => 0, 'claimed' => 0, 'redeemed' => 0, 'claim_rate' => 0.0, 'redemption_rate' => 0.0];
$impactTypes = [];
$impactMerchants = [];
$impactTimeline = [];

if ($impactReady) {
    $rewardOutcomes['issued'] = mg_sha_admin_scalar($pdo, "SELECT COUNT(DISTINCT wi.id) FROM wallet_items wi INNER JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.status IN ('issued','claimed','redeemed')");
    $rewardOutcomes['claimed'] = mg_sha_admin_scalar($pdo, "SELECT COUNT(DISTINCT wi.id) FROM wallet_items wi INNER JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.status IN ('claimed','redeemed')");
    $rewardOutcomes['redeemed'] = mg_sha_admin_scalar($pdo, "SELECT COUNT(DISTINCT wi.id) FROM wallet_items wi INNER JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.status='redeemed'");
    $rewardOutcomes['claim_rate'] = $rewardOutcomes['issued'] > 0 ? round(($rewardOutcomes['claimed'] / $rewardOutcomes['issued']) * 100, 1) : 0.0;
    $rewardOutcomes['redemption_rate'] = $rewardOutcomes['claimed'] > 0 ? round(($rewardOutcomes['redeemed'] / $rewardOutcomes['claimed']) * 100, 1) : 0.0;

    $impactRows = mg_sha_admin_rows($pdo, "SELECT a.action_type,
            COUNT(DISTINCT a.id) actions,
            COUNT(DISTINCT CASE WHEN wi.status IN ('issued','claimed','redeemed') THEN wi.id END) rewards,
            COUNT(DISTINCT CASE WHEN wi.status IN ('claimed','redeemed') THEN wi.id END) claims,
            COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redemptions
        FROM merchant_store_health_actions a
        LEFT JOIN campaign_contacts cc ON cc.merchant_user_id=a.merchant_user_id
        LEFT JOIN wallet_items wi ON wi.contact_id=cc.id AND wi.updated_at>=a.updated_at AND wi.updated_at<=DATE_ADD(a.updated_at, INTERVAL 14 DAY)
        WHERE a.status IN ('started','completed')
        GROUP BY a.action_type
        ORDER BY redemptions DESC, claims DESC, rewards DESC, actions DESC
        LIMIT 12");
    $impactTypes = array_map(static function (array $row): array {
        $actions = (int)($row['actions'] ?? 0);
        $claims = (int)($row['claims'] ?? 0);
        $redemptions = (int)($row['redemptions'] ?? 0);
        return [
            'action_type' => (string)($row['action_type'] ?? ''),
            'actions' => $actions,
            'rewards' => (int)($row['rewards'] ?? 0),
            'claims' => $claims,
            'redemptions' => $redemptions,
            'claims_per_action' => $actions > 0 ? round($claims / $actions, 2) : 0.0,
            'redemptions_per_action' => $actions > 0 ? round($redemptions / $actions, 2) : 0.0,
        ];
    }, $impactRows);

    $impactMerchantRows = mg_sha_admin_rows($pdo, "SELECT a.merchant_user_id,
            COALESCE(NULLIF(u.display_name,''), NULLIF(u.full_name,''), u.email, CONCAT('Merchant #',a.merchant_user_id)) merchant_name,
            COUNT(DISTINCT a.id) actions,
            COUNT(DISTINCT CASE WHEN wi.status IN ('issued','claimed','redeemed') THEN wi.id END) rewards,
            COUNT(DISTINCT CASE WHEN wi.status IN ('claimed','redeemed') THEN wi.id END) claims,
            COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redemptions,
            MAX(COALESCE(wi.updated_at,a.updated_at)) last_impact_at
        FROM merchant_store_health_actions a
        LEFT JOIN users u ON u.id=a.merchant_user_id
        LEFT JOIN campaign_contacts cc ON cc.merchant_user_id=a.merchant_user_id
        LEFT JOIN wallet_items wi ON wi.contact_id=cc.id AND wi.updated_at>=a.updated_at AND wi.updated_at<=DATE_ADD(a.updated_at, INTERVAL 14 DAY)
        WHERE a.status IN ('started','completed')
        GROUP BY a.merchant_user_id, merchant_name
        ORDER BY redemptions DESC, claims DESC, rewards DESC, actions DESC
        LIMIT 15");
    $impactMerchants = array_map(static function (array $row): array {
        return [
            'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
            'merchant_name' => (string)($row['merchant_name'] ?? ''),
            'actions' => (int)($row['actions'] ?? 0),
            'rewards' => (int)($row['rewards'] ?? 0),
            'claims' => (int)($row['claims'] ?? 0),
            'redemptions' => (int)($row['redemptions'] ?? 0),
            'last_impact_at' => $row['last_impact_at'] ?? null,
        ];
    }, $impactMerchantRows);

    $impactTimelineRows = mg_sha_admin_rows($pdo, "SELECT a.public_id, a.merchant_user_id,
            COALESCE(NULLIF(u.display_name,''), NULLIF(u.full_name,''), u.email, CONCAT('Merchant #',a.merchant_user_id)) merchant_name,
            a.action_type, a.title, a.status, a.updated_at action_at,
            COUNT(DISTINCT CASE WHEN wi.status IN ('issued','claimed','redeemed') THEN wi.id END) rewards,
            COUNT(DISTINCT CASE WHEN wi.status IN ('claimed','redeemed') THEN wi.id END) claims,
            COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redemptions,
            MAX(wi.updated_at) latest_outcome_at
        FROM merchant_store_health_actions a
        LEFT JOIN users u ON u.id=a.merchant_user_id
        LEFT JOIN campaign_contacts cc ON cc.merchant_user_id=a.merchant_user_id
        LEFT JOIN wallet_items wi ON wi.contact_id=cc.id AND wi.updated_at>=a.updated_at AND wi.updated_at<=DATE_ADD(a.updated_at, INTERVAL 14 DAY)
        WHERE a.status IN ('started','completed')
        GROUP BY a.id,u.display_name,u.full_name,u.email
        ORDER BY a.updated_at DESC
        LIMIT 20");
    $impactTimeline = array_map(static function (array $row): array {
        return [
            'id' => (string)($row['public_id'] ?? ''),
            'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
            'merchant_name' => (string)($row['merchant_name'] ?? ''),
            'action_type' => (string)($row['action_type'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'action_at' => $row['action_at'] ?? null,
            'rewards' => (int)($row['rewards'] ?? 0),
            'claims' => (int)($row['claims'] ?? 0),
            'redemptions' => (int)($row['redemptions'] ?? 0),
            'latest_outcome_at' => $row['latest_outcome_at'] ?? null,
        ];
    }, $impactTimelineRows);
}

$inviteImpact = $hasInvites ? [
    'active_invites' => mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM crm_reward_invites WHERE status='sent' AND (expires_at IS NULL OR expires_at>NOW())"),
    'expired_invites' => mg_sha_admin_scalar($pdo, "SELECT COUNT(*) FROM crm_reward_invites WHERE status='sent' AND expires_at IS NOT NULL AND expires_at<=NOW()"),
] : ['active_invites' => 0, 'expired_invites' => 0];

$topTypes = array_map(static function (array $row): array {
    $total = (int)($row['total'] ?? 0);
    $completed = (int)($row['completed'] ?? 0);
    return [
        'action_type' => (string)($row['action_type'] ?? ''),
        'total' => $total,
        'started' => (int)($row['started'] ?? 0),
        'completed' => $completed,
        'snoozed' => (int)($row['snoozed'] ?? 0),
        'dismissed' => (int)($row['dismissed'] ?? 0),
        'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
    ];
}, $typeRows);

$merchants = array_map(static function (array $row): array {
    $total = (int)($row['total'] ?? 0);
    $completed = (int)($row['completed'] ?? 0);
    return [
        'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
        'merchant_name' => (string)($row['merchant_name'] ?? ''),
        'total' => $total,
        'started' => (int)($row['started'] ?? 0),
        'completed' => $completed,
        'snoozed' => (int)($row['snoozed'] ?? 0),
        'dismissed' => (int)($row['dismissed'] ?? 0),
        'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
        'last_action_at' => $row['last_action_at'] ?? null,
    ];
}, $merchantRows);

$recent = array_map(static function (array $row): array {
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
        'merchant_name' => (string)($row['merchant_name'] ?? ''),
        'action_key' => (string)($row['action_key'] ?? ''),
        'action_type' => (string)($row['action_type'] ?? ''),
        'condition_key' => (string)($row['condition_key'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'priority' => (string)($row['priority'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'condition_count' => (int)($row['condition_count'] ?? 0),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}, $recentRows);

$daily = array_map(static function (array $row): array {
    return [
        'date' => (string)($row['action_date'] ?? ''),
        'total' => (int)($row['total'] ?? 0),
        'started' => (int)($row['started'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
        'snoozed' => (int)($row['snoozed'] ?? 0),
        'dismissed' => (int)($row['dismissed'] ?? 0),
    ];
}, $dailyRows);

echo json_encode([
    'ok' => true,
    'summary' => [
        'total' => $total,
        'started' => $started,
        'completed' => $completed,
        'snoozed' => $snoozed,
        'dismissed' => $dismissed,
        'active_merchants' => $activeMerchants,
        'completion_rate' => $completionRate,
        'start_rate' => $startRate,
        'dismiss_rate' => $dismissRate,
    ],
    'status' => $statusRows,
    'top_types' => $topTypes,
    'merchants' => $merchants,
    'recent' => $recent,
    'daily' => $daily,
    'impact' => [
        'ready' => $impactReady,
        'tables' => ['wallet_items' => $hasWalletItems, 'campaign_contacts' => $hasContacts, 'crm_reward_invites' => $hasInvites],
        'reward_outcomes' => $rewardOutcomes,
        'invite_impact' => $inviteImpact,
        'types' => $impactTypes,
        'merchants' => $impactMerchants,
        'timeline' => $impactTimeline,
    ],
], JSON_UNESCAPED_SLASHES);
