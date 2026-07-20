<?php
declare(strict_types=1);

function mg_task_agent_program_schema_ready(PDO $pdo): bool
{
    foreach (['distribution_programs','distribution_recipients','distribution_program_products','distribution_allocations','multi_agent_distribution_program_links'] as $table) {
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if (!(bool)$stmt->fetchColumn()) return false;
    }
    return true;
}

function mg_task_agent_program_allowed_types(array $agent): array
{
    $template=mg_multi_agent_runtime_template($agent);
    return match ((string)($template['key']??'')) {
        'workplace_rewards' => ['workplace_reward'],
        'community_fundraising' => ['fundraiser','contest','giveaway','merchant_grant'],
        default => [],
    };
}

function mg_task_agent_program_template_ready(array $agent): bool
{
    return mg_task_agent_program_allowed_types($agent)!==[];
}

function mg_task_agent_program_normalize(array $row): array
{
    $budget=$row['budget_cents']!==null?(int)$row['budget_cents']:null;
    $reserved=(int)($row['reserved_cents']??0);
    $issued=(int)($row['issued_cents']??0);
    $remaining=$budget===null?null:max(0,$budget-$reserved-$issued);
    return [
        'id'=>(string)$row['public_id'],
        'name'=>(string)$row['name'],
        'program_type'=>(string)$row['program_type'],
        'status'=>(string)$row['status'],
        'starts_at'=>$row['starts_at']?:null,
        'ends_at'=>$row['ends_at']?:null,
        'currency'=>'USD',
        'budget'=>$budget===null?null:$budget/100,
        'reserved'=>$reserved/100,
        'issued'=>$issued/100,
        'remaining_budget'=>$remaining===null?null:$remaining/100,
        'max_items'=>$row['max_items']!==null?(int)$row['max_items']:null,
        'issued_items'=>(int)($row['issued_items']??0),
        'per_recipient_limit'=>$row['per_recipient_limit']!==null?(int)$row['per_recipient_limit']:null,
        'recipient_count'=>(int)($row['recipient_count']??0),
        'eligible_count'=>(int)($row['eligible_count']??0),
        'selected_count'=>(int)($row['selected_count']??0),
        'allocated_count'=>(int)($row['allocated_count']??0),
        'product_count'=>(int)($row['product_count']??0),
        'allocation_count'=>(int)($row['allocation_count']??0),
        'issued_allocation_count'=>(int)($row['issued_allocation_count']??0),
        'updated_at'=>$row['updated_at']??null,
        'canonical_url'=>'/merchant-distribution-program.php?program_id='.rawurlencode((string)$row['public_id']),
        'authority'=>'distribution_programs',
        'used_ai'=>false,
    ];
}

