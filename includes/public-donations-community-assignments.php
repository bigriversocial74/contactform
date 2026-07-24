<?php
declare(strict_types=1);

/**
 * Public Donations Phase 3 domain helpers.
 *
 * This layer deliberately owns assignment relationships only. It never creates,
 * issues, transfers, recalls, or mutates reward inventory.
 */

function mg_public_donations_assignment_fail(string $message, int $status = 422): never
{
    if (function_exists('mg_fail')) mg_fail($message, $status);
    throw new RuntimeException($message, $status);
}

function mg_public_donations_assignment_uuid(): string
{
    if (function_exists('mg_public_uuid')) return mg_public_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_public_donations_assignment_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*)
               FROM information_schema.columns
              WHERE table_schema=DATABASE()
                AND table_name='campaign_community_assignments'
                AND column_name IN ('public_id','merchant_user_id','campaign_id','community_user_id','status','added_by_user_id','reactivated_at','paused_at','removed_at')"
        );
        return $cache[$key] = (int)$stmt->fetchColumn() === 9;
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_public_donations_assignment_limit(mixed $value, int $default = 24, int $maximum = 50): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max(1, min((int)$limit, $maximum));
}

function mg_public_donations_assignment_safe_media_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || mb_strlen($url) > 600 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return null;
    if (!in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) return null;
    if (isset($parts['user']) || isset($parts['pass'])) return null;
    return $url;
}

function mg_public_donations_assignment_role_labels(mixed $csv): array
{
    $blocked = ['community', 'admin', 'super_admin'];
    $roles = [];
    foreach (explode(',', strtolower((string)$csv)) as $slug) {
        $slug = trim($slug);
        if ($slug === '' || in_array($slug, $blocked, true)) continue;
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $slug) !== 1) continue;
        $roles[$slug] = ucwords(str_replace(['_', '-'], ' ', $slug));
    }
    return array_values($roles);
}

function mg_public_donations_assignment_identity(array $row): array
{
    $slug = trim((string)($row['profile_slug'] ?? ''));
    $profileVisible = $slug !== ''
        && (string)($row['profile_status'] ?? '') === 'active'
        && in_array((string)($row['profile_visibility'] ?? ''), ['public', 'unlisted'], true);
    $assignmentId = trim((string)($row['assignment_public_id'] ?? ''));
    $assignmentStatus = trim((string)($row['assignment_status'] ?? ''));

    return [
        'community_account_id' => (string)($row['community_account_id'] ?? ''),
        'display_name' => trim((string)($row['display_name'] ?? '')) ?: 'Community member',
        'username' => $profileVisible ? $slug : null,
        'profile_slug' => $profileVisible ? $slug : null,
        'public_profile_url' => $profileVisible ? '/profile.php?slug=' . rawurlencode($slug) : null,
        'avatar_url' => $profileVisible ? mg_public_donations_assignment_safe_media_url($row['avatar_url'] ?? null) : null,
        'general_location' => $profileVisible && trim((string)($row['location_label'] ?? '')) !== ''
            ? mb_substr(trim((string)$row['location_label']), 0, 160)
            : null,
        'community_badge' => true,
        'other_roles' => mg_public_donations_assignment_role_labels($row['role_slugs'] ?? ''),
        'assignment' => $assignmentId !== '' ? [
            'id' => $assignmentId,
            'status' => in_array($assignmentStatus, ['active', 'paused', 'removed'], true) ? $assignmentStatus : 'removed',
        ] : null,
    ];
}

