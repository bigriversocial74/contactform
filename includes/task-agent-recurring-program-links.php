<?php
declare(strict_types=1);

function mg_task_agent_recurring_available_programs(PDO $pdo,int $userId,int $limit=40): array
{
    if(!mg_task_agent_recurring_schema_ready($pdo))return [];
    $stmt=$pdo->prepare('SELECT rp.public_id FROM user_recurring_gift_programs rp LEFT JOIN multi_agent_recurring_program_links link ON link.program_id=rp.id AND link.owner_user_id=rp.owner_user_id WHERE rp.owner_user_id=? AND link.id IS NULL ORDER BY FIELD(rp.status,\'active\',\'draft\',\'paused\',\'completed\',\'cancelled\'),rp.next_run_at,rp.id LIMIT '.max(1,min(80,$limit)));
    $stmt->execute([$userId]);
    $available=array_fill_keys(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)),true);
    if(!$available)return [];
    return array_values(array_filter(
        mg_personal_workflows_recurring_programs($pdo,$userId,'all'),
        static fn(array $program):bool=>isset($available[(string)($program['id']??'')])
    ));
}

function mg_task_agent_recurring_link_existing(PDO $pdo,int $userId,int $agentId,string $programPublicId): array
{
    mg_task_agent_recurring_require_schema($pdo);
    $stmt=$pdo->prepare('SELECT id FROM user_recurring_gift_programs WHERE owner_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$userId,$programPublicId]);
    $programId=(int)($stmt->fetchColumn()?:0);
    if($programId<1)throw new RuntimeException('Recurring gift program not found.');
    try{
        $pdo->prepare('INSERT INTO multi_agent_recurring_program_links(public_id,agent_id,owner_user_id,program_id,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
            ->execute([mg_public_uuid(),$agentId,$userId,$programId]);
    }catch(PDOException $error){
        if((string)$error->getCode()!=='23000')throw $error;
        $existing=$pdo->prepare('SELECT agent_id FROM multi_agent_recurring_program_links WHERE owner_user_id=? AND program_id=? LIMIT 1');
        $existing->execute([$userId,$programId]);
        if((int)$existing->fetchColumn()!==$agentId)throw new RuntimeException('This recurring program is already connected to another agent.');
    }
    mg_audit('multi_agent.recurring_program_existing_linked','agent',[
        'agent_id'=>$agentId,'program_id'=>$programPublicId,'used_ai'=>false,'commerce_executed'=>false,
    ],$userId);
    return mg_task_agent_recurring_program($pdo,$userId,$agentId,$programPublicId);
}

function mg_task_agent_recurring_link_card(array $program): array
{
    $context=is_array($program['context']??null)?$program['context']:[];
    return [
        'type'=>'recurring_program_link',
        'title'=>(string)($program['title']??'Existing recurring program'),
        'body'=>'Use the existing Personal Agent program for '.((string)($context['name']??'general gifting')).'. No program data will be copied.',
        'program'=>[
            'id'=>(string)($program['id']??''),
            'status'=>(string)($program['status']??''),
            'cadence'=>(string)($program['cadence']??''),
            'interval_count'=>(int)($program['interval_count']??1),
            'next_run_at'=>(string)($program['next_run_at']??''),
            'context_name'=>(string)($context['name']??''),
        ],
        'action'=>'link_recurring_program',
        'action_label'=>'Use with this agent',
        'review_payload'=>['program_id'=>(string)($program['id']??'')],
        'canonical_reuse'=>true,
        'commerce_executed'=>false,
    ];
}
