<?php
declare(strict_types=1);

function mg_task_agent_recurring_schema_ready(PDO $pdo): bool
{
    foreach (['user_recurring_gift_programs','user_recurring_gift_runs','user_gifting_plans','multi_agent_recurring_program_links'] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_task_agent_recurring_require_schema(PDO $pdo): void
{
    if (!mg_task_agent_recurring_schema_ready($pdo)) {
        throw new RuntimeException('Task Agent Phase 4 database migration is required.');
    }
}

function mg_task_agent_recurring_actions(string $status): array
{
    return match ($status) {
        'draft' => ['activate','generate_draft','cancel'],
        'active' => ['generate_draft','skip_next','pause','cancel'],
        'paused' => ['resume','cancel'],
        default => [],
    };
}

function mg_task_agent_recurring_programs(PDO $pdo, int $userId, int $agentId, int $limit = 40): array
{
    if (!mg_task_agent_recurring_schema_ready($pdo)) return [];
    $limit = max(1, min(80, $limit));
    $stmt = $pdo->prepare("SELECT rp.*,l.public_id list_public_id,l.name list_name,
        c.public_id contact_public_id,c.display_name contact_name,
        pp.public_id linked_public_id,COALESCE(pp.display_name,u.display_name,u.full_name) linked_name,
        rr.public_id last_run_public_id,rr.status last_run_status,rr.scheduled_for last_run_scheduled_for,
        gp.public_id last_plan_public_id,gp.title last_plan_title
        FROM multi_agent_recurring_program_links link
        INNER JOIN user_recurring_gift_programs rp ON rp.id=link.program_id AND rp.owner_user_id=link.owner_user_id
        LEFT JOIN user_contact_lists l ON l.id=rp.list_id AND l.owner_user_id=rp.owner_user_id
        LEFT JOIN user_contacts c ON c.id=rp.user_contact_id AND c.owner_user_id=rp.owner_user_id
        LEFT JOIN users u ON u.id=rp.contact_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=rp.contact_user_id
        LEFT JOIN user_recurring_gift_runs rr ON rr.program_id=rp.id AND rr.run_sequence=rp.run_sequence
        LEFT JOIN user_gifting_plans gp ON gp.id=rr.plan_id AND gp.owner_user_id=rp.owner_user_id
        WHERE link.owner_user_id=? AND link.agent_id=?
        ORDER BY FIELD(rp.status,'active','draft','paused','completed','cancelled'),rp.next_run_at,rp.id
        LIMIT {$limit}");
    $stmt->execute([$userId,$agentId]);
    $now = time();
    return array_map(static function(array $row) use ($now): array {
        $context = ['type'=>'none','id'=>'','name'=>''];
        if (!empty($row['contact_public_id'])) {
            $context = ['type'=>'contact','id'=>(string)$row['contact_public_id'],'name'=>(string)$row['contact_name']];
        } elseif (!empty($row['linked_public_id'])) {
            $context = ['type'=>'linked_user','id'=>(string)$row['linked_public_id'],'name'=>(string)$row['linked_name']];
        } elseif (!empty($row['list_public_id'])) {
            $context = ['type'=>'list','id'=>(string)$row['list_public_id'],'name'=>(string)$row['list_name']];
        }
        $status = (string)$row['status'];
        $nextRunAt = (string)$row['next_run_at'];
        return [
            'id'=>(string)$row['public_id'],
            'title'=>(string)$row['title'],
            'occasion_type'=>(string)$row['occasion_type'],
            'occasion_label'=>(string)($row['occasion_label'] ?? ''),
            'cadence'=>(string)$row['cadence'],
            'interval_count'=>(int)$row['interval_count'],
            'next_run_at'=>$nextRunAt,
            'end_at'=>$row['end_at'] ?: null,
            'budget_min'=>mg_personal_workflows_money($row['budget_min_cents'] !== null ? (int)$row['budget_min_cents'] : null),
            'budget_max'=>mg_personal_workflows_money($row['budget_max_cents'] !== null ? (int)$row['budget_max_cents'] : null),
            'currency'=>(string)$row['currency'],
            'status'=>$status,
            'generation_mode'=>(string)$row['generation_mode'],
            'run_sequence'=>(int)$row['run_sequence'],
            'last_generated_at'=>$row['last_generated_at'] ?: null,
            'context'=>$context,
            'due'=>$status === 'active' && strtotime($nextRunAt) <= $now,
            'actions'=>mg_task_agent_recurring_actions($status),
            'last_run'=>empty($row['last_run_public_id']) ? null : [
                'id'=>(string)$row['last_run_public_id'],
                'status'=>(string)$row['last_run_status'],
                'scheduled_for'=>(string)$row['last_run_scheduled_for'],
                'plan'=>empty($row['last_plan_public_id']) ? null : [
                    'id'=>(string)$row['last_plan_public_id'],
                    'title'=>(string)$row['last_plan_title'],
                ],
            ],
            'approval_required'=>true,
            'commerce_executed'=>false,
            'links'=>['manage'=>'/agent.php?view=recurring'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_recurring_program(PDO $pdo, int $userId, int $agentId, string $programPublicId): array
{
    foreach (mg_task_agent_recurring_programs($pdo,$userId,$agentId,80) as $program) {
        if (hash_equals((string)$program['id'],$programPublicId)) return $program;
    }
    throw new RuntimeException('Recurring gift program not found for this agent.');
}

function mg_task_agent_recurring_internal_id(PDO $pdo, int $userId, int $agentId, string $programPublicId): int
{
    mg_task_agent_recurring_require_schema($pdo);
    $stmt=$pdo->prepare('SELECT rp.id FROM multi_agent_recurring_program_links link INNER JOIN user_recurring_gift_programs rp ON rp.id=link.program_id AND rp.owner_user_id=link.owner_user_id WHERE link.owner_user_id=? AND link.agent_id=? AND rp.public_id=? LIMIT 1');
    $stmt->execute([$userId,$agentId,$programPublicId]);
    $id=(int)($stmt->fetchColumn() ?: 0);
    if($id<1) throw new RuntimeException('Recurring gift program not found for this agent.');
    return $id;
}

function mg_task_agent_recurring_create(PDO $pdo, int $userId, int $agentId, array $input): array
{
    mg_task_agent_recurring_require_schema($pdo);
    $title=mg_personal_agent_text($input['title'] ?? '',190);
    $notes=mg_personal_agent_nullable_text($input['notes'] ?? null,5000);
    if($title==='' || mg_personal_workflows_sensitive_text($title.' '.($notes ?? ''))) {
        throw new InvalidArgumentException('Program content cannot contain credentials, payment data, or claim codes.');
    }
    $nextRun=mg_personal_agent_datetime($input['next_run_at'] ?? '');
    if(strtotime($nextRun) < time()-300) throw new InvalidArgumentException('The first recurring review date must be now or in the future.');
    $input['title']=$title;
    $input['notes']=$notes;
    $input['next_run_at']=$nextRun;

    $pdo->beginTransaction();
    try {
        $program=mg_personal_workflows_create_recurring_program($pdo,$userId,$input);
        $stmt=$pdo->prepare('SELECT id FROM user_recurring_gift_programs WHERE owner_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$userId,(string)$program['id']]);
        $programId=(int)($stmt->fetchColumn() ?: 0);
        if($programId<1) throw new RuntimeException('Unable to link the recurring gift program.');
        $pdo->prepare('INSERT INTO multi_agent_recurring_program_links(public_id,agent_id,owner_user_id,program_id,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
            ->execute([mg_public_uuid(),$agentId,$userId,$programId]);
        mg_audit('multi_agent.recurring_program_linked','agent',['agent_id'=>$agentId,'program_id'=>(string)$program['id'],'used_ai'=>false,'commerce_executed'=>false],$userId);
        $pdo->commit();
        return mg_task_agent_recurring_program($pdo,$userId,$agentId,(string)$program['id']);
    } catch(Throwable $error) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_task_agent_recurring_update(PDO $pdo, int $userId, int $agentId, string $programPublicId, string $action, string $expectedStatus = ''): array
{
    mg_task_agent_recurring_internal_id($pdo,$userId,$agentId,$programPublicId);
    $program=mg_personal_workflows_update_recurring_program($pdo,$userId,$programPublicId,$action,$expectedStatus !== '' ? $expectedStatus : null);
    mg_audit('multi_agent.recurring_program_updated','agent',['agent_id'=>$agentId,'program_id'=>$programPublicId,'action'=>$action,'used_ai'=>false,'commerce_executed'=>false],$userId);
    return mg_task_agent_recurring_program($pdo,$userId,$agentId,(string)$program['id']);
}

function mg_task_agent_recurring_generate(PDO $pdo, int $userId, int $agentId, string $programPublicId, string $expectedNextRunAt): array
{
    mg_task_agent_recurring_internal_id($pdo,$userId,$agentId,$programPublicId);
    if($expectedNextRunAt==='') throw new InvalidArgumentException('Refresh the recurring program before generating its next draft.');
    $result=mg_personal_workflows_generate_recurring_draft($pdo,$userId,$programPublicId,$expectedNextRunAt);
    mg_audit('multi_agent.recurring_program_draft_generated','agent',['agent_id'=>$agentId,'program_id'=>$programPublicId,'used_ai'=>false,'commerce_executed'=>false],$userId);
    return ['generation'=>$result,'program'=>mg_task_agent_recurring_program($pdo,$userId,$agentId,$programPublicId)];
}

function mg_task_agent_recurring_skip(PDO $pdo, int $userId, int $agentId, string $programPublicId, string $expectedNextRunAt): array
{
    mg_task_agent_recurring_internal_id($pdo,$userId,$agentId,$programPublicId);
    if($expectedNextRunAt==='') throw new InvalidArgumentException('Refresh the recurring program before skipping its next cycle.');
    $result=mg_personal_workflows_skip_recurring_run($pdo,$userId,$programPublicId,$expectedNextRunAt);
    mg_audit('multi_agent.recurring_program_cycle_skipped','agent',['agent_id'=>$agentId,'program_id'=>$programPublicId,'used_ai'=>false,'commerce_executed'=>false],$userId);
    return ['skip'=>$result,'program'=>mg_task_agent_recurring_program($pdo,$userId,$agentId,$programPublicId)];
}

function mg_task_agent_recurring_card(array $program): array
{
    $context=is_array($program['context']??null)?$program['context']:[];
    $cadence=(int)($program['interval_count']??1).' '.(string)($program['cadence']??'yearly');
    return [
        'type'=>'recurring_gift_program',
        'title'=>(string)($program['title']??'Recurring gift program'),
        'body'=>'Next review: '.(string)($program['next_run_at']??'').'. Cadence: '.$cadence.'. Every occurrence creates a draft plan only.',
        'program'=>[
            'id'=>(string)($program['id']??''),
            'title'=>(string)($program['title']??''),
            'status'=>(string)($program['status']??''),
            'cadence'=>(string)($program['cadence']??''),
            'interval_count'=>(int)($program['interval_count']??1),
            'next_run_at'=>(string)($program['next_run_at']??''),
            'end_at'=>$program['end_at']??null,
            'budget_min'=>$program['budget_min']??null,
            'budget_max'=>$program['budget_max']??null,
            'currency'=>(string)($program['currency']??'USD'),
            'context_name'=>(string)($context['name']??''),
            'due'=>!empty($program['due']),
            'run_sequence'=>(int)($program['run_sequence']??0),
            'actions'=>is_array($program['actions']??null)?$program['actions']:[],
            'last_run'=>$program['last_run']??null,
        ],
        'action'=>'manage_recurring_program',
        'action_label'=>'Manage recurring program',
        'url'=>(string)($program['links']['manage']??'/agent.php?view=recurring'),
        'review_payload'=>[
            'program_id'=>(string)($program['id']??''),
            'expected_status'=>(string)($program['status']??''),
            'expected_next_run_at'=>(string)($program['next_run_at']??''),
        ],
        'approval_required'=>true,
        'generation_mode'=>'draft_plan_only',
        'commerce_executed'=>false,
    ];
}

function mg_task_agent_recurring_for_model(array $programs): array
{
    return array_map(static function(array $program):array{
        $context=is_array($program['context']??null)?$program['context']:[];
        return [
            'title'=>(string)($program['title']??''),
            'status'=>(string)($program['status']??''),
            'cadence'=>(string)($program['cadence']??''),
            'interval_count'=>(int)($program['interval_count']??1),
            'next_run_at'=>(string)($program['next_run_at']??''),
            'end_at'=>(string)($program['end_at']??''),
            'budget_min'=>$program['budget_min']??null,
            'budget_max'=>$program['budget_max']??null,
            'currency'=>(string)($program['currency']??'USD'),
            'context_name'=>(string)($context['name']??''),
            'run_sequence'=>(int)($program['run_sequence']??0),
            'due'=>!empty($program['due']),
            'approval_required'=>true,
            'generation_mode'=>'draft_plan_only',
        ];
    },array_slice($programs,0,8));
}
