<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_action_decode(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value)||trim($value)==='') return [];
    try{$decoded=json_decode($value,true,64,JSON_THROW_ON_ERROR);}catch(Throwable){return [];}
    return is_array($decoded)?$decoded:[];
}

function mg_mcp_creator_campaign_action_row(PDO $pdo,string $publicId,int $ownerUserId,bool $forUpdate=false): array
{
    $sql="SELECT aa.*,r.public_id run_public_id,r.status run_status,r.grant_id,a.public_id automation_public_id,
                 g.public_id grant_public_id,g.authorizing_user_id,g.status grant_status,g.revocation_version,
                 c.public_id connection_public_id,c.status connection_status,c.expires_at connection_expires_at,
                 cl.public_id client_public_id,cl.client_key,cl.display_name client_name,cl.status client_status,
                 ap.public_id approval_public_id,ap.status approval_status,ap.requested_reason,ap.decision_reason,
                 ap.requested_at approval_requested_at,ap.decided_at approval_decided_at,ap.decided_by_user_id,
                 ap.expires_at approval_expires_at,ap.executed_at approval_executed_at
          FROM mcp_automation_actions aa
          INNER JOIN mcp_automation_runs r ON r.id=aa.run_id
          INNER JOIN mcp_automations a ON a.id=r.automation_id
          INNER JOIN mcp_automation_grants g ON g.id=r.grant_id
          INNER JOIN mcp_connections c ON c.id=g.connection_id
          INNER JOIN mcp_clients cl ON cl.id=c.client_id
          INNER JOIN mcp_creator_campaign_action_approvals ap ON ap.action_id=aa.id
          WHERE aa.public_id=? AND g.authorizing_user_id=? LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId,$ownerUserId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgMcpCreatorCampaignActionException('Creator Campaign action was not found.',404,'MCP_CREATOR_CAMPAIGN_ACTION_NOT_FOUND');
    return $row;
}

function mg_mcp_creator_campaign_action_projection(array $row,bool $duplicate=false): array
{
    $input=mg_mcp_creator_campaign_action_decode($row['sanitized_input_json']??null);
    $receipt=$row['receipt']??null;
    return [
        'id'=>(string)$row['public_id'],
        'tool'=>(string)$row['tool_name'],
        'status'=>(string)$row['status'],
        'risk'=>(string)$row['risk_level'],
        'operation_class'=>(string)$row['operation_class'],
        'input'=>$input,
        'input_fingerprint'=>(string)$row['input_fingerprint'],
        'fresh_state_token'=>$row['fresh_state_token']!==null?(string)$row['fresh_state_token']:null,
        'idempotency_key'=>(string)$row['idempotency_key'],
        'duplicate'=>$duplicate,
        'grant'=>['id'=>(string)$row['grant_public_id'],'status'=>(string)$row['grant_status'],'revocation_version'=>(int)$row['revocation_version']],
        'connection'=>['id'=>(string)$row['connection_public_id'],'status'=>(string)$row['connection_status'],'client'=>(string)$row['client_name']],
        'approval'=>[
            'id'=>(string)$row['approval_public_id'],'status'=>(string)$row['approval_status'],
            'requested_reason'=>(string)$row['requested_reason'],
            'decision_reason'=>$row['decision_reason']!==null?(string)$row['decision_reason']:null,
            'requested_at'=>(string)$row['approval_requested_at'],
            'decided_at'=>$row['approval_decided_at']!==null?(string)$row['approval_decided_at']:null,
            'expires_at'=>(string)$row['approval_expires_at'],
            'executed_at'=>$row['approval_executed_at']!==null?(string)$row['approval_executed_at']:null,
        ],
        'receipt'=>is_array($receipt)?$receipt:null,
        'error'=>$row['error_code']!==null?['code'=>(string)$row['error_code'],'message'=>(string)($row['error_message']??'')]:null,
        'created_at'=>(string)$row['created_at'],
        'updated_at'=>(string)$row['updated_at'],
    ];
}

