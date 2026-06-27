<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int) ($user['id'] ?? 0);
$context = mg_user_package_context($pdo, $user);

function mg_account_package_count(PDO $pdo, string $sql, array $params): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return max(0, (int) $stmt->fetchColumn());
    } catch (Throwable) {
        return 0;
    }
}

$usage = [
    'max_microgifts' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM catalog_products WHERE merchant_user_id=? AND status<>'archived'", [$userId]),
    'max_rewards' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM reward_templates WHERE merchant_user_id=? AND status<>'archived'", [$userId]),
    'max_active_campaigns' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active'", [$userId]),
    'max_crm_contacts' => mg_account_package_count($pdo, "SELECT COUNT(DISTINCT email) FROM campaign_contacts WHERE merchant_user_id=? AND email<>''", [$userId]),
    'monthly_stamps_included' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND status<>'cancelled' AND issued_at>=?", [$userId, gmdate('Y-m-01 00:00:00')]),
    'max_landing_pages' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM merchant_storefronts WHERE merchant_user_id=? AND status<>'archived'", [$userId]),
    'max_locations' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM merchant_locations WHERE merchant_user_id=? AND status<>'archived'", [$userId]),
    'max_team_seats' => mg_account_package_count($pdo, "SELECT COUNT(*) FROM merchant_team_members mtm INNER JOIN merchant_workspaces mw ON mw.id=mtm.workspace_id WHERE mw.merchant_user_id=? AND mtm.status<>'removed'", [$userId]),
];

$labels = [
    'max_microgifts' => 'Microgifts',
    'max_rewards' => 'Rewards',
    'max_active_campaigns' => 'Active campaigns',
    'max_crm_contacts' => 'CRM contacts',
    'monthly_stamps_included' => 'Monthly stamps',
    'max_landing_pages' => 'Landing pages',
    'max_locations' => 'Locations',
    'max_team_seats' => 'Team seats',
];

$limits = [];
foreach ($labels as $key => $label) {
    $limit = mg_package_limit_value($context, $key);
    $unlimited = $limit === null || $limit === '';
    $used = (int) ($usage[$key] ?? 0);
    $numericLimit = $unlimited ? null : max(0, (int) $limit);
    $remaining = $unlimited ? null : max(0, (int) $numericLimit - $used);
    $limits[$key] = [
        'label' => $label,
        'used' => $used,
        'limit' => $numericLimit,
        'remaining' => $remaining,
        'unlimited' => $unlimited,
        'at_limit' => !$unlimited && $used >= (int) $numericLimit,
    ];
}

mg_ok([
    'package' => [
        'package_id' => (string) ($context['package_id'] ?? 'free'),
        'package_name' => (string) ($context['package_name'] ?? 'Free'),
        'status' => (string) ($context['status'] ?? 'free'),
        'merchant_access' => !empty($context['merchant_access']),
    ],
    'limits' => $limits,
    'send_channels' => [
        'email_stamps_enabled' => (bool) mg_package_limit_value($context, 'email_stamps_enabled'),
        'sms_stamps_enabled' => (bool) mg_package_limit_value($context, 'sms_stamps_enabled'),
        'stamp_overage_enabled' => (bool) mg_package_limit_value($context, 'stamp_overage_enabled'),
        'bulk_stamp_purchase_enabled' => (bool) mg_package_limit_value($context, 'bulk_stamp_purchase_enabled'),
    ],
]);
