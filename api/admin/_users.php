<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

const MG_ADMIN_USERS_DEFAULT_LIMIT = 25;
const MG_ADMIN_USERS_MAX_LIMIT = 50;

function mg_admin_users_require_user(): array
{
    return mg_require_permission('admin.users.view');
}

function mg_admin_users_text(mixed $value, int $maxLength): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (mb_strlen($text) > $maxLength) {
        throw new InvalidArgumentException('Invalid user directory filters.');
    }
    return $text;
}

function mg_admin_users_filters(array $input): array
{
    $query = mb_strtolower(mg_admin_users_text($input['q'] ?? '', 160));
    $status = mb_strtolower(mg_admin_users_text($input['status'] ?? '', 20));
    $role = mb_strtolower(mg_admin_users_text($input['role'] ?? '', 80));
    $verification = mb_strtolower(mg_admin_users_text($input['verification'] ?? '', 20));

    if ($status !== '' && !in_array($status, ['active', 'disabled', 'pending'], true)) {
        throw new InvalidArgumentException('Invalid user directory filters.');
    }
    if ($role !== '' && preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $role) !== 1) {
        throw new InvalidArgumentException('Invalid user directory filters.');
    }
    if ($verification !== '' && !in_array($verification, ['verified', 'unverified'], true)) {
        throw new InvalidArgumentException('Invalid user directory filters.');
    }

    return compact('query', 'status', 'role', 'verification');
}

function mg_admin_users_limit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['default' => MG_ADMIN_USERS_DEFAULT_LIMIT],
    ]);
    return max(1, min((int)$limit, MG_ADMIN_USERS_MAX_LIMIT));
}

function mg_admin_users_cursor(mixed $value): ?int
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^[1-9][0-9]{0,19}$/', $value) !== 1) {
        throw new InvalidArgumentException('Invalid pagination cursor.');
    }
    $cursor = filter_var($value, FILTER_VALIDATE_INT);
    if ($cursor === false || $cursor < 1) {
        throw new InvalidArgumentException('Invalid pagination cursor.');
    }
    return (int)$cursor;
}

function mg_admin_users_item(array $row): array
{
    $roles = array_values(array_filter(array_map(
        static fn(string $role): string => trim($role),
        explode(',', (string)($row['role_slugs'] ?? ''))
    )));

    return [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'full_name' => (string)$row['full_name'],
        'display_name' => (string)($row['display_name'] ?? $row['full_name']),
        'status' => (string)$row['status'],
        'email_verified_at' => $row['email_verified_at'] !== null ? (string)$row['email_verified_at'] : null,
        'created_at' => (string)$row['created_at'],
        'roles' => $roles,
        'profile' => $row['profile_public_id'] !== null ? [
            'id' => (string)$row['profile_public_id'],
            'slug' => (string)$row['profile_slug'],
            'display_name' => (string)$row['profile_display_name'],
            'profile_type' => (string)$row['profile_type'],
            'visibility' => (string)$row['profile_visibility'],
            'status' => (string)$row['profile_status'],
            'url' => '/profile.php?slug=' . rawurlencode((string)$row['profile_slug']),
        ] : null,
    ];
}

function mg_admin_users_read(PDO $pdo, array $input): array
{
    $filters = mg_admin_users_filters($input);
    $limit = mg_admin_users_limit($input['limit'] ?? MG_ADMIN_USERS_DEFAULT_LIMIT);
    $cursor = mg_admin_users_cursor($input['cursor'] ?? null);
    $params = [];

    $sql = "SELECT
      u.id,u.email,u.full_name,u.display_name,u.status,u.email_verified_at,u.created_at,
      pp.public_id AS profile_public_id,pp.slug AS profile_slug,pp.display_name AS profile_display_name,
      pp.profile_type,pp.visibility AS profile_visibility,pp.status AS profile_status,
      GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ',') AS role_slugs
    FROM users u
    LEFT JOIN public_profiles pp ON pp.user_id=u.id
    LEFT JOIN user_roles ur ON ur.user_id=u.id
    LEFT JOIN roles r ON r.id=ur.role_id
    WHERE 1=1";

    if ($filters['query'] !== '') {
        $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['query']) . '%';
        $sql .= " AND (
          LOWER(u.email) LIKE ? ESCAPE '!'
          OR LOWER(u.full_name) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(u.display_name,'')) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(pp.display_name,'')) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(pp.slug,'')) LIKE ? ESCAPE '!'
        )";
        array_push($params, $needle, $needle, $needle, $needle, $needle);
    }
    if ($filters['status'] !== '') {
        $sql .= ' AND u.status=?';
        $params[] = $filters['status'];
    }
    if ($filters['role'] !== '') {
        $sql .= ' AND EXISTS(SELECT 1 FROM user_roles urf INNER JOIN roles rf ON rf.id=urf.role_id WHERE urf.user_id=u.id AND rf.slug=?)';
        $params[] = $filters['role'];
    }
    if ($filters['verification'] === 'verified') {
        $sql .= ' AND u.email_verified_at IS NOT NULL';
    } elseif ($filters['verification'] === 'unverified') {
        $sql .= ' AND u.email_verified_at IS NULL';
    }
    if ($cursor !== null) {
        $sql .= ' AND u.id<?';
        $params[] = $cursor;
    }

    $sql .= ' GROUP BY u.id,u.email,u.full_name,u.display_name,u.status,u.email_verified_at,u.created_at,pp.public_id,pp.slug,pp.display_name,pp.profile_type,pp.visibility,pp.status';
    $sql .= ' ORDER BY u.id DESC LIMIT ' . ($limit + 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }

    $items = array_map('mg_admin_users_item', $rows);
    $nextCursor = $hasMore && $rows !== [] ? (string)$rows[array_key_last($rows)]['id'] : null;

    return [
        'items' => $items,
        'next_cursor' => $nextCursor,
        'has_more' => $hasMore,
        'limit' => $limit,
        'filters' => $filters,
    ];
}
