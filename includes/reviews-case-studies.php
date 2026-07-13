<?php
declare(strict_types=1);

function mg_rcs_uuid(): string
{
    if (function_exists('mg_public_uuid')) return mg_public_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_rcs_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_rcs_admin_user(): array
{
    $user = mg_current_user();
    if (!$user) mg_fail('Authentication required.', 401);
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    $allowed = in_array('super_admin', $roles, true)
        || in_array('admin.profiles.moderation.manage', $permissions, true)
        || in_array('admin.profiles.moderation.view', $permissions, true)
        || in_array('admin.users.manage', $permissions, true);
    if (!$allowed) mg_fail('Administrative permission required.', 403);
    return $user;
}

function mg_rcs_admin_can_manage(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array('super_admin', $roles, true)
        || in_array('admin.profiles.moderation.manage', $permissions, true)
        || in_array('admin.users.manage', $permissions, true);
}

function mg_rcs_json(mixed $value): ?string
{
    if ($value === null) return null;
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_rcs_decode(mixed $value, mixed $fallback = []): mixed
{
    if (!is_string($value) || trim($value) === '') return $fallback;
    $decoded = json_decode($value, true);
    return $decoded === null && strtolower(trim($value)) !== 'null' ? $fallback : $decoded;
}

function mg_rcs_audit(PDO $pdo, array $event): void
{
    if (!mg_rcs_table_exists($pdo, 'review_case_study_audit')) return;
    $stmt = $pdo->prepare('INSERT INTO review_case_study_audit (public_id,actor_user_id,merchant_user_id,review_id,case_study_id,action,before_json,after_json,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_rcs_uuid(),
        (int)($event['actor_user_id'] ?? 0),
        !empty($event['merchant_user_id']) ? (int)$event['merchant_user_id'] : null,
        !empty($event['review_id']) ? (int)$event['review_id'] : null,
        !empty($event['case_study_id']) ? (int)$event['case_study_id'] : null,
        mb_substr((string)($event['action'] ?? 'unknown'), 0, 120),
        mg_rcs_json($event['before'] ?? null),
        mg_rcs_json($event['after'] ?? null),
        mg_rcs_json($event['metadata'] ?? null),
    ]);
}

function mg_rcs_review_payload(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'merchant_user_id' => (int)$row['merchant_user_id'],
        'profile_id' => (int)$row['profile_id'],
        'campaign_id' => (int)$row['campaign_id'],
        'reviewer_user_id' => (int)$row['reviewer_user_id'],
        'reviewer_name' => (string)$row['reviewer_name'],
        'rating' => (int)$row['rating'],
        'title' => $row['review_title'] !== null ? (string)$row['review_title'] : null,
        'body' => (string)$row['review_body'],
        'status' => (string)$row['status'],
        'featured_on_profile' => !empty($row['featured_on_profile']),
        'featured_in_case_study' => !empty($row['featured_in_case_study']),
        'submitted_at' => (string)$row['submitted_at'],
        'merchant_name' => (string)($row['merchant_name'] ?? ''),
        'profile_slug' => (string)($row['profile_slug'] ?? ''),
        'reply' => !empty($row['reply_public_id']) ? [
            'id' => (string)$row['reply_public_id'],
            'body' => (string)$row['reply_body'],
            'status' => (string)$row['reply_status'],
            'created_at' => (string)$row['reply_created_at'],
            'updated_at' => (string)$row['reply_updated_at'],
        ] : null,
    ];
}