function mg_public_donations_assignment_campaigns(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT public_id,public_slug,title,status,starts_at,ends_at,updated_at
           FROM campaigns
          WHERE merchant_user_id=? AND campaign_type='public_donation' AND status<>'archived'
          ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END,
                   updated_at DESC,id DESC
          LIMIT 100"
    );
    $stmt->execute([$merchantId]);
    return array_map(static function(array $row): array {
        $slug = trim((string)($row['public_slug'] ?? ''));
        $ref = $slug !== '' ? $slug : (string)$row['public_id'];
        return [
            'id' => (string)$row['public_id'],
            'slug' => $slug !== '' ? $slug : null,
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'starts_at' => $row['starts_at'] !== null ? (string)$row['starts_at'] : null,
            'ends_at' => $row['ends_at'] !== null ? (string)$row['ends_at'] : null,
            'public_url' => '/public-donations.php?campaign=' . rawurlencode($ref),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_public_donations_assignment_campaign(PDO $pdo, int $merchantId, string $campaignRef, bool $forUpdate = false): array
{
    $campaignRef = strtolower(trim($campaignRef));
    if ($campaignRef === '' || mb_strlen($campaignRef) > 120 || preg_match('/^[a-z0-9][a-z0-9-]{0,119}$/', $campaignRef) !== 1) {
        mg_public_donations_assignment_fail('Invalid Public Donations campaign.', 422);
    }
    $stmt = $pdo->prepare(
        "SELECT id,public_id,public_slug,title,status,campaign_type
           FROM campaigns
          WHERE merchant_user_id=? AND (public_id=? OR public_slug=?)
          LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$merchantId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign || (string)$campaign['campaign_type'] !== 'public_donation' || (string)$campaign['status'] === 'archived') {
        mg_public_donations_assignment_fail('Public Donations campaign not found.', 404);
    }
    return $campaign;
}

function mg_public_donations_assignment_search(PDO $pdo, int $merchantId, int $campaignId, string $query = '', int $limit = 24): array
{
    $query = trim($query);
    if (mb_strlen($query) > 120) mg_public_donations_assignment_fail('Community search is too long.', 422);
    $limit = mg_public_donations_assignment_limit($limit);
    $params = [$campaignId];
    $filter = '';
    if ($query !== '') {
        $like = '%' . $query . '%';
        $filter = " AND (
            u.display_name LIKE ? OR u.full_name LIKE ?
            OR ((pp.status='active' AND pp.visibility IN ('public','unlisted'))
                AND (pp.display_name LIKE ? OR pp.slug LIKE ? OR pp.location_label LIKE ?))
        )";
        array_push($params, $like, $like, $like, $like, $like);
    }

    $sql = "SELECT pp.public_id AS community_account_id,
                   CASE
                       WHEN pp.status='active' AND pp.visibility IN ('public','unlisted')
                           THEN COALESCE(NULLIF(pp.display_name,''),NULLIF(u.display_name,''),u.full_name)
                       ELSE COALESCE(NULLIF(u.display_name,''),u.full_name)
                   END AS display_name,
                   pp.slug AS profile_slug,pp.status AS profile_status,pp.visibility AS profile_visibility,
                   pp.avatar_url,pp.location_label,
                   GROUP_CONCAT(DISTINCT role_all.slug ORDER BY role_all.slug SEPARATOR ',') AS role_slugs,
                   assignment.public_id AS assignment_public_id,assignment.status AS assignment_status
              FROM users u
              INNER JOIN public_profiles pp ON pp.user_id=u.id
              INNER JOIN user_roles community_link ON community_link.user_id=u.id
              INNER JOIN roles community_role ON community_role.id=community_link.role_id AND community_role.slug='community'
              LEFT JOIN user_roles role_link ON role_link.user_id=u.id
              LEFT JOIN roles role_all ON role_all.id=role_link.role_id
              LEFT JOIN campaign_community_assignments assignment
                     ON assignment.campaign_id=? AND assignment.community_user_id=u.id
             WHERE u.status='active'{$filter}
             GROUP BY u.id,pp.public_id,pp.display_name,u.display_name,u.full_name,pp.slug,pp.status,pp.visibility,
                      pp.avatar_url,pp.location_label,assignment.public_id,assignment.status
             ORDER BY CASE assignment.status WHEN 'active' THEN 1 WHEN 'paused' THEN 2 WHEN 'removed' THEN 3 ELSE 0 END,
                      display_name ASC,u.id ASC
             LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('mg_public_donations_assignment_identity', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_public_donations_assignment_summary(PDO $pdo, int $merchantId, int $campaignId): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(assignment.status='active'),0) AS active,
                COALESCE(SUM(assignment.status='paused'),0) AS paused,
                COALESCE(SUM(assignment.status='removed'),0) AS removed
           FROM campaign_community_assignments assignment
           INNER JOIN users u ON u.id=assignment.community_user_id AND u.status='active'
          WHERE assignment.merchant_user_id=? AND assignment.campaign_id=?
            AND EXISTS (
                SELECT 1 FROM user_roles community_link
                INNER JOIN roles community_role ON community_role.id=community_link.role_id
                WHERE community_link.user_id=assignment.community_user_id AND community_role.slug='community'
            )"
    );
    $stmt->execute([$merchantId, $campaignId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'total' => (int)($row['total'] ?? 0),
        'active' => (int)($row['active'] ?? 0),
        'paused' => (int)($row['paused'] ?? 0),
        'removed' => (int)($row['removed'] ?? 0),
    ];
}

function mg_public_donations_assignment_list(PDO $pdo, int $merchantId, int $campaignId, string $status = 'all', int $limit = 50): array
{
    $status = strtolower(trim($status));
    if (!in_array($status, ['all', 'active', 'paused', 'removed'], true)) $status = 'all';
    $limit = mg_public_donations_assignment_limit($limit, 50, 100);
    $params = [$merchantId, $campaignId];
    $filter = '';
    if ($status !== 'all') {
        $filter = ' AND assignment.status=?';
        $params[] = $status;
    }
    $stmt = $pdo->prepare(
        "SELECT assignment.public_id AS assignment_public_id,assignment.status AS assignment_status,
                assignment.added_at,assignment.reactivated_at,assignment.paused_at,assignment.removed_at,assignment.last_allocated_at,
                pp.public_id AS community_account_id,
                CASE
                    WHEN pp.status='active' AND pp.visibility IN ('public','unlisted')
                        THEN COALESCE(NULLIF(pp.display_name,''),NULLIF(u.display_name,''),u.full_name)
                    ELSE COALESCE(NULLIF(u.display_name,''),u.full_name)
                END AS display_name,
                pp.slug AS profile_slug,pp.status AS profile_status,pp.visibility AS profile_visibility,
                pp.avatar_url,pp.location_label,
                GROUP_CONCAT(DISTINCT role_all.slug ORDER BY role_all.slug SEPARATOR ',') AS role_slugs
           FROM campaign_community_assignments assignment
           INNER JOIN users u ON u.id=assignment.community_user_id AND u.status='active'
           INNER JOIN public_profiles pp ON pp.user_id=u.id
           INNER JOIN user_roles community_link ON community_link.user_id=u.id
           INNER JOIN roles community_role ON community_role.id=community_link.role_id AND community_role.slug='community'
           LEFT JOIN user_roles role_link ON role_link.user_id=u.id
           LEFT JOIN roles role_all ON role_all.id=role_link.role_id
          WHERE assignment.merchant_user_id=? AND assignment.campaign_id=?{$filter}
          GROUP BY assignment.id,assignment.public_id,assignment.status,assignment.added_at,assignment.reactivated_at,
                   assignment.paused_at,assignment.removed_at,assignment.last_allocated_at,pp.public_id,pp.display_name,
                   u.display_name,u.full_name,pp.slug,pp.status,pp.visibility,pp.avatar_url,pp.location_label
          ORDER BY CASE assignment.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END,
                   assignment.updated_at DESC,assignment.id DESC
          LIMIT {$limit}"
    );
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $identity = mg_public_donations_assignment_identity($row);
        $identity['assignment'] = [
            'id' => (string)$row['assignment_public_id'],
            'status' => (string)$row['assignment_status'],
            'added_at' => $row['added_at'] !== null ? (string)$row['added_at'] : null,
            'reactivated_at' => $row['reactivated_at'] !== null ? (string)$row['reactivated_at'] : null,
            'paused_at' => $row['paused_at'] !== null ? (string)$row['paused_at'] : null,
            'removed_at' => $row['removed_at'] !== null ? (string)$row['removed_at'] : null,
            'last_allocated_at' => $row['last_allocated_at'] !== null ? (string)$row['last_allocated_at'] : null,
        ];
        $items[] = $identity;
    }
    return $items;
}

function mg_public_donations_assignment_target(PDO $pdo, string $communityAccountId, bool $forUpdate = false): array
{
    $communityAccountId = trim($communityAccountId);
    if ($communityAccountId === '' || mb_strlen($communityAccountId) > 40 || preg_match('/^[A-Za-z0-9_-]{2,40}$/', $communityAccountId) !== 1) {
        mg_public_donations_assignment_fail('Invalid Community account.', 422);
    }
    $stmt = $pdo->prepare(
        "SELECT u.id,pp.public_id,
                CASE
                    WHEN pp.status='active' AND pp.visibility IN ('public','unlisted')
                        THEN COALESCE(NULLIF(pp.display_name,''),NULLIF(u.display_name,''),u.full_name)
                    ELSE COALESCE(NULLIF(u.display_name,''),u.full_name)
                END AS display_name
           FROM users u
           INNER JOIN public_profiles pp ON pp.user_id=u.id
          WHERE pp.public_id=? AND u.status='active'
            AND EXISTS (
                SELECT 1 FROM user_roles community_link
                INNER JOIN roles community_role ON community_role.id=community_link.role_id
                WHERE community_link.user_id=u.id AND community_role.slug='community'
            )
          LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$communityAccountId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) mg_public_donations_assignment_fail('Active Community account not found.', 404);
    return $target;
}

function mg_public_donations_assignment_notification(PDO $pdo, array $campaign, array $target, string $assignmentPublicId, int $actorId): string
{
    if (!function_exists('mg_create_notification')) return '';
    $slug = trim((string)($campaign['public_slug'] ?? ''));
    $ref = $slug !== '' ? $slug : (string)$campaign['public_id'];
    return mg_create_notification(
        $pdo,
        (int)$target['id'],
        'public_donations.community_added',
        'Added to a Public Donations campaign',
        'You were connected to “' . mb_substr((string)$campaign['title'], 0, 180) . '”. No reward inventory has been issued yet.',
        '/public-donations.php?campaign=' . rawurlencode($ref),
        [
            'actor_user_id' => $actorId,
            'allow_self' => true,
            'merchant_user_id' => (int)($campaign['merchant_user_id'] ?? 0),
            'campaign_public_id' => (string)$campaign['public_id'],
            'assignment_public_id' => $assignmentPublicId,
            'event_key' => 'public-donations.assignment.' . strtolower($assignmentPublicId) . '.' . bin2hex(random_bytes(6)),
        ]
    );
}

function mg_public_donations_assignment_mutate(
    PDO $pdo,
    int $merchantId,
    int $actorId,
    string $campaignRef,
    string $action,
    string $communityAccountId = '',
    string $assignmentPublicId = ''
): array {
    $action = strtolower(trim($action));
    if (!in_array($action, ['add', 'pause', 'remove', 'reactivate'], true)) {
        mg_public_donations_assignment_fail('Invalid Community assignment action.', 422);
    }
    $assignmentPublicId = strtolower(trim($assignmentPublicId));
    if ($assignmentPublicId !== '' && (strlen($assignmentPublicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $assignmentPublicId) !== 1)) {
        mg_public_donations_assignment_fail('Invalid Community assignment.', 422);
    }

    $pdo->beginTransaction();
    try {
        $campaign = mg_public_donations_assignment_campaign($pdo, $merchantId, $campaignRef, true);
        $campaign['merchant_user_id'] = $merchantId;
        if (in_array($action, ['add', 'reactivate'], true) && in_array((string)$campaign['status'], ['ended', 'archived'], true)) {
            mg_public_donations_assignment_fail('Ended campaigns cannot add or reactivate Community accounts.', 409);
        }

        $target = null;
        $assignment = null;
        if ($assignmentPublicId !== '') {
            $stmt = $pdo->prepare(
                "SELECT assignment.*,pp.public_id AS community_account_id,
                        CASE
                            WHEN pp.status='active' AND pp.visibility IN ('public','unlisted')
                                THEN COALESCE(NULLIF(pp.display_name,''),NULLIF(u.display_name,''),u.full_name)
                            ELSE COALESCE(NULLIF(u.display_name,''),u.full_name)
                        END AS display_name
                   FROM campaign_community_assignments assignment
                   INNER JOIN users u ON u.id=assignment.community_user_id
                   INNER JOIN public_profiles pp ON pp.user_id=u.id
                  WHERE assignment.public_id=? AND assignment.merchant_user_id=? AND assignment.campaign_id=?
                  LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$assignmentPublicId, $merchantId, (int)$campaign['id']]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$assignment) mg_public_donations_assignment_fail('Community assignment not found.', 404);
            $target = mg_public_donations_assignment_target($pdo, (string)$assignment['community_account_id'], true);
        } else {
            $target = mg_public_donations_assignment_target($pdo, $communityAccountId, true);
            $stmt = $pdo->prepare(
                'SELECT * FROM campaign_community_assignments WHERE campaign_id=? AND community_user_id=? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([(int)$campaign['id'], (int)$target['id']]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $changed = false;
        $notify = false;
        $currentStatus = (string)($assignment['status'] ?? '');
        $publicId = (string)($assignment['public_id'] ?? '');

        if ($action === 'add') {
            if (!$assignment) {
                $publicId = mg_public_donations_assignment_uuid();
                $stmt = $pdo->prepare(
                    "INSERT INTO campaign_community_assignments
                     (public_id,merchant_user_id,campaign_id,community_user_id,status,public_display_status,added_by_user_id,added_at,created_at,updated_at)
                     VALUES (?,?,?,?, 'active','pending',?,NOW(),NOW(),NOW())"
                );
                $stmt->execute([$publicId, $merchantId, (int)$campaign['id'], (int)$target['id'], $actorId]);
                $changed = true;
                $notify = true;
            } elseif ($currentStatus !== 'active') {
                $pdo->prepare(
                    "UPDATE campaign_community_assignments
                        SET status='active',reactivated_at=NOW(),paused_at=NULL,removed_at=NULL,updated_at=NOW()
                      WHERE id=?"
                )->execute([(int)$assignment['id']]);
                $changed = true;
                $notify = true;
            }
        } elseif ($action === 'reactivate') {
            if (!$assignment) mg_public_donations_assignment_fail('Community assignment not found.', 404);
            if ($currentStatus !== 'active') {
                $pdo->prepare(
                    "UPDATE campaign_community_assignments
                        SET status='active',reactivated_at=NOW(),paused_at=NULL,removed_at=NULL,updated_at=NOW()
                      WHERE id=?"
                )->execute([(int)$assignment['id']]);
                $changed = true;
                $notify = true;
            }
        } elseif ($action === 'pause') {
            if (!$assignment) mg_public_donations_assignment_fail('Community assignment not found.', 404);
            if ($currentStatus !== 'paused') {
                $pdo->prepare(
                    "UPDATE campaign_community_assignments
                        SET status='paused',paused_at=NOW(),updated_at=NOW()
                      WHERE id=?"
                )->execute([(int)$assignment['id']]);
                $changed = true;
            }
        } else {
            if (!$assignment) mg_public_donations_assignment_fail('Community assignment not found.', 404);
            if ($currentStatus !== 'removed') {
                $pdo->prepare(
                    "UPDATE campaign_community_assignments
                        SET status='removed',removed_at=NOW(),updated_at=NOW()
                      WHERE id=?"
                )->execute([(int)$assignment['id']]);
                $changed = true;
            }
        }

        $notificationId = '';
        if ($notify) {
            $notificationId = mg_public_donations_assignment_notification($pdo, $campaign, $target, $publicId, $actorId);
        }
        $pdo->commit();

        if (function_exists('mg_audit')) {
            mg_audit('merchant.public_donations_community_assignment', 'campaign', [
                'campaign_id' => (string)$campaign['public_id'],
                'assignment_id' => $publicId,
                'action' => $action,
                'changed' => $changed,
                'notification_created' => $notificationId !== '',
            ], $actorId);
        }

        return [
            'campaign_id' => (string)$campaign['public_id'],
            'assignment_id' => $publicId,
            'community_account_id' => (string)$target['public_id'],
            'display_name' => (string)$target['display_name'],
            'action' => $action,
            'changed' => $changed,
            'notification_created' => $notificationId !== '',
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
