<?php
declare(strict_types=1);

function mg_personal_workflows_schedules(PDO $pdo, int $userId, string $status = 'all'): array
{
    mg_personal_workflows_require_schema($pdo);
    $allowed = ['all','draft','approved','paused','prepared','completed','cancelled'];
    if (!in_array($status, $allowed, true)) $status = 'all';
    $where = $status === 'all' ? '' : ' AND s.status=?';
    $params = [$userId];
    if ($status !== 'all') $params[] = $status;
    $stmt = $pdo->prepare("SELECT s.public_id,s.scheduled_for,s.timezone,s.status,s.execution_mode,s.approval_required,s.prepared_at,s.completed_at,s.created_at,s.updated_at,
        p.public_id plan_public_id,p.title plan_title,p.target_date,p.currency,p.budget_min,p.budget_max
        FROM user_gifting_schedules s
        INNER JOIN user_gifting_plans p ON p.id=s.plan_id AND p.owner_user_id=s.owner_user_id
        WHERE s.owner_user_id=?{$where}
        ORDER BY FIELD(s.status,'approved','draft','prepared','paused','completed','cancelled'),s.scheduled_for,s.id");
    $stmt->execute($params);
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],
        'scheduled_for'=>(string)$row['scheduled_for'],
        'timezone'=>(string)$row['timezone'],
        'status'=>(string)$row['status'],
        'execution_mode'=>(string)$row['execution_mode'],
        'approval_required'=>(bool)$row['approval_required'],
        'prepared_at'=>$row['prepared_at'] ?: null,
        'completed_at'=>$row['completed_at'] ?: null,
        'plan'=>[
            'id'=>(string)$row['plan_public_id'],
            'title'=>(string)$row['plan_title'],
            'target_date'=>$row['target_date'] ?: null,
            'budget_min'=>$row['budget_min'] !== null ? (float)$row['budget_min'] : null,
            'budget_max'=>$row['budget_max'] !== null ? (float)$row['budget_max'] : null,
            'currency'=>(string)$row['currency'],
        ],
        'created_at'=>$row['created_at'],
        'updated_at'=>$row['updated_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_workflows_recurring_programs(PDO $pdo, int $userId, string $status = 'all'): array
{
    mg_personal_workflows_require_schema($pdo);
    $allowed = ['all','draft','active','paused','completed','cancelled'];
    if (!in_array($status, $allowed, true)) $status = 'all';
    $where = $status === 'all' ? '' : ' AND rp.status=?';
    $params = [$userId];
    if ($status !== 'all') $params[] = $status;
    $stmt = $pdo->prepare("SELECT rp.*,l.public_id list_public_id,l.name list_name,c.public_id contact_public_id,c.display_name contact_name,
        u.public_id linked_public_id,COALESCE(pp.display_name,u.display_name,u.full_name) linked_name
        FROM user_recurring_gift_programs rp
        LEFT JOIN user_contact_lists l ON l.id=rp.list_id AND l.owner_user_id=rp.owner_user_id
        LEFT JOIN user_contacts c ON c.id=rp.user_contact_id AND c.owner_user_id=rp.owner_user_id
        LEFT JOIN users u ON u.id=rp.contact_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=rp.contact_user_id
        WHERE rp.owner_user_id=?{$where}
        ORDER BY FIELD(rp.status,'active','draft','paused','completed','cancelled'),rp.next_run_at,rp.id");
    $stmt->execute($params);
    return array_map(static function(array $row): array {
        $context=['type'=>'none','id'=>'','name'=>''];
        if (!empty($row['contact_public_id'])) $context=['type'=>'contact','id'=>(string)$row['contact_public_id'],'name'=>(string)$row['contact_name']];
        elseif (!empty($row['linked_public_id'])) $context=['type'=>'linked_user','id'=>(string)$row['linked_public_id'],'name'=>(string)$row['linked_name']];
        elseif (!empty($row['list_public_id'])) $context=['type'=>'list','id'=>(string)$row['list_public_id'],'name'=>(string)$row['list_name']];
        return [
            'id'=>(string)$row['public_id'],
            'title'=>(string)$row['title'],
            'occasion_type'=>(string)$row['occasion_type'],
            'occasion_label'=>(string)($row['occasion_label'] ?? ''),
            'cadence'=>(string)$row['cadence'],
            'interval_count'=>(int)$row['interval_count'],
            'next_run_at'=>(string)$row['next_run_at'],
            'end_at'=>$row['end_at'] ?: null,
            'budget_min'=>mg_personal_workflows_money($row['budget_min_cents'] !== null ? (int)$row['budget_min_cents'] : null),
            'budget_max'=>mg_personal_workflows_money($row['budget_max_cents'] !== null ? (int)$row['budget_max_cents'] : null),
            'currency'=>(string)$row['currency'],
            'status'=>(string)$row['status'],
            'generation_mode'=>(string)$row['generation_mode'],
            'run_sequence'=>(int)$row['run_sequence'],
            'last_generated_at'=>$row['last_generated_at'] ?: null,
            'notes'=>(string)($row['notes'] ?? ''),
            'context'=>$context,
            'created_at'=>$row['created_at'],
            'updated_at'=>$row['updated_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
