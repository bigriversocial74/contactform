<?php
declare(strict_types=1);

function mg_creator_campaign_creator_directory(
    PDO $pdo,
    array $user,
    array $filters = []
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_directory.view'
    );
    $search = trim((string) ($filters['search'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
    $where = [
        "u.status='active'",
        "cp.status='active'",
        "uma.status='active'",
        "um.code='creator'",
    ];
    $params = [];
    if ($search !== '') {
        $where[] = '(cp.display_name LIKE ? OR cp.slug LIKE ? OR u.display_name LIKE ? OR u.full_name LIKE ? OR pp.headline LIKE ? OR pp.location_label LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $base = ' FROM creator_profiles cp
              INNER JOIN users u ON u.id=cp.user_id
              INNER JOIN user_models um ON um.code=\'creator\'
              INNER JOIN user_model_assignments uma ON uma.user_id=cp.user_id AND uma.user_model_id=um.id
              LEFT JOIN public_profiles pp ON pp.user_id=cp.user_id
              WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $base);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT cp.public_id creator_profile_public_id,
                COALESCE(cp.display_name,u.display_name,u.full_name) display_name,
                cp.slug,cp.bio,pp.headline,pp.avatar_url,pp.location_label,pp.website_url,pp.completion_score'
        . $base .
        ' ORDER BY COALESCE(cp.display_name,u.display_name,u.full_name),cp.id
          LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
        'workspace_id' => (int) $context['workspace_id'],
    ];
}

function mg_creator_campaign_invitation_expire_due(PDO $pdo, ?int $creatorUserId = null): int
{
    $sql = "SELECT * FROM creator_campaign_invitations
            WHERE status='pending' AND response_deadline_at IS NOT NULL AND response_deadline_at<NOW()";
    $params = [];
    if ($creatorUserId !== null) {
        $sql .= ' AND creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' ORDER BY id LIMIT 500 FOR UPDATE';

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $update = $pdo->prepare(
            "UPDATE creator_campaign_invitations
             SET status='expired',responded_at=NOW(),lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND status='pending'"
        );
        $count = 0;
        foreach ($rows as $row) {
            $update->execute([(int) $row['id']]);
            if ($update->rowCount() !== 1) continue;
            $count++;
            mg_creator_campaign_participation_event($pdo, [
                'campaign_id' => (int) $row['campaign_id'],
                'invitation_id' => (int) $row['id'],
                'actor_user_id' => (int) $row['updated_by_user_id'],
                'event_type' => 'invitation.expired',
                'from_status' => 'pending',
                'to_status' => 'expired',
                'reason' => 'Response deadline passed.',
            ]);
        }
        if ($ownsTransaction) $pdo->commit();
        return $count;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

