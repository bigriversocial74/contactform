<?php
declare(strict_types=1);

require_once __DIR__ . '/_base.php';

function mg_profile_moderation_queue(PDO $pdo, array $user, array $input): array
{
    $access = mg_profile_moderation_access($user);
    if (!$access['view']) throw new RuntimeException('Permission denied.');

    $limit = mg_profile_moderation_limit($input['limit'] ?? null);
    $page = mg_profile_moderation_page($input['page'] ?? null);
    $offset = ($page - 1) * $limit;
    $where = [];
    $params = [];

    $status = strtolower(trim((string)($input['status'] ?? 'active')));
    if ($status === 'active') {
        $where[] = "c.status IN ('open','in_review','actioned','appealed')";
    } elseif ($status !== '' && $status !== 'all') {
        $where[] = 'c.status=?';
        $params[] = mg_profile_moderation_enum($status, mg_profile_moderation_case_statuses());
    }

    foreach (['priority' => mg_profile_moderation_priorities(), 'category' => mg_profile_moderation_categories(), 'source' => mg_profile_moderation_sources()] as $field => $allowed) {
        $value = strtolower(trim((string)($input[$field] ?? '')));
        if ($value !== '' && $value !== 'all') {
            $where[] = 'c.' . $field . '=?';
            $params[] = mg_profile_moderation_enum($value, $allowed);
        }
    }

    $assignee = strtolower(trim((string)($input['assignee'] ?? 'all')));
    if ($assignee === 'me') {
        $where[] = 'c.assigned_user_id=?';
        $params[] = (int)$user['id'];
    } elseif ($assignee === 'unassigned') {
        $where[] = 'c.assigned_user_id IS NULL';
    } elseif (!in_array($assignee, ['', 'all'], true)) {
        throw new InvalidArgumentException('Invalid assignee filter.');
    }

    $query = trim((string)($input['q'] ?? ''));
    if ($query !== '') {
        if (mb_strlen($query) > 120) throw new InvalidArgumentException('Search query is too long.');
        $where[] = "(pp.slug LIKE ? ESCAPE '=' OR pp.display_name LIKE ? ESCAPE '=' OR pp.public_id=? OR c.public_id=?)";
        $escaped = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $query);
        $like = '%' . $escaped . '%';
        array_push($params, $like, $like, $query, $query);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM profile_moderation_cases c
         INNER JOIN public_profiles pp ON pp.id=c.profile_id' . $whereSql
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql =
        'SELECT c.*,pp.public_id AS profile_public_id,pp.slug,pp.display_name,pp.headline,pp.avatar_url,pp.profile_type,pp.visibility,pp.status AS profile_status,pp.completion_score,
                COALESCE(NULLIF(au.display_name,\'\'),NULLIF(au.full_name,\'\'),\'Moderator\') AS assigned_name,
                (SELECT COUNT(*) FROM profile_moderation_actions ma WHERE ma.case_id=c.id) AS action_count,
                (SELECT pa.status FROM profile_moderation_appeals pa WHERE pa.case_id=c.id ORDER BY pa.submitted_at DESC,pa.id DESC LIMIT 1) AS appeal_status
         FROM profile_moderation_cases c
         INNER JOIN public_profiles pp ON pp.id=c.profile_id
         LEFT JOIN users au ON au.id=c.assigned_user_id' . $whereSql .
        " ORDER BY FIELD(c.priority,'urgent','high','normal','low'),FIELD(c.status,'appealed','open','in_review','actioned','resolved','dismissed'),c.opened_at ASC,c.id ASC
         LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_map('mg_profile_moderation_public_case_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $summaryStmt = $pdo->query(
        "SELECT
          COUNT(*) AS total,
          SUM(status='open') AS open_count,
          SUM(status='in_review') AS review_count,
          SUM(status='appealed') AS appealed_count,
          SUM(status='actioned') AS actioned_count,
          SUM(priority='urgent' AND status IN ('open','in_review','actioned','appealed')) AS urgent_count,
          SUM(assigned_user_id IS NULL AND status IN ('open','in_review','actioned','appealed')) AS unassigned_count
         FROM profile_moderation_cases"
    );
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'access' => $access,
        'summary' => [
            'total' => (int)($summary['total'] ?? 0),
            'open' => (int)($summary['open_count'] ?? 0),
            'in_review' => (int)($summary['review_count'] ?? 0),
            'appealed' => (int)($summary['appealed_count'] ?? 0),
            'actioned' => (int)($summary['actioned_count'] ?? 0),
            'urgent' => (int)($summary['urgent_count'] ?? 0),
            'unassigned' => (int)($summary['unassigned_count'] ?? 0),
        ],
        'cases' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit)),
        ],
        'filters' => [
            'statuses' => mg_profile_moderation_case_statuses(),
            'priorities' => mg_profile_moderation_priorities(),
            'categories' => mg_profile_moderation_categories(),
            'sources' => mg_profile_moderation_sources(),
        ],
    ];
}

