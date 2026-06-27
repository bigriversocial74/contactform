<?php
declare(strict_types=1);

function mg_cp_action_clean_email(mixed $email): string
{
    $email = strtolower(trim((string)$email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function mg_cp_action_user_for_email(PDO $pdo, mixed $email, int $fallback = 0): int
{
    if ($fallback > 0) return $fallback;
    $email = mg_cp_action_clean_email($email);
    if ($email === '') return 0;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? LIMIT 1');
    $stmt->execute([$email]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function mg_cp_action_sync_campaign_contact(PDO $pdo, int $merchantId, array $row): array
{
    $userId = mg_cp_action_user_for_email($pdo, $row['email'] ?? '', (int)($row['user_id'] ?? 0));
    if ($userId > 0 && (int)($row['user_id'] ?? 0) <= 0 && !empty($row['id'])) {
        $pdo->prepare('UPDATE campaign_contacts SET user_id=?,updated_at=NOW() WHERE id=? AND merchant_user_id=? AND user_id IS NULL')->execute([$userId, (int)$row['id'], $merchantId]);
        $row['user_id'] = $userId;
    }
    return $row;
}

function mg_cp_action_sync_profile_contact(PDO $pdo, int $merchantId, array $row): array
{
    $email = mg_cp_action_clean_email($row['primary_email'] ?? '');
    $userId = mg_cp_action_user_for_email($pdo, $email, (int)($row['user_id'] ?? 0));
    if ($userId > 0 && (int)($row['user_id'] ?? 0) <= 0 && !empty($row['id'])) {
        $pdo->prepare('UPDATE merchant_crm_contacts SET user_id=?,updated_at=NOW() WHERE id=? AND merchant_user_id=? AND user_id IS NULL')->execute([$userId, (int)$row['id'], $merchantId]);
        $row['user_id'] = $userId;
    }
    if ($userId > 0 && $email !== '') {
        $pdo->prepare('UPDATE campaign_contacts SET user_id=?,updated_at=NOW() WHERE merchant_user_id=? AND user_id IS NULL AND LOWER(email)=?')->execute([$userId, $merchantId, $email]);
    }
    return $row;
}
