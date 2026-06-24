<?php
declare(strict_types=1);

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

function mg_public_campaign_enforce_reward_limits(PDO $pdo, array $campaign, ?int $userId, string $email): void
{
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