function mg_task_agent_program_rows(PDO $pdo,int $ownerUserId,int $agentId,array $types,int $limit=40): array
{
    if (!mg_task_agent_program_schema_ready($pdo) || $types===[]) return [];
    $placeholders=implode(',',array_fill(0,count($types),'?'));
    $sql="SELECT dp.*,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id) recipient_count,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id AND dr.eligibility_status='eligible') eligible_count,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id AND dr.eligibility_status='selected') selected_count,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id AND dr.eligibility_status IN ('allocated','fulfilled')) allocated_count,
        (SELECT COUNT(*) FROM distribution_program_products dpp WHERE dpp.program_id=dp.id AND dpp.status='active') product_count,
        (SELECT COUNT(*) FROM distribution_allocations da WHERE da.program_id=dp.id) allocation_count,
        (SELECT COUNT(*) FROM distribution_allocations da WHERE da.program_id=dp.id AND da.status='issued') issued_allocation_count
        FROM multi_agent_distribution_program_links link
        INNER JOIN distribution_programs dp ON dp.id=link.distribution_program_id AND dp.merchant_user_id=link.owner_user_id
        WHERE link.owner_user_id=? AND link.agent_id=? AND dp.program_type IN ($placeholders)
        ORDER BY FIELD(dp.status,'active','scheduled','draft','paused','completed','cancelled','archived'),dp.updated_at DESC,dp.id DESC
        LIMIT ".max(1,min(100,$limit));
    $stmt=$pdo->prepare($sql);
    $stmt->execute(array_merge([$ownerUserId,$agentId],$types));
    return array_map('mg_task_agent_program_normalize',$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_program_available(PDO $pdo,int $ownerUserId,int $agentId,array $types,int $limit=40): array
{
    if (!mg_task_agent_program_schema_ready($pdo) || $types===[]) return [];
    $placeholders=implode(',',array_fill(0,count($types),'?'));
    $sql="SELECT dp.*,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id) recipient_count,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id AND dr.eligibility_status='eligible') eligible_count,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id AND dr.eligibility_status='selected') selected_count,
        (SELECT COUNT(*) FROM distribution_recipients dr WHERE dr.program_id=dp.id AND dr.eligibility_status IN ('allocated','fulfilled')) allocated_count,
        (SELECT COUNT(*) FROM distribution_program_products dpp WHERE dpp.program_id=dp.id AND dpp.status='active') product_count,
        (SELECT COUNT(*) FROM distribution_allocations da WHERE da.program_id=dp.id) allocation_count,
        (SELECT COUNT(*) FROM distribution_allocations da WHERE da.program_id=dp.id AND da.status='issued') issued_allocation_count
        FROM distribution_programs dp
        WHERE dp.merchant_user_id=? AND dp.program_type IN ($placeholders)
          AND NOT EXISTS (SELECT 1 FROM multi_agent_distribution_program_links link WHERE link.owner_user_id=? AND link.distribution_program_id=dp.id)
        ORDER BY FIELD(dp.status,'active','scheduled','draft','paused','completed','cancelled','archived'),dp.updated_at DESC,dp.id DESC
        LIMIT ".max(1,min(100,$limit));
    $stmt=$pdo->prepare($sql);
    $stmt->execute(array_merge([$ownerUserId],$types,[$ownerUserId]));
    return array_map('mg_task_agent_program_normalize',$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_program_for_model(array $programs): array
{
    return array_map(static fn(array $program):array=>[
        'name'=>$program['name'],
        'program_type'=>$program['program_type'],
        'status'=>$program['status'],
        'starts_at'=>$program['starts_at'],
        'ends_at'=>$program['ends_at'],
        'budget'=>$program['budget'],
        'reserved'=>$program['reserved'],
        'issued'=>$program['issued'],
        'remaining_budget'=>$program['remaining_budget'],
        'max_items'=>$program['max_items'],
        'issued_items'=>$program['issued_items'],
        'recipient_count'=>$program['recipient_count'],
        'eligible_count'=>$program['eligible_count'],
        'selected_count'=>$program['selected_count'],
        'allocated_count'=>$program['allocated_count'],
        'product_count'=>$program['product_count'],
        'allocation_count'=>$program['allocation_count'],
        'issued_allocation_count'=>$program['issued_allocation_count'],
        'authority'=>'distribution_programs',
    ],array_slice($programs,0,12));
}

function mg_task_agent_program_card(array $program,bool $available=false): array
{
    $budget=$program['budget']===null?'No budget cap':number_format((float)$program['budget'],2).' USD budget';
    $body=sprintf('%s · %s · %d recipients · %d products · %d issued items.',$budget,(string)$program['status'],(int)$program['recipient_count'],(int)$program['product_count'],(int)$program['issued_items']);
    return [
        'type'=>$available?'distribution_program_link':'distribution_program',
        'title'=>$program['name'],
        'body'=>$body,
        'action'=>$available?'link_distribution_program':'open_distribution_program',
        'action_label'=>$available?'Connect existing program':'Open canonical program',
        'program'=>$program,
        'url'=>$program['canonical_url'],
        'review_payload'=>['program_id'=>$program['id']],
    ];
}

function mg_task_agent_program_append_context(PDO $pdo,int $ownerUserId,array $agent,array $context): array
{
    $types=mg_task_agent_program_allowed_types($agent);
    if ($types===[]) return $context;
    $ready=mg_task_agent_program_schema_ready($pdo);
    $programs=$ready?mg_task_agent_program_rows($pdo,$ownerUserId,(int)$agent['id'],$types,40):[];
    $available=$ready?mg_task_agent_program_available($pdo,$ownerUserId,(int)$agent['id'],$types,40):[];
    $context['distribution_programs']=$programs;
    $context['available_distribution_programs']=$available;
    $context['distribution_programs_for_model']=mg_task_agent_program_for_model($programs);
    $context['distribution_program_schema_ready']=$ready;
    return $context;
}

function mg_task_agent_program_link(PDO $pdo,int $ownerUserId,int $agentId,string $programPublicId,array $agent): array
{
    $types=mg_task_agent_program_allowed_types($agent);
    if ($types===[]) throw new InvalidArgumentException('This agent cannot coordinate distribution programs.');
    if (!mg_task_agent_program_schema_ready($pdo)) throw new RuntimeException('Task Agent Phase 4 migration is required.');
    if ($programPublicId==='') throw new InvalidArgumentException('Choose an existing distribution program.');
    $placeholders=implode(',',array_fill(0,count($types),'?'));
    $stmt=$pdo->prepare("SELECT id,public_id FROM distribution_programs WHERE merchant_user_id=? AND public_id=? AND program_type IN ($placeholders) LIMIT 1");
    $stmt->execute(array_merge([$ownerUserId,$programPublicId],$types));
    $program=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$program) throw new RuntimeException('Distribution program not found for this merchant and agent type.');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO multi_agent_distribution_program_links(public_id,agent_id,owner_user_id,distribution_program_id,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE agent_id=VALUES(agent_id),updated_at=NOW()')
            ->execute([mg_public_uuid(),$agentId,$ownerUserId,(int)$program['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    mg_audit('multi_agent.distribution_program_linked','distribution_program',['agent_id'=>$agentId,'program_id'=>$programPublicId,'canonical_reuse'=>true,'used_ai'=>false],$ownerUserId);
    foreach (mg_task_agent_program_rows($pdo,$ownerUserId,$agentId,$types,40) as $row) if ($row['id']===$programPublicId) return $row;
    throw new RuntimeException('Unable to load the connected distribution program.');
}

function mg_task_agent_program_unlink(PDO $pdo,int $ownerUserId,int $agentId,string $programPublicId,array $agent): void
{
    $types=mg_task_agent_program_allowed_types($agent);
    if ($types===[]) throw new InvalidArgumentException('This agent cannot coordinate distribution programs.');
    $placeholders=implode(',',array_fill(0,count($types),'?'));
    $stmt=$pdo->prepare("DELETE link FROM multi_agent_distribution_program_links link INNER JOIN distribution_programs dp ON dp.id=link.distribution_program_id AND dp.merchant_user_id=link.owner_user_id WHERE link.owner_user_id=? AND link.agent_id=? AND dp.public_id=? AND dp.program_type IN ($placeholders)");
    $stmt->execute(array_merge([$ownerUserId,$agentId,$programPublicId],$types));
    if ($stmt->rowCount()<1) throw new RuntimeException('Connected distribution program not found.');
    mg_audit('multi_agent.distribution_program_unlinked','distribution_program',['agent_id'=>$agentId,'program_id'=>$programPublicId,'program_mutated'=>false,'used_ai'=>false],$ownerUserId);
}
