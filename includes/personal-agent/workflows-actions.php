<?php
declare(strict_types=1);

function mg_personal_workflows_create_schedule(PDO $pdo, int $userId, array $input): array
{
    mg_personal_workflows_require_schema($pdo);
    $planPublicId=mg_personal_agent_text($input['plan_id'] ?? '',80);
    if($planPublicId==='') throw new InvalidArgumentException('Choose a gifting plan.');
    $stmt=$pdo->prepare("SELECT id,status FROM user_gifting_plans WHERE owner_user_id=? AND public_id=? LIMIT 1");
    $stmt->execute([$userId,$planPublicId]);
    $plan=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$plan) throw new RuntimeException('Gifting plan not found.');
    if(in_array((string)$plan['status'],['completed','cancelled'],true)) throw new RuntimeException('Completed or cancelled plans cannot be scheduled.');
    $scheduledFor=mg_personal_agent_datetime($input['scheduled_for'] ?? '');
    $timezone=mg_personal_agent_text($input['timezone'] ?? 'UTC',64) ?: 'UTC';
    try { new DateTimeZone($timezone); } catch(Throwable) { throw new InvalidArgumentException('Choose a valid timezone.'); }
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO user_gifting_schedules
        (public_id,owner_user_id,plan_id,scheduled_for,timezone,status,execution_mode,approval_required,created_at,updated_at)
        VALUES (?,?,?,?,?,'draft','prepare_only',1,NOW(),NOW())")
        ->execute([$publicId,$userId,(int)$plan['id'],$scheduledFor,$timezone]);
    mg_audit('user_gifting_schedule.created','user_gifting_schedule',['schedule_id'=>$publicId,'plan_id'=>$planPublicId,'execution_mode'=>'prepare_only'],$userId);
    mg_event('user_gifting_schedule.created',['schedule_id'=>$publicId,'plan_id'=>$planPublicId,'approval_required'=>true],$userId);
    foreach(mg_personal_workflows_schedules($pdo,$userId,'all') as $row) if($row['id']===$publicId) return $row;
    throw new RuntimeException('Unable to load the gifting schedule.');
}

function mg_personal_workflows_update_schedule(PDO $pdo, int $userId, string $publicId, string $action): array
{
    mg_personal_workflows_require_schema($pdo);
    $action=mg_personal_agent_text($action,30);
    $map=['approve'=>'approved','pause'=>'paused','resume'=>'approved','prepare'=>'prepared','complete'=>'completed','cancel'=>'cancelled'];
    if(!isset($map[$action])) throw new InvalidArgumentException('Invalid schedule action.');
    $stmt=$pdo->prepare('SELECT id,status FROM user_gifting_schedules WHERE owner_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$userId,$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Gifting schedule not found.');
    $current=(string)$row['status'];
    $allowed=['draft'=>['approve','cancel'],'approved'=>['pause','prepare','cancel'],'paused'=>['resume','cancel'],'prepared'=>['complete','cancel'],'completed'=>[],'cancelled'=>[]];
    if(!in_array($action,$allowed[$current] ?? [],true)) throw new RuntimeException('That schedule transition is not allowed.');
    $next=$map[$action];
    $pdo->prepare("UPDATE user_gifting_schedules SET status=?,prepared_at=IF(?='prepared',NOW(),prepared_at),completed_at=IF(?='completed',NOW(),completed_at),cancelled_at=IF(?='cancelled',NOW(),cancelled_at),updated_at=NOW() WHERE id=? AND owner_user_id=?")
        ->execute([$next,$next,$next,$next,(int)$row['id'],$userId]);
    mg_audit('user_gifting_schedule.status_updated','user_gifting_schedule',['schedule_id'=>$publicId,'from'=>$current,'to'=>$next,'commerce_executed'=>false],$userId);
    foreach(mg_personal_workflows_schedules($pdo,$userId,'all') as $item) if($item['id']===$publicId) return $item;
    throw new RuntimeException('Gifting schedule not found.');
}

