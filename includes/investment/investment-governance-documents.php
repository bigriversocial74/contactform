<?php
declare(strict_types=1);

function mg_investment_governance_save_tax_document(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.tax_documents.manage');
    $round=mg_investment_governance_round($pdo,$input['round_id']??'');
    $investorId=(int)($input['investor_user_id']??0);if($investorId<1)throw new MgInvestmentException('Investor is required.');
    $funded=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0');$funded->execute([(int)$round['id'],$investorId]);if((int)$funded->fetchColumn()<1)throw new MgInvestmentException('Tax and annual documents may only be assigned to a verified funded investor.',409);
    $types=['w9','w8ben','w8bene','form_1099','schedule_k1','annual_financials','investor_tax_notice','other'];$statuses=['not_started','requested','preparing_external','internal_review','approved','published','superseded','archived','not_applicable'];
    $type=(string)($input['document_type']??'other');$status=(string)($input['status']??'not_started');if(!in_array($type,$types,true)||!in_array($status,$statuses,true))throw new MgInvestmentException('Invalid tax-document configuration.');
    $year=(int)($input['reporting_year']??date('Y'));if($year<2000||$year>(int)date('Y')+2)throw new MgInvestmentException('Reporting year is outside the supported range.');
    if($status==='published')mg_investment_require_permission($actor,'admin.investment.governance.publish');
    $publicId=trim((string)($input['tax_document_id']??''));$actorId=(int)$actor['id'];$pdo->beginTransaction();
    try{
        if($publicId===''){
            $publicId=mg_investment_uuid();
            $pdo->prepare('INSERT INTO investment_tax_documents (public_id,round_id,investor_user_id,document_type,reporting_year,title,external_provider,status,current_version_number,published_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,? ,0,IF(?="published",NOW(),NULL),?,?,NOW(),NOW())')->execute([$publicId,(int)$round['id'],$investorId,$type,$year,mg_investment_text($input['title']??'',220,2,'Document title'),mg_investment_text($input['external_provider']??'',180)?:null,$status,$status,$actorId,$actorId]);
            $documentId=(int)$pdo->lastInsertId();
        }else{
            $q=$pdo->prepare('SELECT * FROM investment_tax_documents WHERE public_id=? AND round_id=? LIMIT 1 FOR UPDATE');$q->execute([mg_investment_text($publicId,36,36,'Tax document identifier'),(int)$round['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new MgInvestmentException('Tax document not found.',404);$documentId=(int)$row['id'];
            $pdo->prepare('UPDATE investment_tax_documents SET investor_user_id=?,document_type=?,reporting_year=?,title=?,external_provider=?,status=?,published_at=IF(?="published",COALESCE(published_at,NOW()),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$investorId,$type,$year,mg_investment_text($input['title']??'',220,2,'Document title'),mg_investment_text($input['external_provider']??'',180)?:null,$status,$status,$actorId,$documentId]);
        }
        $url=mg_investment_url($input['external_url']??'');
        if($url!==null){
            $n=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM investment_tax_document_versions WHERE tax_document_id=?');$n->execute([$documentId]);$version=(int)$n->fetchColumn();$versionPublicId=mg_investment_uuid();$versionStatus=in_array($status,['approved','published'],true)?$status:'draft';
            if($versionStatus==='published')$pdo->prepare('UPDATE investment_tax_document_versions SET status="superseded" WHERE tax_document_id=? AND status="published"')->execute([$documentId]);
            $pdo->prepare('INSERT INTO investment_tax_document_versions (public_id,tax_document_id,version_number,external_url,external_reference,status,approved_by_user_id,approved_at,published_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,IF(? IN ("approved","published"),?,NULL),IF(? IN ("approved","published"),NOW(),NULL),IF(?="published",NOW(),NULL),?,NOW())')->execute([$versionPublicId,$documentId,$version,$url,mg_investment_text($input['external_reference']??'',220)?:null,$versionStatus,$versionStatus,$actorId,$versionStatus,$versionStatus,$actorId]);
            $pdo->prepare('UPDATE investment_tax_documents SET current_version_number=?,status=?,published_at=IF(?="published",NOW(),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$version,$versionStatus,$versionStatus,$actorId,$documentId]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    mg_audit('investment_tax_document_saved','investment_tax_document',['tax_document_id'=>$publicId,'round_id'=>$round['public_id'],'investor_user_id'=>$investorId,'status'=>$status,'prepared_externally'=>true],$actorId);
    return mg_investment_governance_dashboard($pdo,['round_id'=>$round['public_id']]);
}

function mg_investment_governance_notice_recipient_ids(PDO $pdo,array $notice,array $input): array
{
    $roundId=(int)($notice['round_id']??0);$audience=(string)$notice['audience'];$ids=[];
    if(in_array($audience,['specific_investors','custom'],true)){
        $raw=$input['investor_user_ids']??[];if(is_string($raw))$raw=preg_split('/[\s,]+/',$raw,-1,PREG_SPLIT_NO_EMPTY)?:[];
        foreach((array)$raw as $id)if((int)$id>0)$ids[]=(int)$id;
    }elseif($audience==='funded_investors'&&$roundId>0){
        $q=$pdo->prepare('SELECT DISTINCT investor_user_id FROM investor_closing_records WHERE round_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined")');$q->execute([$roundId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }elseif($audience==='major_investors'&&$roundId>0){
        $q=$pdo->prepare('SELECT DISTINCT investor_user_id FROM investment_investor_rights WHERE round_id=? AND right_type="major_investor" AND status="active"');$q->execute([$roundId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }elseif($audience==='rights_holders'&&$roundId>0){
        $q=$pdo->prepare('SELECT DISTINCT investor_user_id FROM investment_investor_rights WHERE round_id=? AND status="active"');$q->execute([$roundId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }elseif($audience==='selected_investors'&&$roundId>0){
        $q=$pdo->prepare('SELECT DISTINCT investor_user_id FROM investment_round_access WHERE round_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');$q->execute([$roundId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }elseif($audience==='board'){
        $ids=array_map('intval',$pdo->query('SELECT DISTINCT p.user_id FROM investment_governance_participants p INNER JOIN investment_governance_appointments a ON a.participant_id=p.id WHERE p.user_id IS NOT NULL AND a.status="active" AND a.appointment_type IN ("director","board_observer")')->fetchAll(PDO::FETCH_COLUMN));
    }
    return array_values(array_unique(array_filter($ids,static fn($id)=>$id>0)));
}

function mg_investment_governance_save_notice(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$round=mg_investment_governance_round($pdo,$input['round_id']??'',false);
    $types=['new_financing','material_contract','leadership_change','litigation_regulatory','operating_change','acquisition_sale','missed_milestone','capitalization_change','information_rights','board_meeting','other'];$audiences=['funded_investors','major_investors','rights_holders','selected_investors','specific_investors','board','custom'];$statuses=['draft','internal_review','counsel_review','approved','published','archived'];$counsel=['not_started','in_review','approved','changes_required','not_applicable'];
    $type=(string)($input['notice_type']??'other');$audience=(string)($input['audience']??'funded_investors');$status=(string)($input['status']??'draft');$counselStatus=(string)($input['counsel_status']??'not_started');
    if(!in_array($type,$types,true)||!in_array($audience,$audiences,true)||!in_array($status,$statuses,true)||!in_array($counselStatus,$counsel,true))throw new MgInvestmentException('Invalid material-notice configuration.');
    if($status==='published'){mg_investment_require_permission($actor,'admin.investment.governance.publish');if(!in_array($counselStatus,['approved','not_applicable'],true))throw new MgInvestmentException('Counsel approval or not-applicable status is required before publishing a material notice.',409);if($round===null&&$audience!=='board')throw new MgInvestmentException('A round is required for investor notice audiences.',409);}
    $publicId=trim((string)($input['notice_id']??''));$actorId=(int)$actor['id'];$values=[$round?(int)$round['id']:null,$type,mg_investment_text($input['title']??'',220,2,'Notice title'),mg_investment_long_text($input['body']??'',30000,20,'Notice body'),$audience,$status,$counselStatus,mg_investment_governance_datetime($input['effective_at']??''),$status,mg_investment_governance_datetime($input['expires_at']??''),mg_investment_url($input['related_document_url']??''),mg_investment_text($input['related_document_reference']??'',220)?:null,mg_investment_long_text($input['internal_notes']??'',10000)?:null,$status,$actorId];
    $pdo->beginTransaction();
    try{
        if($publicId===''){$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_material_notices (public_id,round_id,notice_type,title,body,audience,status,counsel_status,effective_at,published_at,expires_at,related_document_url,related_document_reference,internal_notes,approved_by_user_id,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,IF(?="published",NOW(),NULL),?,?,?,?,IF(? IN ("approved","published"),?,NULL),?,?,NOW(),NOW())')->execute([$publicId,...$values,$actorId,$actorId]);$noticeId=(int)$pdo->lastInsertId();}
        else{$q=$pdo->prepare('SELECT * FROM investment_material_notices WHERE public_id=? LIMIT 1 FOR UPDATE');$q->execute([mg_investment_text($publicId,36,36,'Notice identifier')]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new MgInvestmentException('Material notice not found.',404);$noticeId=(int)$row['id'];$pdo->prepare('UPDATE investment_material_notices SET round_id=?,notice_type=?,title=?,body=?,audience=?,status=?,counsel_status=?,effective_at=?,published_at=IF(?="published",COALESCE(published_at,NOW()),published_at),expires_at=?,related_document_url=?,related_document_reference=?,internal_notes=?,approved_by_user_id=IF(? IN ("approved","published"),?,approved_by_user_id),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$values[0],$type,$values[2],$values[3],$audience,$status,$counselStatus,$values[7],$status,$values[9],$values[10],$values[11],$values[12],$status,$actorId,$actorId,$noticeId]);}
        if($status==='published'){
            $notice=['id'=>$noticeId,'round_id'=>$round?(int)$round['id']:null,'audience'=>$audience];$ids=mg_investment_governance_notice_recipient_ids($pdo,$notice,$input);if($ids===[])throw new MgInvestmentException('The selected audience does not currently contain eligible recipients.',409);
            $pdo->prepare('UPDATE investment_material_notice_recipients SET status="revoked",updated_at=NOW() WHERE notice_id=?')->execute([$noticeId]);$stmt=$pdo->prepare('INSERT INTO investment_material_notice_recipients (notice_id,investor_user_id,status,created_at,updated_at) VALUES (?,?,"published",NOW(),NOW()) ON DUPLICATE KEY UPDATE status=IF(status="acknowledged","acknowledged","published"),updated_at=NOW()');foreach($ids as $id)$stmt->execute([$noticeId,$id]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    mg_audit('investment_material_notice_saved','investment_material_notice',['notice_id'=>$publicId,'status'=>$status,'audience'=>$audience,'automatic_email'=>false],$actorId);return mg_investment_governance_dashboard($pdo,$input);
}

function mg_investment_governance_ai_draft(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.ai');$type=mg_investment_text($input['draft_type']??'board_agenda',80,3,'Draft type');$allowed=['board_agenda','meeting_briefing','draft_minutes','resolution_summary','governance_action_list','investor_rights_summary','obligation_review','material_event_notice','quarterly_board_update','annual_investor_update','missing_governance_documents'];if(!in_array($type,$allowed,true))throw new MgInvestmentException('Invalid governance AI action.');
    $round=mg_investment_governance_round($pdo,$input['round_id']??'',false);$dashboard=mg_investment_governance_dashboard($pdo,$round?['round_id'=>$round['public_id']]:[]);
    $context=['draft_type'=>$type,'round'=>$round?['public_id'=>$round['public_id'],'public_name'=>$round['public_name'],'status'=>$round['status'],'funded_cents'=>(int)$round['funded_cents']]:null,'summary'=>$dashboard['summary'],'meetings'=>array_map(static fn($m)=>['title'=>$m['title'],'starts_at'=>$m['starts_at'],'status'=>$m['status'],'counsel_status'=>$m['counsel_status'],'summary_status'=>$m['summary_status']],array_slice($dashboard['meetings'],0,20)),'consents'=>array_map(static fn($c)=>['title'=>$c['title'],'status'=>$c['status'],'counsel_status'=>$c['counsel_status'],'pending_count'=>(int)$c['pending_count']],array_slice($dashboard['consents'],0,20)),'obligations'=>array_map(static fn($o)=>['title'=>$o['title'],'type'=>$o['obligation_type'],'due_at'=>$o['due_at'],'status'=>$o['status'],'counsel_review_required'=>(bool)$o['counsel_review_required']],array_slice($dashboard['obligations'],0,30)),'instruction'=>mg_investment_long_text($input['instruction']??'',6000)];
    $workspaceId=$round?(int)$round['workspace_id']:0;if($workspaceId<1){$q=$pdo->query('SELECT id FROM investment_workspaces ORDER BY id LIMIT 1');$workspaceId=(int)$q->fetchColumn();if($workspaceId<1)throw new MgInvestmentException('An investment workspace is required for Claude governance drafts.',409);}
    $model=mg_investment_claude_model($pdo);$publicId=mg_investment_uuid();$roundId=$round?(int)$round['id']:null;$pdo->prepare('INSERT INTO investment_ai_analyses (public_id,workspace_id,round_id,requested_by_user_id,provider,model,analysis_type,input_snapshot_json,status,created_at) VALUES (?,?,?,? ,"anthropic",?,?,? ,"requested",NOW())')->execute([$publicId,$workspaceId,$roundId,(int)$actor['id'],$model,'governance_'.$type,mg_investment_json_encode($context)]);$analysisId=(int)$pdo->lastInsertId();
    try{require_once dirname(__DIR__).'/ai/anthropic-client.php';$system='You are the Microgifter Governance Assistant. Produce an internal editable draft from supplied administrative records only. Never determine legal rights, appoint directors, cast or record votes, approve resolutions, sign documents, publish notices, prepare tax forms, issue securities, modify the official stock ledger, or provide legal or tax advice. Distinguish approved records from drafts, identify missing facts, and require counsel/accountant review where appropriate.';$response=mg_anthropic_messages(['model'=>$model,'max_tokens'=>1800,'temperature'=>0.2,'system'=>$system,'messages'=>[['role'=>'user','content'=>mg_investment_json_encode($context)]]]);$text=mg_anthropic_text_from_response($response);$usage=is_array($response['usage']??null)?$response['usage']:[];$pdo->prepare('UPDATE investment_ai_analyses SET response_text=?,status="completed",input_tokens=?,output_tokens=?,completed_at=NOW() WHERE id=?')->execute([$text,(int)($usage['input_tokens']??0),(int)($usage['output_tokens']??0),$analysisId]);mg_audit('investment_governance_ai_completed','investment_ai',['analysis_id'=>$publicId,'draft_type'=>$type],(int)$actor['id']);}catch(Throwable $e){$pdo->prepare('UPDATE investment_ai_analyses SET status="failed",error_message=?,completed_at=NOW() WHERE id=?')->execute([mb_substr($e->getMessage(),0,1000),$analysisId]);}
    $q=$pdo->prepare('SELECT public_id,analysis_type,response_text,status,error_message,created_at FROM investment_ai_analyses WHERE id=?');$q->execute([$analysisId]);return ['analysis'=>$q->fetch(PDO::FETCH_ASSOC),'dashboard'=>mg_investment_governance_dashboard($pdo,$round?['round_id'=>$round['public_id']]:[])];
}
