<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/package-entitlements.php';
require_once dirname(__DIR__, 2) . '/stamps/_stamps.php';

function mg_public_campaign_limit_count(PDO $pdo, string $field, int $id, ?int $userId, string $email): int
{
    if (!in_array($field, ['campaign_id','reward_template_id'], true) || $id < 1) return 0;
    $email = strtolower(trim($email));
    $where = ["wi.$field=?", "wi.status<>'cancelled'"];
    $params = [$id];
    if ($userId && $userId > 0) {
        $where[] = '(wi.user_id=? OR cc.email=?)';
        $params[] = $userId;
        $params[] = $email;
    } else {
        $where[] = 'cc.email=?';
        $params[] = $email;
    }
    $sql = 'SELECT COUNT(*) FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function mg_public_campaign_package_user(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare('SELECT id,email,display_name,full_name FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$merchantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $merchantId, 'roles' => []];
    try {
        $roleStmt = $pdo->prepare('SELECT r.slug FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?');
        $roleStmt->execute([$merchantId]);
        $user['roles'] = array_values(array_filter(array_map('strval', $roleStmt->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable) {
        $user['roles'] = [];
    }
    return $user;
}

function mg_public_campaign_enforce_crm_contact_limit(PDO $pdo, int $merchantId, string $email, bool $isNewCampaignContact): void
{
    if (!$isNewCampaignContact) return;
    $email = strtolower(trim($email));
    $existingEmail = $pdo->prepare('SELECT id FROM campaign_contacts WHERE merchant_user_id=? AND email=? LIMIT 1');
    $existingEmail->execute([$merchantId, $email]);
    if ($existingEmail->fetchColumn()) return;
    $user = mg_public_campaign_package_user($pdo, $merchantId);
    $current = $pdo->prepare("SELECT COUNT(DISTINCT email) FROM campaign_contacts WHERE merchant_user_id=? AND email<>''");
    $current->execute([$merchantId]);
    mg_package_require_limit_available($pdo, $user, 'max_crm_contacts', (int)$current->fetchColumn(), 'CRM contact limit reached.');
}

function mg_public_campaign_enforce_monthly_stamp_limit(PDO $pdo, int $merchantId): void
{
    $user = mg_public_campaign_package_user($pdo, $merchantId);
    $context = mg_user_package_context($pdo, $user);
    $limit = mg_package_limit_value($context, 'monthly_stamps_included');
    if ($limit === null || $limit === '') return;
    $current = $pdo->prepare("SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND status<>'cancelled' AND issued_at>=?");
    $current->execute([$merchantId, gmdate('Y-m-01 00:00:00')]);
    if ((int)$current->fetchColumn() < max(0, (int)$limit)) return;
    if ((bool)mg_package_limit_value($context, 'stamp_overage_enabled')) return;
    mg_fail('Monthly stamp limit reached. Upgrade your package or enable stamp overage.', 402);
}

function mg_public_campaign_enforce_reward_limits(PDO $pdo, array $campaign, ?int $userId, string $email): void
{
    $merchantId = (int) ($campaign['merchant_user_id'] ?? 0);
    if ($merchantId > 0) mg_public_campaign_enforce_monthly_stamp_limit($pdo, $merchantId);
    $campaignId = (int) ($campaign['id'] ?? 0);
    $templateId = (int) ($campaign['reward_template_db_id'] ?? $campaign['reward_template_id'] ?? 0);
    $campaignLimit = max(1, (int) ($campaign['per_user_limit'] ?? 1));
    $templateLimit = max(1, (int) ($campaign['reward_template_per_user_limit'] ?? 1));
    if (mg_public_campaign_limit_count($pdo, 'campaign_id', $campaignId, $userId, $email) >= $campaignLimit) {
        mg_fail('Campaign reward limit reached for this customer.', 409);
    }
    if ($templateId > 0 && mg_public_campaign_limit_count($pdo, 'reward_template_id', $templateId, $userId, $email) >= $templateLimit) {
        mg_fail('Reward template limit reached for this customer.', 409);
    }
}

function mg_public_campaign_debit_reward_stamp(PDO $pdo, array $campaign, string $walletPublicId, string $sourceType, array $metadata = []): array
{
    $merchantId = (int)($campaign['merchant_user_id'] ?? 0);
    if ($merchantId < 1) mg_fail('Campaign merchant is unavailable.', 409);

    return mg_stamp_debit_send($pdo, $merchantId, $merchantId, 'direct_reward_send', 'campaign-reward:' . $walletPublicId, [
        'source_type' => 'public_campaign_reward',
        'source_id' => $walletPublicId,
        'reference' => (string)($campaign['public_id'] ?? $sourceType),
        'reason_code' => 'campaign_reward_issue',
        'note' => 'Campaign reward issued via ' . $sourceType,
        'metadata' => $metadata + [
            'campaign_id' => (string)($campaign['public_id'] ?? ''),
            'campaign_type' => (string)($campaign['campaign_type'] ?? ''),
            'reward_template_id' => (string)($campaign['reward_template_public_id'] ?? ''),
            'source_type' => $sourceType,
        ],
    ]);
}
