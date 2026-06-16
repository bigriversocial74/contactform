<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/profiles.php';

const MG_PROFILE_MODERATION_DEFAULT_LIMIT = 24;
const MG_PROFILE_MODERATION_MAX_LIMIT = 50;

function mg_profile_moderation_case_statuses(): array
{
    return ['open', 'in_review', 'actioned', 'resolved', 'dismissed', 'appealed'];
}

function mg_profile_moderation_categories(): array
{
    return ['impersonation', 'harassment', 'spam', 'fraud', 'unsafe_content', 'copyright', 'privacy', 'policy', 'other'];
}

function mg_profile_moderation_priorities(): array
{
    return ['low', 'normal', 'high', 'urgent'];
}

function mg_profile_moderation_sources(): array
{
    return ['admin', 'user_report', 'automated', 'appeal'];
}

function mg_profile_moderation_actions(): array
{
    return ['claim', 'note', 'warn', 'hide', 'suspend', 'restore', 'dismiss', 'escalate', 'appeal_accept', 'appeal_deny'];
}

function mg_profile_moderation_reason_codes(): array
{
    return ['impersonation', 'harassment', 'spam', 'fraud', 'unsafe_content', 'copyright', 'privacy', 'policy_violation', 'insufficient_evidence', 'owner_remediated', 'appeal_upheld', 'appeal_denied', 'other'];
}

function mg_profile_moderation_public_id(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function mg_profile_moderation_access(array $user): array
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    $super = in_array('super_admin', $roles, true);
    $manage = $super || in_array('admin.profiles.moderation.manage', $permissions, true);
    return [
        'view' => $manage || in_array('admin.profiles.moderation.view', $permissions, true),
        'manage' => $manage,
        'users' => $super || in_array('admin.users.view', $permissions, true),
        'audit' => $super || in_array('admin.audit.view', $permissions, true),
        'super_admin' => $super,
    ];
}

function mg_profile_moderation_require_view(): array
{
    $user = mg_require_api_user();
    if (!mg_profile_moderation_access($user)['view']) {
        mg_security_log('warning', 'profile.moderation.view_denied', 'Profile moderation view denied.', [], (int)$user['id']);
        mg_fail('Permission denied.', 403);
    }
    return $user;
}

function mg_profile_moderation_require_manage(): array
{
    $user = mg_require_api_user();
    if (!mg_profile_moderation_access($user)['manage']) {
        mg_audit('profile.moderation.manage_denied', 'public_profile', [], (int)$user['id']);
        mg_security_log('warning', 'profile.moderation.manage_denied', 'Profile moderation mutation denied.', [], (int)$user['id']);
        mg_fail('Permission denied.', 403);
    }
    return $user;
}

function mg_profile_moderation_limit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => MG_PROFILE_MODERATION_DEFAULT_LIMIT]]);
    return max(1, min((int)$limit, MG_PROFILE_MODERATION_MAX_LIMIT));
}

function mg_profile_moderation_page(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    return max(1, min((int)$page, 10000));
}

function mg_profile_moderation_enum(mixed $value, array $allowed, ?string $default = null): ?string
{
    $candidate = strtolower(trim((string)$value));
    if ($candidate === '') return $default;
    if (!in_array($candidate, $allowed, true)) throw new InvalidArgumentException('Invalid filter value.');
    return $candidate;
}

function mg_profile_moderation_text(mixed $value, int $max, bool $required = false): string
{
    $text = trim((string)$value);
    if ($required && $text === '') throw new InvalidArgumentException('A value is required.');
    if (mb_strlen($text) > $max) throw new InvalidArgumentException('The supplied value is too long.');
    return $text;
}

function mg_profile_moderation_json(mixed $value): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) throw new InvalidArgumentException('Evidence must be an object or list.');
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 20000) throw new InvalidArgumentException('Evidence is too large.');
    return $encoded;
}

function mg_profile_moderation_decode_json(mixed $value): ?array
{
    if (!is_string($value) || trim($value) === '') return null;
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function mg_profile_moderation_profile(PDO $pdo, string $reference, bool $forUpdate = false): array
{
    $reference = trim($reference);
    if ($reference === '' || strlen($reference) > 140) throw new InvalidArgumentException('Profile reference is required.');
    $stmt = $pdo->prepare(
        'SELECT pp.*,u.status AS user_status
         FROM public_profiles pp
         INNER JOIN users u ON u.id=pp.user_id
         WHERE pp.public_id=? OR pp.slug=?
         LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$reference, strtolower($reference)]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) throw new RuntimeException('Profile not found.');
    return $profile;
}

function mg_profile_moderation_case(PDO $pdo, string $casePublicId, bool $forUpdate = false): array
{
    $casePublicId = trim($casePublicId);
    if ($casePublicId === '' || strlen($casePublicId) > 40) throw new InvalidArgumentException('Case reference is required.');
    $stmt = $pdo->prepare('SELECT * FROM profile_moderation_cases WHERE public_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$casePublicId]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) throw new RuntimeException('Moderation case not found.');
    return $case;
}

function mg_profile_moderation_public_case_row(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'source' => (string)$row['source'],
        'category' => (string)$row['category'],
        'priority' => (string)$row['priority'],
        'status' => (string)$row['status'],
        'summary' => (string)$row['summary'],
        'details' => $row['details'] !== null ? (string)$row['details'] : null,
        'opened_at' => (string)$row['opened_at'],
        'reviewed_at' => $row['reviewed_at'] !== null ? (string)$row['reviewed_at'] : null,
        'resolved_at' => $row['resolved_at'] !== null ? (string)$row['resolved_at'] : null,
        'updated_at' => (string)$row['updated_at'],
        'assigned_to' => $row['assigned_user_id'] !== null ? [
            'id' => (int)$row['assigned_user_id'],
            'name' => (string)($row['assigned_name'] ?? 'Moderator'),
        ] : null,
        'profile' => [
            'id' => (string)$row['profile_public_id'],
            'slug' => (string)$row['slug'],
            'display_name' => (string)$row['display_name'],
            'headline' => $row['headline'] !== null ? (string)$row['headline'] : null,
            'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null,
            'type' => (string)$row['profile_type'],
            'visibility' => (string)$row['visibility'],
            'status' => (string)$row['profile_status'],
            'completion_score' => (int)$row['completion_score'],
        ],
        'action_count' => (int)($row['action_count'] ?? 0),
        'appeal_status' => $row['appeal_status'] !== null ? (string)$row['appeal_status'] : null,
    ];
}
