<?php
declare(strict_types=1);

function mg_public_campaign_policy_mode(array $rules, string $campaignType = ''): string
{
    $mode = strtolower(trim((string)($rules['participation_policy'] ?? $rules['account_policy'] ?? '')));
    $allowed = ['email_only', 'account_recommended', 'account_required'];
    if (!in_array($mode, $allowed, true)) {
        $mode = in_array($campaignType, ['listen_music_reward', 'watch_video_reward'], true) ? 'account_recommended' : 'email_only';
    }
    return $mode;
}

function mg_public_campaign_policy_current_user(): ?array
{
    if (!function_exists('mg_current_user')) return null;
    $user = mg_current_user();
    return is_array($user) && !empty($user['id']) ? $user : null;
}

function mg_public_campaign_policy_find_user(PDO $pdo, string $email): ?int
{
    $email = strtolower(trim($email));
    if ($email === '') return null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_public_campaign_policy_resolve(PDO $pdo, array $rules, string $campaignType, string $email): array
{
    $mode = mg_public_campaign_policy_mode($rules, $campaignType);
    $email = strtolower(trim($email));
    $currentUser = mg_public_campaign_policy_current_user();
    $sessionUserId = is_array($currentUser) ? (int)($currentUser['id'] ?? 0) : 0;
    $sessionEmail = is_array($currentUser) ? strtolower(trim((string)($currentUser['email'] ?? ''))) : '';

    if ($mode === 'account_required') {
        if ($sessionUserId < 1) {
            mg_fail('A Microgifter account is required to participate in this campaign.', 401);
        }
        if ($sessionEmail !== '' && $email !== '' && $sessionEmail !== $email) {
            mg_fail('Use the email on your signed-in Microgifter account to participate in this campaign.', 403);
        }
        return [
            'mode' => $mode,
            'user_id' => $sessionUserId,
            'signed_in' => true,
            'matched_by' => 'session',
            'email' => $sessionEmail !== '' ? $sessionEmail : $email,
        ];
    }

    $matchedBy = 'email';
    $userId = null;
    if ($sessionUserId > 0 && ($sessionEmail === '' || $email === '' || $sessionEmail === $email)) {
        $userId = $sessionUserId;
        $matchedBy = 'session';
    } else {
        $userId = mg_public_campaign_policy_find_user($pdo, $email);
    }

    return [
        'mode' => $mode,
        'user_id' => $userId,
        'signed_in' => $sessionUserId > 0,
        'matched_by' => $matchedBy,
        'email' => $email,
    ];
}

function mg_public_campaign_policy_public_payload(array $rules, string $campaignType, bool $signedIn): array
{
    $mode = mg_public_campaign_policy_mode($rules, $campaignType);
    return [
        'mode' => $mode,
        'signed_in' => $signedIn,
        'account_required' => $mode === 'account_required',
        'account_recommended' => $mode === 'account_recommended',
    ];
}
