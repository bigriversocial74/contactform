<?php
declare(strict_types=1);

function mg_user_restriction(PDO $pdo, int $userId, string $type): ?array
{
    if ($userId < 1 || !in_array($type, ['posting','messaging','uploading','following'], true)) return null;
    $stmt = $pdo->prepare(
        "SELECT public_id,restriction_type,reason,starts_at,ends_at
         FROM user_moderation_restrictions
         WHERE user_id=? AND status='active' AND restriction_type IN (?, 'all')
           AND starts_at<=NOW() AND (ends_at IS NULL OR ends_at>NOW())
         ORDER BY restriction_type='all' DESC,created_at DESC LIMIT 1"
    );
    $stmt->execute([$userId,$type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_require_user_not_restricted(PDO $pdo, int $userId, string $type): void
{
    $restriction = mg_user_restriction($pdo, $userId, $type);
    if (!$restriction) return;
    $label = match ($type) {
        'posting' => 'publishing posts',
        'messaging' => 'sending messages',
        'uploading' => 'uploading media',
        'following' => 'changing social relationships',
    };
    throw new RuntimeException('Your account is currently restricted from ' . $label . '.');
}
