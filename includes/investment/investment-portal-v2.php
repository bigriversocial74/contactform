<?php
declare(strict_types=1);

function mg_investment_portal_data_v2(PDO $pdo,array $user): array
{
    if(!in_array('investor',is_array($user['roles']??null)?$user['roles']:[],true))throw new MgInvestmentException('Investor access is not active.',403);
    $profileStmt=$pdo->prepare('SELECT * FROM investor_profiles WHERE user_id=? AND status="active" LIMIT 1');$profileStmt->execute([(int)$user['id']]);$profile=$profileStmt->fetch(PDO::FETCH_ASSOC);if(!$profile)throw new MgInvestmentException('Investor profile is not active.',403);
    mg_investment_portal_log($pdo,(int)$user['id'],null,'portal_open',null,['source'=>'investor_portal_v2']);
    $stmt=$pdo->prepare('SELECT r.* FROM investment_rounds r INNER JOIN investment_round_publication p ON p.round_id=r.id WHERE p.publication_status IN ("private_preview","published") AND r.status IN ("private_preview","open","minimum_reached","closing","closed") AND (r.visibility="approved_investors" OR (r.visibility="selected_investors" AND EXISTS(SELECT 1 FROM investment_round_access a WHERE a.round_id=r.id AND a.investor_user_id=? AND a.status="granted" AND (a.expires_at IS NULL OR a.expires_at>NOW()))) OR (r.visibility="funded_investors" AND EXISTS(SELECT 1 FROM investor_round_interests ri WHERE ri.round_id=r.id AND ri.investor_user_id=? AND ri.funded_cents>0))) ORDER BY FIELD(r.status,"open","minimum_reached","private_preview","closing","closed"),r.updated_at DESC');
    $stmt->execute([(int)$user['id'],(int)$user['id']]);$rounds=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $publication=mg_investment_publication_get($pdo,(int)$row['id']);$sections=$publication['sections'];$snapshot=mg_investment_json($row['snapshot_json']);
        $selectedStmt=$pdo->prepare('SELECT COUNT(*) FROM investment_round_access WHERE round_id=? AND investor_user_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');$selectedStmt->execute([(int)$row['id'],(int)$user['id']]);$hasSelectedAccess=(int)$selectedStmt->fetchColumn()>0;
        $fundedStmt=$pdo->prepare('SELECT COALESCE(SUM(funded_cents),0) FROM investor_round_interests WHERE round_id=? AND investor_user_id=? AND status NOT IN ("passed","declined","archived")');$fundedStmt->execute([(int)$row['id'],(int)$user['id']]);$hasFundedAccess=(int)$fundedStmt->fetchColumn()>0;
        $metrics=[];$documents=[];
        if($sections['evidence_metrics']){$q=$pdo->prepare('SELECT public_id,name,description,unit,value_type,confidence,current_value,last_verified_at FROM investment_metrics WHERE workspace_id=? AND investor_visible=1 ORDER BY name');$q->execute([(int)$row['workspace_id']]);$metrics=$q->fetchAll(PDO::FETCH_ASSOC);}
        if($sections['documents']){
            $allowed=['approved_investors','public_summary'];if($hasSelectedAccess)$allowed[]='selected_investors';if($hasFundedAccess)$allowed[]='funded_investors';$placeholders=implode(',',array_fill(0,count($allowed),'?'));
            $q=$pdo->prepare('SELECT public_id,title,document_type,status,external_url,visibility FROM investment_documents WHERE workspace_id=? AND status="published" AND visibility IN ('.$placeholders.') ORDER BY title');$q->execute(array_merge([(int)$row['workspace_id']],$allowed));$documents=$q->fetchAll(PDO::FETCH_ASSOC);
        }
        $rounds[]=['id'=>$row['public_id'],'public_name'=>$row['public_name'],'status'=>$row['status'],'instrument_type'=>$row['instrument_type'],'minimum_raise_cents'=>(int)$row['minimum_raise_cents'],'target_raise_cents'=>(int)$row['target_raise_cents'],'maximum_raise_cents'=>(int)$row['maximum_raise_cents'],'valuation_cap_cents'=>(int)$row['valuation_cap_cents'],'discount_bps'=>(int)$row['discount_bps'],'minimum_investment_cents'=>(int)$row['minimum_investment_cents'],'soft_commitment_cents'=>(int)$row['soft_commitment_cents'],'signed_cents'=>(int)$row['signed_cents'],'funded_cents'=>(int)$row['funded_cents'],'opens_at'=>$row['opens_at'],'target_close_at'=>$row['target_close_at'],'counsel_status'=>$row['counsel_status'],'publication_status'=>$publication['publication_status'],'sections'=>$sections,'founder_update'=>$sections['founder_update']?$publication['founder_update']:null,'important_notice'=>$sections['important_notice']?$publication['important_notice']:null,'company_summary'=>$sections['company_summary']?($snapshot['workspace']['company']??[]):null,'budgets'=>$sections['use_of_funds']?array_values(array_filter($snapshot['budgets']??[],static fn($item)=>(int)($item['investor_visible']??0)===1)):[],'goals'=>$sections['goals']?array_values(array_filter($snapshot['goals']??[],static fn($item)=>(int)($item['investor_visible']??0)===1)):[],'metrics'=>$metrics,'documents'=>$documents];
        mg_investment_portal_log($pdo,(int)$user['id'],(int)$row['id'],'round_view',(string)$row['public_id'],['publication_status'=>$publication['publication_status']]);
    }
    return ['profile'=>$profile,'rounds'=>$rounds];
}

function mg_investment_portal_event_v2(PDO $pdo,array $user,array $input): array
{
    if(!in_array('investor',is_array($user['roles']??null)?$user['roles']:[],true))throw new MgInvestmentException('Investor access is not active.',403);
    $event=(string)($input['event_type']??'');if(!in_array($event,['document_open','metric_view','round_view'],true))throw new MgInvestmentException('Invalid portal event.');
    $round=null;if(!empty($input['round_id']))$round=mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'));
    mg_investment_portal_log($pdo,(int)$user['id'],$round?(int)$round['id']:null,$event,mg_investment_text($input['subject_id']??'',36)?:null,['title'=>mg_investment_text($input['title']??'',220)]);
    return ['recorded'=>true];
}