function mg_profile_moderation_open_case(PDO $pdo, array $user, array $input): array
{
    if (!mg_profile_moderation_access($user)['manage']) throw new RuntimeException('Permission denied.');
    $reference = mg_profile_moderation_text($input['profile'] ?? $input['profile_ref'] ?? '', 140, true);
    $category = mg_profile_moderation_enum($input['category'] ?? null, mg_profile_moderation_categories(), 'other');
    $priority = mg_profile_moderation_enum($input['priority'] ?? null, mg_profile_moderation_priorities(), 'normal');
    $summary = mg_profile_moderation_text($input['summary'] ?? '', 220, true);
    $details = mg_profile_moderation_text($input['details'] ?? '', 10000);
    $evidence = mg_profile_moderation_json($input['evidence'] ?? null);

    $pdo->beginTransaction();
    try {
        $profile = mg_profile_moderation_profile($pdo, $reference, true);
        $duplicate = $pdo->prepare("SELECT public_id FROM profile_moderation_cases WHERE profile_id=? AND category=? AND status IN ('open','in_review','actioned','appealed') LIMIT 1 FOR UPDATE");
        $duplicate->execute([(int)$profile['id'], $category]);
        if ($duplicate->fetchColumn()) throw new DomainException('An active case already exists for this profile and category.');

        $publicId = mg_profile_moderation_public_id('pmc');
        $insert = $pdo->prepare(
            "INSERT INTO profile_moderation_cases
             (public_id,profile_id,opened_by_user_id,source,category,priority,status,summary,details,evidence_json,opened_at,created_at,updated_at)
             VALUES (?,?,?,'admin',?,?,'open',?,?,?,NOW(),NOW(),NOW())"
        );
        $insert->execute([$publicId, (int)$profile['id'], (int)$user['id'], $category, $priority, $summary, $details !== '' ? $details : null, $evidence]);
        $caseId = (int)$pdo->lastInsertId();

        $action = $pdo->prepare(
            "INSERT INTO profile_moderation_actions
             (public_id,case_id,profile_id,actor_user_id,actor_type,action_type,reason_code,reason_text,previous_profile_status,resulting_profile_status,created_at)
             VALUES (?,?,?,?, 'moderator','case_opened',?,?,?, ?,NOW())"
        );
        $action->execute([
            mg_profile_moderation_public_id('pma'), $caseId, (int)$profile['id'], (int)$user['id'],
            $category, $summary, (string)$profile['status'], (string)$profile['status'],
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_audit('profile.moderation.case_opened', 'public_profile', ['case_id' => $publicId, 'profile_id' => (string)$profile['public_id'], 'category' => $category, 'priority' => $priority], (int)$user['id']);
    mg_event('profile.moderation.case_opened', ['case_id' => $publicId, 'profile_id' => (string)$profile['public_id'], 'category' => $category], (int)$user['id']);
    require_once __DIR__ . '/_case.php';
    return mg_profile_moderation_detail($pdo, $user, $publicId);
}