function mg_mcp_creator_campaign_action_attach_receipt(PDO $pdo,array $row): array
{
    $stmt=$pdo->prepare('SELECT public_id,status,canonical_service,canonical_action,before_state_token,after_state_token,result_reference_type,result_reference_public_id,amount_cents,quantity,metadata_json,attempted_at,completed_at FROM mcp_action_receipts WHERE action_id=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int)$row['id']]);$receipt=$stmt->fetch(PDO::FETCH_ASSOC);
    if($receipt){$receipt['metadata']=mg_mcp_creator_campaign_action_decode($receipt['metadata_json']??null);unset($receipt['metadata_json']);$row['receipt']=$receipt;}
    return $row;
}

function mg_mcp_creator_campaign_action_list_owner(PDO $pdo,int $ownerUserId,array $filters=[]): array
{
    mg_mcp_creator_campaign_action_expire($pdo,$ownerUserId);
    $status=strtolower(trim((string)($filters['status']??'')));$where=['g.authorizing_user_id=?'];$params=[$ownerUserId];
    if($status!==''){if(!in_array($status,MG_MCP_CREATOR_CAMPAIGN_ACTION_STATUSES,true))throw new MgMcpCreatorCampaignActionException('Invalid action status.');$where[]='aa.status=?';$params[]=$status;}
    $stmt=$pdo->prepare("SELECT aa.public_id FROM mcp_automation_actions aa INNER JOIN mcp_automation_runs r ON r.id=aa.run_id INNER JOIN mcp_automation_grants g ON g.id=r.grant_id INNER JOIN mcp_creator_campaign_action_approvals ap ON ap.action_id=aa.id WHERE ".implode(' AND ',$where).' ORDER BY aa.id DESC LIMIT 200');
    $stmt->execute($params);$items=[];
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN)?:[] as $id){$items[]=mg_mcp_creator_campaign_action_projection(mg_mcp_creator_campaign_action_attach_receipt($pdo,mg_mcp_creator_campaign_action_row($pdo,(string)$id,$ownerUserId)));}
    return $items;
}

function mg_mcp_creator_campaign_action_expire(PDO $pdo,int $ownerUserId): int
{
    $stmt=$pdo->prepare("SELECT ap.id,ap.action_id,aa.run_id FROM mcp_creator_campaign_action_approvals ap INNER JOIN mcp_automation_actions aa ON aa.id=ap.action_id WHERE ap.owner_user_id=? AND ap.status='pending' AND ap.expires_at<NOW() FOR UPDATE");
    $stmt->execute([$ownerUserId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($rows as $row){
        $pdo->prepare("UPDATE mcp_creator_campaign_action_approvals SET status='expired',updated_at=NOW() WHERE id=? AND status='pending'")->execute([(int)$row['id']]);
        $pdo->prepare("UPDATE mcp_automation_actions SET status='expired',completed_at=NOW(),error_code='MCP_CREATOR_CAMPAIGN_ACTION_APPROVAL_EXPIRED',error_message='Owner approval expired.',updated_at=NOW() WHERE id=? AND status='waiting_for_approval'")->execute([(int)$row['action_id']]);
        $pdo->prepare("UPDATE mcp_automation_runs SET status='cancelled',completed_at=NOW(),error_code='MCP_CREATOR_CAMPAIGN_ACTION_APPROVAL_EXPIRED',error_message='Owner approval expired.',updated_at=NOW() WHERE id=?")->execute([(int)$row['run_id']]);
    }
    return count($rows);
}

function mg_mcp_creator_campaign_action_automation(PDO $pdo,array $grant): array
{
    $stmt=$pdo->prepare("SELECT * FROM mcp_automations WHERE grant_id=? AND playbook_key='creator_campaign_manual_canonical_actions' LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$grant['id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if($row)return $row;
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO mcp_automations(public_id,grant_id,owner_user_id,workspace_type,workspace_id,name,playbook_key,description,status,configuration_json,timezone,current_version,created_at,updated_at) VALUES (?,?,?,?,?,'Creator Campaign canonical actions','creator_campaign_manual_canonical_actions','Owner-approved, manually executed Creator Campaign canonical actions.','active','{}','UTC',1,NOW(),NOW())")
        ->execute([$publicId,(int)$grant['id'],(int)$grant['authorizing_user_id'],$grant['workspace_type'],$grant['workspace_id']]);
    $stmt->execute([(int)$grant['id']]);return $stmt->fetch(PDO::FETCH_ASSOC)?:[];
}

function mg_mcp_creator_campaign_action_security(PDO $pdo,array $context,string $event,string $message,array $evidence,string $severity='high'): void
{
    $stmt=$pdo->prepare('INSERT INTO mcp_security_events(public_id,connection_id,client_id,user_id,workspace_type,workspace_id,severity,event_type,message,evidence_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_public_uuid(),(int)$context['connection_db_id'],(int)$context['client_db_id'],(int)$context['user_id'],$context['workspace_type'],$context['workspace_id'],$severity,$event,mb_substr($message,0,500),json_encode($evidence,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
}
