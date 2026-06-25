<?php
declare(strict_types=1);

function mg_reward_user_email_verified(PDO $pdo, int $userId): bool
{
    if ($userId < 1) return false;
    $stmt = $pdo->prepare('SELECT email_verified_at FROM users WHERE id=? AND status=\'active\' LIMIT 1');
    $stmt->execute([$userId]);
    return (string)($stmt->fetchColumn() ?: '') !== '';
}

function mg_reward_require_verified_email(PDO $pdo, int $userId, string $action = 'use this reward'): void
{
    if (mg_reward_user_email_verified($pdo, $userId)) return;
    mg_fail('Please verify your email before you ' . $action . '.', 403);
}
