<?php
declare(strict_types=1);

function mg_investment_pipeline_admin_user_audited(PDO $pdo, mixed $userId): ?int
{
    $id = (int)$userId;
    if ($id < 1) return null;
    $q = $pdo->prepare('SELECT COUNT(*) FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE u.id=? AND r.slug IN ("admin","super_admin")');
    $q->execute([$id]);
    if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('Assigned owner must be an active Admin or Super Admin.', 409);
    return $id;
}

function mg_investment_pipeline_dashboard_audited(PDO $pdo, array $filters = []): array
{
    $stage = trim((string)($filters['stage'] ?? ''));
    $priority = trim((string)($filters['priority'] ?? ''));
    $search = trim((string)($filters['q'] ?? ''));
    $allowedStages = ['','approved','qualified','contacted','meeting_scheduled','due_diligence','interested','soft_committed','signed','funded','passed','declined','archived'];
    $allowedPriorities = ['','low','normal','high','critical'];
    if (!in_array($stage, $allowedStages, true) || !in_array($priority, $allowedPriorities, true)) {
        throw new MgInvestmentException('Invalid pipeline dashboard filter.');
    }

    $params = [];
    $where = ['ip.status="active"'];
    if ($stage !== '') { $where[] = 'pr.stage=?'; $params[] = $stage; }
    if ($priority !== '') { $where[] = 'pr.priority=?'; $params[] = $priority; }
    if ($search !== '') {
        $where[] = '(u.email LIKE ? OR u.full_name LIKE ? OR u.display_name LIKE ? OR ip.firm_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $sql = 'SELECT pr.public_id,pr.stage,pr.priority,pr.qualification_score,pr.source,pr.capacity_range,pr.tags_json,pr.last_contact_at,pr.next_follow_up_at,pr.updated_at,pr.investor_user_id,
                   ip.firm_name,ip.job_title,ip.investor_type,ip.expected_investment_range,u.email,u.full_name,u.display_name,au.full_name AS assigned_name,
                   (SELECT COUNT(*) FROM investor_follow_up_tasks t WHERE t.investor_user_id=pr.investor_user_id AND t.status IN ("open","in_progress")) AS open_tasks,
                   (SELECT COUNT(*) FROM investor_follow_up_tasks t WHERE t.investor_user_id=pr.investor_user_id AND t.status IN ("open","in_progress") AND t.due_at<NOW()) AS overdue_tasks,
                   COALESCE((SELECT SUM(ri.soft_commitment_cents) FROM investor_round_interests ri WHERE ri.investor_user_id=pr.investor_user_id),0) AS soft_commitment_cents,
                   COALESCE((SELECT SUM(ri.signed_cents) FROM investor_round_interests ri WHERE ri.investor_user_id=pr.investor_user_id),0) AS signed_cents,
                   COALESCE((SELECT SUM(cr.verified_funded_cents) FROM investor_closing_records cr WHERE cr.investor_user_id=pr.investor_user_id AND cr.status NOT IN ("withdrawn","declined")),0) AS funded_cents
            FROM investor_pipeline_records pr
            INNER JOIN investor_profiles ip ON ip.id=pr.investor_profile_id
            INNER JOIN users u ON u.id=pr.investor_user_id
            LEFT JOIN users au ON au.id=pr.assigned_user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY FIELD(pr.priority,"critical","high","normal","low"),COALESCE(pr.next_follow_up_at,"2999-12-31"),pr.updated_at DESC
            LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['tags'] = mg_investment_json($item['tags_json']);
        unset($item['tags_json']);
    }
    unset($item);

    $summary = $pdo->query('SELECT COUNT(*) AS total,SUM(stage NOT IN ("passed","declined","archived")) AS active,SUM(stage="meeting_scheduled") AS meetings,SUM(stage="due_diligence") AS due_diligence,SUM(stage="soft_committed") AS soft_committed,SUM(stage="signed") AS signed,SUM(stage="funded") AS funded,SUM(next_follow_up_at IS NOT NULL AND next_follow_up_at<NOW() AND stage NOT IN ("passed","declined","archived")) AS overdue FROM investor_pipeline_records')->fetch(PDO::FETCH_ASSOC) ?: [];
    $money = $pdo->query('SELECT COALESCE(SUM(ri.soft_commitment_cents),0) AS soft_commitment_cents,COALESCE(SUM(ri.signed_cents),0) AS signed_cents,COALESCE((SELECT SUM(cr.verified_funded_cents) FROM investor_closing_records cr WHERE cr.status NOT IN ("withdrawn","declined")),0) AS funded_cents FROM investor_round_interests ri')->fetch(PDO::FETCH_ASSOC) ?: [];
    $rounds = $pdo->query('SELECT r.public_id,r.public_name,r.status,r.visibility,r.target_raise_cents,r.funded_cents,w.public_id AS workspace_public_id,COALESCE(p.publication_status,"draft") AS publication_status FROM investment_rounds r INNER JOIN investment_workspaces w ON w.id=r.workspace_id LEFT JOIN investment_round_publication p ON p.round_id=r.id ORDER BY r.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $admins = $pdo->query('SELECT DISTINCT u.id,u.full_name,u.email FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ("admin","super_admin") ORDER BY u.full_name')->fetchAll(PDO::FETCH_ASSOC);

    return ['items'=>$items,'summary'=>array_merge($summary,$money),'rounds'=>$rounds,'admins'=>$admins];
}

function mg_investment_pipeline_save_record_v2_audited(PDO $pdo, array $actor, array $input): array
{
    $record = mg_investment_pipeline_record($pdo, mg_investment_text($input['investor_id'] ?? '', 36, 36, 'Investor identifier'));
    $stage = (string)($input['stage'] ?? 'approved');
    mg_investment_pipeline_admin_user_audited($pdo, $input['assigned_user_id'] ?? 0);

    if ($stage === 'signed') {
        $q = $pdo->prepare('SELECT COALESCE(SUM(signed_cents),0) FROM investor_round_interests WHERE investor_user_id=? AND status NOT IN ("passed","declined","archived")');
        $q->execute([(int)$record['investor_user_id']]);
        if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('The signed pipeline stage requires approved signed money.', 409);
    }
    if ($stage === 'funded') {
        $q = $pdo->prepare('SELECT COALESCE(SUM(verified_funded_cents),0) FROM investor_closing_records WHERE investor_user_id=? AND status NOT IN ("withdrawn","declined")');
        $q->execute([(int)$record['investor_user_id']]);
        if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('The funded pipeline stage requires maker/checker verified funded money.', 409);
    }

    return mg_investment_pipeline_save_record_v2($pdo, $actor, $input);
}

function mg_investment_pipeline_save_task_audited(PDO $pdo, array $actor, array $input): array
{
    mg_investment_pipeline_admin_user_audited($pdo, $input['assigned_user_id'] ?? 0);
    return mg_investment_pipeline_save_task($pdo, $actor, $input);
}