function mg_personal_workflows_create_recurring_program(PDO $pdo, int $userId, array $input): array
{
    mg_personal_workflows_require_schema($pdo);
    $context=mg_personal_workflows_context($pdo,$userId,$input);
    if(!in_array((string)($context['type'] ?? 'none'),['none','contact','linked_user','list'],true)) throw new InvalidArgumentException('Recurring programs require a contact, linked user, list, or no selected context.');
    $ids=mg_personal_workflows_context_columns($context);
    $title=mg_personal_agent_text($input['title'] ?? '',190);
    if($title==='') throw new InvalidArgumentException('Program title is required.');
    $cadence=mg_personal_agent_text($input['cadence'] ?? 'yearly',20);
    if(!in_array($cadence,['weekly','monthly','quarterly','yearly','custom'],true)) throw new InvalidArgumentException('Choose a valid recurring cadence.');
    $interval=max(1,min(52,(int)($input['interval_count'] ?? 1)));
    $nextRun=mg_personal_agent_datetime($input['next_run_at'] ?? '');
    $endAt=trim((string)($input['end_at'] ?? ''))!=='' ? mg_personal_agent_datetime($input['end_at']) : null;
    if($endAt!==null && strtotime($endAt)<=strtotime($nextRun)) throw new InvalidArgumentException('Program end date must be after the first run.');
    $budgetMin=mg_personal_workflows_cents($input['budget_min'] ?? null);
    $budgetMax=mg_personal_workflows_cents($input['budget_max'] ?? null);
    if($budgetMin!==null && $budgetMax!==null && $budgetMin>$budgetMax) throw new InvalidArgumentException('Minimum budget cannot exceed maximum budget.');
    $currency=mg_personal_agent_currency($input['currency'] ?? 'USD');
    $occasionType=mg_personal_agent_text($input['occasion_type'] ?? 'general',64) ?: 'general';
    $occasionLabel=mg_personal_agent_nullable_text($input['occasion_label'] ?? null,160);
    $notes=mg_personal_agent_nullable_text($input['notes'] ?? null,5000);
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO user_recurring_gift_programs
        (public_id,owner_user_id,list_id,user_contact_id,contact_user_id,title,occasion_type,occasion_label,cadence,interval_count,next_run_at,end_at,budget_min_cents,budget_max_cents,currency,status,generation_mode,run_sequence,notes,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft','draft_plan_only',0,?,NOW(),NOW())")
        ->execute([$publicId,$userId,$ids['list_id'],$ids['user_contact_id'],$ids['contact_user_id'],$title,$occasionType,$occasionLabel,$cadence,$interval,$nextRun,$endAt,$budgetMin,$budgetMax,$currency,$notes]);
    mg_audit('user_recurring_gift_program.created','user_recurring_gift_program',['program_id'=>$publicId,'cadence'=>$cadence,'generation_mode'=>'draft_plan_only','context_type'=>$context['type']],$userId);
    foreach(mg_personal_workflows_recurring_programs($pdo,$userId,'all') as $row) if($row['id']===$publicId) return $row;
    throw new RuntimeException('Unable to load recurring gift program.');
}

function mg_personal_workflows_update_recurring_program(PDO $pdo, int $userId, string $publicId, string $action): array
{
    $map=['activate'=>'active','pause'=>'paused','resume'=>'active','complete'=>'completed','cancel'=>'cancelled'];
    if(!isset($map[$action])) throw new InvalidArgumentException('Invalid recurring program action.');
    $stmt=$pdo->prepare('SELECT id,status FROM user_recurring_gift_programs WHERE owner_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$userId,$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Recurring gift program not found.');
    $current=(string)$row['status'];
    $allowed=['draft'=>['activate','cancel'],'active'=>['pause','complete','cancel'],'paused'=>['resume','complete','cancel'],'completed'=>[],'cancelled'=>[]];
    if(!in_array($action,$allowed[$current] ?? [],true)) throw new RuntimeException('That recurring-program transition is not allowed.');
    $next=$map[$action];
    $pdo->prepare('UPDATE user_recurring_gift_programs SET status=?,updated_at=NOW() WHERE id=? AND owner_user_id=?')->execute([$next,(int)$row['id'],$userId]);
    mg_audit('user_recurring_gift_program.status_updated','user_recurring_gift_program',['program_id'=>$publicId,'from'=>$current,'to'=>$next],$userId);
    foreach(mg_personal_workflows_recurring_programs($pdo,$userId,'all') as $item) if($item['id']===$publicId) return $item;
    throw new RuntimeException('Recurring gift program not found.');
}

function mg_personal_workflows_generate_recurring_draft(PDO $pdo, int $userId, string $publicId): array
{
    mg_personal_workflows_require_schema($pdo);
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare("SELECT rp.*,l.public_id list_public_id,c.public_id contact_public_id,u.public_id linked_public_id
            FROM user_recurring_gift_programs rp
            LEFT JOIN user_contact_lists l ON l.id=rp.list_id AND l.owner_user_id=rp.owner_user_id
            LEFT JOIN user_contacts c ON c.id=rp.user_contact_id AND c.owner_user_id=rp.owner_user_id
            LEFT JOIN users u ON u.id=rp.contact_user_id
            WHERE rp.owner_user_id=? AND rp.public_id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId,$publicId]);
        $program=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$program) throw new RuntimeException('Recurring gift program not found.');
        if(!in_array((string)$program['status'],['draft','active'],true)) throw new RuntimeException('Only draft or active programs can generate a draft.');
        $scheduledFor=(string)$program['next_run_at'];
        if(!empty($program['end_at']) && strtotime($scheduledFor)>strtotime((string)$program['end_at'])) {
            $pdo->prepare("UPDATE user_recurring_gift_programs SET status='completed',updated_at=NOW() WHERE id=?")->execute([(int)$program['id']]);
            throw new RuntimeException('Recurring gift program has reached its end date.');
        }
        $sequence=(int)$program['run_sequence']+1;
        $idempotency=hash('sha256',$userId.'|'.$program['id'].'|'.$sequence.'|'.$scheduledFor);
        $existing=$pdo->prepare('SELECT p.public_id FROM user_recurring_gift_runs r LEFT JOIN user_gifting_plans p ON p.id=r.plan_id WHERE r.idempotency_key=? LIMIT 1');
        $existing->execute([$idempotency]);
        $existingPlan=$existing->fetchColumn();
        if($existingPlan) { $pdo->commit(); return ['created'=>false,'plan_id'=>(string)$existingPlan,'run_sequence'=>$sequence]; }
        $contextType='none';$contextId='';
        if(!empty($program['contact_public_id'])) {$contextType='contact';$contextId=(string)$program['contact_public_id'];}
        elseif(!empty($program['linked_public_id'])) {$contextType='linked_user';$contextId=(string)$program['linked_public_id'];}
        elseif(!empty($program['list_public_id'])) {$contextType='list';$contextId=(string)$program['list_public_id'];}
        $targetDate=(new DateTimeImmutable($scheduledFor,new DateTimeZone('UTC')))->format('Y-m-d');
        $plan=mg_personal_agent_create_plan($pdo,$userId,[
            'context_type'=>$contextType,'context_id'=>$contextId,'title'=>(string)$program['title'].' · Run '.$sequence,
            'occasion_type'=>(string)$program['occasion_type'],'occasion_label'=>(string)($program['occasion_label'] ?? ''),'target_date'=>$targetDate,
            'budget_min'=>mg_personal_workflows_money($program['budget_min_cents']!==null?(int)$program['budget_min_cents']:null),
            'budget_max'=>mg_personal_workflows_money($program['budget_max_cents']!==null?(int)$program['budget_max_cents']:null),
            'currency'=>(string)$program['currency'],'notes'=>(string)($program['notes'] ?? ''),'source'=>'agent',
            'recommendation'=>['phase3_source'=>'recurring_program','program_id'=>$publicId,'run_sequence'=>$sequence,'commerce_executed'=>false],
        ]);
        $planInternal=$pdo->prepare('SELECT id FROM user_gifting_plans WHERE owner_user_id=? AND public_id=? LIMIT 1');
        $planInternal->execute([$userId,$plan['id']]);
        $planId=(int)$planInternal->fetchColumn();
        $pdo->prepare("INSERT INTO user_recurring_gift_runs (public_id,program_id,owner_user_id,run_sequence,scheduled_for,plan_id,status,idempotency_key,generated_at,created_at,updated_at)
            VALUES (?,?,?,?,?,?,'draft_created',?,NOW(),NOW(),NOW())")
            ->execute([mg_public_uuid(),(int)$program['id'],$userId,$sequence,$scheduledFor,$planId,$idempotency]);
        $nextRun=mg_personal_workflows_next_run($scheduledFor,(string)$program['cadence'],(int)$program['interval_count']);
        $nextStatus=(!empty($program['end_at']) && strtotime($nextRun)>strtotime((string)$program['end_at']))?'completed':((string)$program['status']==='draft'?'draft':'active');
        $pdo->prepare('UPDATE user_recurring_gift_programs SET run_sequence=?,last_generated_at=NOW(),next_run_at=?,status=?,updated_at=NOW() WHERE id=?')
            ->execute([$sequence,$nextRun,$nextStatus,(int)$program['id']]);
        mg_audit('user_recurring_gift_program.draft_generated','user_recurring_gift_program',['program_id'=>$publicId,'run_sequence'=>$sequence,'plan_id'=>$plan['id'],'commerce_executed'=>false],$userId);
        $pdo->commit();
        return ['created'=>true,'plan'=>$plan,'run_sequence'=>$sequence,'next_run_at'=>$nextRun,'program_status'=>$nextStatus];
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function mg_personal_workflows_expand_list_plan(PDO $pdo, int $userId, string $planPublicId): array
{
    mg_personal_workflows_require_schema($pdo);
    $stmt=$pdo->prepare("SELECT p.id,p.status,p.list_id FROM user_gifting_plans p WHERE p.owner_user_id=? AND p.public_id=? LIMIT 1");
    $stmt->execute([$userId,$planPublicId]);
    $plan=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$plan) throw new RuntimeException('Gifting plan not found.');
    if(empty($plan['list_id'])) throw new RuntimeException('This plan is not connected to a list.');
    if(in_array((string)$plan['status'],['completed','cancelled'],true)) throw new RuntimeException('Completed or cancelled plans cannot be expanded.');
    $members=$pdo->prepare('SELECT contact_user_id,user_contact_id FROM user_contact_list_members WHERE owner_user_id=? AND list_id=? ORDER BY id');
    $members->execute([$userId,(int)$plan['list_id']]);
    $added=0;$skipped=0;
    foreach($members->fetchAll(PDO::FETCH_ASSOC) as $member) {
        $contactUserId=$member['contact_user_id']!==null?(int)$member['contact_user_id']:null;
        $userContactId=$member['user_contact_id']!==null?(int)$member['user_contact_id']:null;
        if($contactUserId!==null) { $eligibility=mg_user_contact_list_eligibility_detail($pdo,$userId,$contactUserId); if(empty($eligibility['eligible'])) {$skipped++;continue;} }
        $insert=$pdo->prepare("INSERT IGNORE INTO user_gifting_plan_members (public_id,plan_id,owner_user_id,user_contact_id,contact_user_id,role_key,status,created_at,updated_at) VALUES (?,?,?,?,?,'recipient','draft',NOW(),NOW())");
        $insert->execute([mg_public_uuid(),(int)$plan['id'],$userId,$userContactId,$contactUserId]);
        if($insert->rowCount()===1)$added++;else$skipped++;
    }
    mg_audit('user_gifting_plan.list_snapshot_created','user_gifting_plan',['plan_id'=>$planPublicId,'added'=>$added,'skipped'=>$skipped],$userId);
    return ['plan_id'=>$planPublicId,'added'=>$added,'skipped'=>$skipped,'approval_required'=>true];
}
