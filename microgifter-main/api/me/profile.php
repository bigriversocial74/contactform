<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/security.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'PATCH'], true)) {
    mg_fail('Method not allowed.', 405);
}

$user = mg_require_api_user();
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.full_name, u.display_name, u.status, u.email_verified_at,
                p.avatar_url, p.headline, p.bio, p.created_at AS profile_created_at, p.updated_at AS profile_updated_at
         FROM users u
         LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $user['id']]);
    $profile = $stmt->fetch();
    mg_ok(['profile' => $profile], 'Profile loaded.');
}

$input = mg_input();
mg_require_csrf_for_write($input);

$userId = (int) $user['id'];
$ip = mg_client_ip() ?: 'unknown';
$profileMax = (int) mg_config_value('security', 'rate_limit_profile_max', 20);
$profileWindow = (int) mg_config_value('security', 'rate_limit_profile_window', 3600);
mg_rate_limit('profile_update_ip', $ip, $profileMax, $profileWindow);
mg_rate_limit('profile_update_user', (string) $userId, $profileMax, $profileWindow);

$allowedFields = ['display_name', 'headline', 'bio', 'avatar_url'];
$changedFields = array_values(array_intersect($allowedFields, array_keys($input)));
if ($changedFields === []) {
    mg_fail('At least one supported profile field is required.', 422);
}

$displayName = array_key_exists('display_name', $input) ? trim((string) $input['display_name']) : null;
$headline = array_key_exists('headline', $input) ? trim((string) $input['headline']) : null;
$bio = array_key_exists('bio', $input) ? trim((string) $input['bio']) : null;
$avatarUrl = array_key_exists('avatar_url', $input) ? trim((string) $input['avatar_url']) : null;

if ($displayName !== null && mb_strlen($displayName) > 160) {
    mg_security_log('warning', 'profile.validation_failed', 'Display name too long.', ['field' => 'display_name'], $userId);
    mg_fail('Display name is too long.', 422);
}
if ($headline !== null && mb_strlen($headline) > 180) {
    mg_security_log('warning', 'profile.validation_failed', 'Headline too long.', ['field' => 'headline'], $userId);
    mg_fail('Headline is too long.', 422);
}
if ($bio !== null && mb_strlen($bio) > 5000) {
    mg_security_log('warning', 'profile.validation_failed', 'Bio too long.', ['field' => 'bio'], $userId);
    mg_fail('Bio is too long.', 422);
}
if ($avatarUrl !== null && $avatarUrl !== '' && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
    mg_security_log('warning', 'profile.validation_failed', 'Invalid avatar URL.', ['field' => 'avatar_url'], $userId);
    mg_fail('Avatar URL must be a valid URL.', 422);
}

$pdo->beginTransaction();
try {
    $existingStmt = $pdo->prepare('SELECT avatar_url, headline, bio FROM user_profiles WHERE user_id = ? LIMIT 1 FOR UPDATE');
    $existingStmt->execute([$userId]);
    $existingProfile = $existingStmt->fetch() ?: ['avatar_url' => null, 'headline' => null, 'bio' => null];

    if ($displayName !== null) {
        $stmt = $pdo->prepare('UPDATE users SET display_name = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$displayName !== '' ? $displayName : null, $userId]);
    }

    $resolvedAvatarUrl = array_key_exists('avatar_url', $input)
        ? ($avatarUrl !== '' ? $avatarUrl : null)
        : ($existingProfile['avatar_url'] ?? null);
    $resolvedHeadline = array_key_exists('headline', $input)
        ? ($headline !== '' ? $headline : null)
        : ($existingProfile['headline'] ?? null);
    $resolvedBio = array_key_exists('bio', $input)
        ? ($bio !== '' ? $bio : null)
        : ($existingProfile['bio'] ?? null);

    $stmt = $pdo->prepare(
        'INSERT INTO user_profiles (user_id, avatar_url, headline, bio, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
           avatar_url = VALUES(avatar_url),
           headline = VALUES(headline),
           bio = VALUES(bio),
           updated_at = NOW()'
    );
    $stmt->execute([
        $userId,
        $resolvedAvatarUrl,
        $resolvedHeadline,
        $resolvedBio,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'profile.update_failed', 'Profile update failed.', ['exception' => $e->getMessage()], $userId);
    mg_fail('Unable to update profile.', 500);
}

mg_audit('profile_updated', 'user_profile', ['fields' => $changedFields], $userId);
mg_event('user.profile.updated', ['user_id' => $userId, 'fields' => $changedFields], $userId);
mg_security_log('info', 'profile.updated', 'Profile updated.', ['fields' => $changedFields], $userId);

$fresh = mg_refresh_session_user();

mg_ok([
    'user' => $fresh,
], 'Profile updated.');
