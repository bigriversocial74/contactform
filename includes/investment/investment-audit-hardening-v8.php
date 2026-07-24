<?php
declare(strict_types=1);

function mg_investment_reporting_period_save_audited(PDO $pdo,array $actor,array $input): array
{
    $status=(string)($input['status']??'planning');
    if($status==='published')throw new MgInvestmentException('Publish an approved immutable report snapshot; reporting periods cannot be published directly.',409);
    $publicId=trim((string)($input['period_id']??''));
    if($publicId!==''){
        $round=mg_investment_closing_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));
        $q=$pdo->prepare('SELECT * FROM investment_reporting_periods WHERE public_id=? AND round_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Period identifier'),(int)$round['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);if(!$current)throw new MgInvestmentException('Investor reporting period not found.',404);
        if($current['published_at']!==null){
            if($status!=='archived')throw new MgInvestmentException('Published reporting periods are immutable. Archive the period instead of rewriting it.',409);
            $newPublic=['period_name'=>mg_investment_text($input['period_name']??'',180,2,'Period name'),'period_type'=>(string)($input['period_type']??'quarterly'),'starts_at'=>mg_investment_date($input['starts_at']??null),'ends_at'=>mg_investment_date($input['ends_at']??null),'due_at'=>mg_investment_date($input['due_at']??null)];
            foreach($newPublic as $field=>$value)if((string)($current[$field]??'')!==(string)($value??''))throw new MgInvestmentException('Published reporting-period dates and labels are immutable.',409);
        }
    }
    return mg_investment_reporting_period_save($pdo,$actor,$input);
}

function mg_investment_reporting_snapshot_save_audited(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.relations.manage');
    $periodPublicId=mg_investment_text($input['period_id']??'',36,36,'Reporting period identifier');$status=(string)($input['status']??'draft');
    if(!in_array($status,['draft','internal_review','approved','published','superseded','archived'],true))throw new MgInvestmentException('Invalid reporting snapshot status.');
    if($status==='published')mg_investment_require_permission($actor,'admin.investment.relations.publish');
    $headline=mg_investment_text($input['headline']??'',220,2,'Report headline');$narrative=mg_investment_long_text($input['narrative']??'',30000)?:null;$metrics=is_array($input['metrics']??null)?$input['metrics']:[];$use=is_array($input['use_of_funds']??null)?$input['use_of_funds']:[];$milestones=is_array($input['milestones']??null)?$input['milestones']:[];$risks=is_array($input['risks']??null)?$input['risks']:[];$actorId=(int)$actor['id'];
    return mg_investment_audit_transaction($pdo,function()use($pdo,$periodPublicId,$status,$headline,$narrative,$metrics,$use,$milestones,$risks,$actorId):array{
        $q=$pdo->prepare('SELECT p.*,r.public_id AS round_public_id FROM investment_reporting_periods p INNER JOIN investment_rounds r ON r.id=p.round_id WHERE p.public_id=? LIMIT 1 FOR UPDATE');$q->execute([$periodPublicId]);$period=$q->fetch(PDO::FETCH_ASSOC);if(!$period)throw new MgInvestmentException('Investor reporting period not found.',404);if($period['published_at']!==null&&in_array($status,['draft','internal_review'],true))throw new MgInvestmentException('Published reporting history is immutable. Create a new approved correction version.',409);
        $metricsJson=mg_investment_json_encode($metrics);$useJson=mg_investment_json_encode($use);$milestonesJson=mg_investment_json_encode($milestones);$risksJson=mg_investment_json_encode($risks);
        if($status==='published'){
            $approvedQ=$pdo->prepare('SELECT * FROM investment_reporting_snapshots WHERE reporting_period_id=? AND status="approved" ORDER BY version_number DESC LIMIT 1 FOR UPDATE');$approvedQ->execute([(int)$period['id']]);$approved=$approvedQ->fetch(PDO::FETCH_ASSOC);if(!$approved)throw new MgInvestmentException('Approve this exact report version before publishing it.',409);
            foreach(['headline'=>$headline,'narrative'=>$narrative,'metrics_json'=>$metricsJson,'use_of_funds_json'=>$useJson,'milestones_json'=>$milestonesJson,'risks_json'=>$risksJson] as $field=>$value)if((string)($approved[$field]??'')!==(string)($value??''))throw new MgInvestmentException('Published content must exactly match the latest approved report version.',409);
        }
        $n=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM investment_reporting_snapshots WHERE reporting_period_id=?');$n->execute([(int)$period['id']]);$version=(int)$n->fetchColumn();$publicId=mg_investment_uuid();
        if($status==='published')$pdo->prepare('UPDATE investment_reporting_snapshots SET status="superseded" WHERE reporting_period_id=? AND status="published"')->execute([(int)$period['id']]);
        $pdo->prepare('INSERT INTO investment_reporting_snapshots (public_id,reporting_period_id,version_number,headline,narrative,metrics_json,use_of_funds_json,milestones_json,risks_json,status,approved_by_user_id,approved_at,published_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,IF(? IN ("approved","published"),?,NULL),IF(? IN ("approved","published"),NOW(),NULL),IF(?="published",NOW(),NULL),?,NOW())')->execute([$publicId,(int)$period['id'],$version,$headline,$narrative,$metricsJson,$useJson,$milestonesJson,$risksJson,$status,$status,$actorId,$status,$status,$actorId]);
        $pdo->prepare('UPDATE investment_reporting_periods SET status=?,published_at=IF(?="published",COALESCE(published_at,NOW()),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$status==='published'?'published':($status==='approved'?'approved':$period['status']),$status,$actorId,(int)$period['id']]);
        mg_audit('investment_reporting_snapshot_saved','investment_reporting_snapshot',['snapshot_id'=>$publicId,'period_id'=>$periodPublicId,'version'=>$version,'status'=>$status,'separate_publish_authority'=>true],$actorId);
        return ['relations'=>mg_investment_relations_detail($pdo,(string)$period['round_public_id']),'dashboard'=>mg_investment_closing_dashboard_audited($pdo,['round_id'=>$period['round_public_id']])];
    });
}

function mg_investment_use_of_funds_actual_save_audited(PDO $pdo,array $actor,array $input): array
{
    $round=mg_investment_closing_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$visible=mg_investment_bool($input['investor_visible']??false);$amount=mg_investment_money($input['amount']??0);if($amount<1)throw new MgInvestmentException('Use-of-funds actual amount must be greater than zero.');if($visible){mg_investment_require_permission($actor,'admin.investment.relations.publish');mg_investment_text($input['evidence_reference']??'',220,2,'Evidence reference');}
    $publicId=trim((string)($input['actual_id']??''));if($publicId!==''){$q=$pdo->prepare('SELECT * FROM investment_use_of_funds_actuals WHERE public_id=? AND round_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Actual identifier'),(int)$round['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);if(!$current)throw new MgInvestmentException('Use-of-funds actual not found.',404);if((int)$current['investor_visible']===1){$periodId=null;if(!empty($input['period_id'])){$p=$pdo->prepare('SELECT id FROM investment_reporting_periods WHERE public_id=? AND round_id=? LIMIT 1');$p->execute([mg_investment_text($input['period_id'],36,36,'Period identifier'),(int)$round['id']]);$periodId=(int)$p->fetchColumn()?:null;}$newPublic=['reporting_period_id'=>$periodId,'budget_category'=>mg_investment_text($input['budget_category']??'',180,2,'Budget category'),'amount_cents'=>$amount,'spent_at'=>mg_investment_date($input['spent_at']??null),'description'=>mg_investment_long_text($input['description']??'',6000,5,'Description'),'evidence_reference'=>mg_investment_audit_nullable_text($input['evidence_reference']??'',220)];foreach($newPublic as $field=>$value)if((string)($current[$field]??'')!==(string)($value??''))throw new MgInvestmentException('Investor-visible use-of-funds actuals are immutable. Create a correcting record.',409);}}
    return mg_investment_use_of_funds_actual_save($pdo,$actor,$input);
}

function mg_investment_governance_save_obligation_audited(PDO $pdo,array $actor,array $input): array
{
    $round=mg_investment_governance_round($pdo,$input['round_id']??'');$scope=(string)($input['recipient_scope']??'funded_investors');$investorId=(int)($input['investor_user_id']??0);$publication=(string)($input['portal_publication_status']??'not_required');$assigned=mg_investment_pipeline_admin_user_audited($pdo,$input['assigned_user_id']??0);
    if($scope==='specific_investor'&&!mg_investment_has_proven_funding_audited($pdo,(int)$round['id'],$investorId))throw new MgInvestmentException('Specific-investor reporting obligations require maker/checker verified funding.',409);
    if($publication==='published')mg_investment_require_permission($actor,'admin.investment.governance.publish');
    $publicId=trim((string)($input['obligation_id']??''));if($publicId!==''){$q=$pdo->prepare('SELECT * FROM investment_reporting_obligations WHERE public_id=? AND round_id=? LIMIT 1');$q->execute([mg_investment_text($publicId,36,36,'Obligation identifier'),(int)$round['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);if(!$current)throw new MgInvestmentException('Reporting obligation not found.',404);if((string)$current['portal_publication_status']==='published'){$newPublic=['investor_user_id'=>$investorId?:null,'obligation_type'=>(string)($input['obligation_type']??'quarterly_report'),'title'=>mg_investment_text($input['title']??'',220,2,'Obligation title'),'reporting_period'=>mg_investment_audit_nullable_text($input['reporting_period']??'',120),'due_at'=>mg_investment_governance_datetime($input['due_at']??'',true,'Obligation due date'),'recipient_scope'=>$scope,'completion_reference'=>mg_investment_audit_nullable_text($input['completion_reference']??'',220)];foreach($newPublic as $field=>$value)if((string)($current[$field]??'')!==(string)($value??''))throw new MgInvestmentException('Published reporting obligations are immutable. Archive and create a corrected obligation.',409);}}
    $safe=$input;$safe['assigned_user_id']=$assigned;return mg_investment_governance_save_obligation($pdo,$actor,$safe);
}

function mg_investment_portal_data_v5_final3(PDO $pdo,array $user): array
{
    $data=mg_investment_portal_data_v5_final2($pdo,$user);$userId=(int)$user['id'];$safe=[];
    foreach($data['rounds'] as $portalRound){$q=$pdo->prepare('SELECT id,visibility FROM investment_rounds WHERE public_id=? LIMIT 1');$q->execute([(string)$portalRound['id']]);$round=$q->fetch(PDO::FETCH_ASSOC);if(!$round)continue;$roundId=(int)$round['id'];$proven=mg_investment_has_proven_funding_audited($pdo,$roundId,$userId);if((string)$round['visibility']==='funded_investors'&&!$proven)continue;
        $signedQ=$pdo->prepare('SELECT COALESCE(SUM(signed_amount_cents),0) FROM investor_closing_records WHERE round_id=? AND signed_verification_source="maker_checker" AND status NOT IN ("withdrawn","declined")');$signedQ->execute([$roundId]);$portalRound['signed_cents']=(int)$signedQ->fetchColumn();$fundedQ=$pdo->prepare('SELECT COALESCE(SUM(verified_funded_cents),0) FROM investor_closing_records WHERE round_id=? AND funding_verification_source="maker_checker" AND status NOT IN ("withdrawn","declined")');$fundedQ->execute([$roundId]);$portalRound['funded_cents']=(int)$fundedQ->fetchColumn();
        $selectedQ=$pdo->prepare('SELECT COUNT(*) FROM investment_round_access WHERE round_id=? AND investor_user_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');$selectedQ->execute([$roundId,$userId]);$selected=(int)$selectedQ->fetchColumn()>0;$allowed=static fn(string $v):bool=>$v==='approved_investors'||$v==='public_summary'||($v==='selected_investors'&&$selected)||($v==='funded_investors'&&$proven);
        if(isset($portalRound['documents']))$portalRound['documents']=array_values(array_filter((array)$portalRound['documents'],static fn(array $d):bool=>$allowed((string)($d['visibility']??''))));
        if(isset($portalRound['diligence'])&&is_array($portalRound['diligence'])){$portalRound['diligence']['relationship']['funded']=$proven;$folders=[];foreach((array)($portalRound['diligence']['folders']??[]) as $folder)if($allowed((string)($folder['visibility']??'')))$folders[(string)$folder['public_id']]=$folder;$portalRound['diligence']['folders']=array_values($folders);$portalRound['diligence']['documents']=array_values(array_filter((array)($portalRound['diligence']['documents']??[]),static function(array $doc)use($allowed,$folders):bool{$folderId=(string)($doc['folder_public_id']??'');return $allowed((string)($doc['visibility']??''))&&($folderId===''||isset($folders[$folderId]));}));$commQ=$pdo->prepare('SELECT c.public_id,c.communication_type,c.subject,c.body,c.published_at,cr.first_viewed_at,cr.last_viewed_at,cr.view_count FROM investor_communications c INNER JOIN investor_communication_recipients cr ON cr.communication_id=c.id AND cr.investor_user_id=? WHERE c.round_id=? AND c.status="published" AND cr.status IN ("published","viewed") AND (c.audience_type<>"funded_investors" OR EXISTS(SELECT 1 FROM investor_closing_records cl WHERE cl.round_id=c.round_id AND cl.investor_user_id=? AND cl.verified_funded_cents>0 AND cl.funding_verification_source="maker_checker" AND cl.status NOT IN ("withdrawn","declined"))) ORDER BY c.published_at DESC');$commQ->execute([$userId,$roundId,$userId]);$portalRound['diligence']['communications']=$commQ->fetchAll(PDO::FETCH_ASSOC);}
        if(!$proven){$portalRound['relations']=null;$portalRound['governance']=null;}
        $safe[]=$portalRound;
    }
    $data['rounds']=$safe;return $data;
}

function mg_investment_portal_accessible_round_final3(PDO $pdo,array $user,string $roundPublicId): array
{
    if(!in_array('investor',is_array($user['roles']??null)?$user['roles']:[],true))throw new MgInvestmentException('Investor access is not active.',403);$userId=(int)$user['id'];$profileQ=$pdo->prepare('SELECT COUNT(*) FROM investor_profiles WHERE user_id=? AND status="active"');$profileQ->execute([$userId]);if((int)$profileQ->fetchColumn()<1)throw new MgInvestmentException('Investor profile is not active.',403);
    $q=$pdo->prepare('SELECT r.* FROM investment_rounds r INNER JOIN investment_round_publication p ON p.round_id=r.id WHERE r.public_id=? AND p.publication_status IN ("private_preview","published") AND r.status IN ("private_preview","open","minimum_reached","closing","closed") AND (r.visibility="approved_investors" OR (r.visibility="selected_investors" AND EXISTS(SELECT 1 FROM investment_round_access a WHERE a.round_id=r.id AND a.investor_user_id=? AND a.status="granted" AND (a.expires_at IS NULL OR a.expires_at>NOW()))) OR (r.visibility="funded_investors" AND EXISTS(SELECT 1 FROM investor_closing_records c WHERE c.round_id=r.id AND c.investor_user_id=? AND c.verified_funded_cents>0 AND c.funding_verification_source="maker_checker" AND c.status NOT IN ("withdrawn","declined")))) LIMIT 1');$q->execute([$roundPublicId,$userId,$userId]);$round=$q->fetch(PDO::FETCH_ASSOC);if(!$round)throw new MgInvestmentException('Investment round is not available.',404);return $round;
}

function mg_investment_portal_event_v5_final3(PDO $pdo,array $user,array $input): array
{
    $event=(string)($input['event_type']??'');$round=mg_investment_portal_accessible_round_final3($pdo,$user,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$roundId=(int)$round['id'];$userId=(int)$user['id'];$subjectId=mg_investment_text($input['subject_id']??'',36,1,'Subject identifier');$proven=mg_investment_has_proven_funding_audited($pdo,$roundId,$userId);
    if($event==='document_open'){$q=$pdo->prepare('SELECT visibility FROM investment_documents WHERE public_id=? AND workspace_id=? AND status="published" LIMIT 1');$q->execute([$subjectId,(int)$round['workspace_id']]);if((string)$q->fetchColumn()==='funded_investors'&&!$proven)throw new MgInvestmentException('Verified funded access is required for this document.',403);return mg_investment_portal_event_v2_final($pdo,$user,$input);}
    if(in_array($event,['metric_view','round_view'],true))return mg_investment_portal_event_v2_final($pdo,$user,$input);
    if(in_array($event,['communication_view','qa_view'],true)){if($event==='communication_view'){$q=$pdo->prepare('SELECT audience_type FROM investor_communications WHERE public_id=? AND round_id=? AND status="published" LIMIT 1');$q->execute([$subjectId,$roundId]);if((string)$q->fetchColumn()==='funded_investors'&&!$proven)throw new MgInvestmentException('Verified funded access is required for this communication.',403);}return mg_investment_portal_event_final2($pdo,$user,$input);}
    if(in_array($event,['closing_document_open','report_view','meeting_summary_view','governance_document_open','tax_document_open','material_notice_view'],true)&&!$proven)throw new MgInvestmentException('Maker/checker verified funded access is required.',403);
    return mg_investment_portal_event_v5($pdo,$user,$input);
}

function mg_investment_portal_submit_diligence_v5_final3(PDO $pdo,array $user,array $input): array
{
    $roundId=mg_investment_text($input['round_id']??'',36,36,'Round identifier');mg_investment_portal_accessible_round_final3($pdo,$user,$roundId);$q=$pdo->prepare('SELECT COUNT(*) FROM investor_diligence_requests WHERE investor_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');$q->execute([(int)$user['id']]);if((int)$q->fetchColumn()>=10)throw new MgInvestmentException('Diligence request limit reached. Try again later.',429);mg_investment_audit_transaction($pdo,static fn():array=>mg_investment_portal_submit_diligence($pdo,$user,$input));return mg_investment_portal_data_v5_final3($pdo,$user);
}

function mg_investment_portal_submit_interest_v5_final3(PDO $pdo,array $user,array $input): array
{
    $roundId=mg_investment_text($input['round_id']??'',36,36,'Round identifier');mg_investment_portal_accessible_round_final3($pdo,$user,$roundId);$q=$pdo->prepare('SELECT COUNT(*) FROM investor_interest_submissions WHERE investor_user_id=? AND round_id=(SELECT id FROM investment_rounds WHERE public_id=? LIMIT 1) AND created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)');$q->execute([(int)$user['id'],$roundId]);if((int)$q->fetchColumn()>=3)throw new MgInvestmentException('Interest submission limit reached for this round. Update the discussion through the investor team.',429);mg_investment_audit_transaction($pdo,static fn():array=>mg_investment_portal_submit_interest($pdo,$user,$input));return mg_investment_portal_data_v5_final3($pdo,$user);
}

function mg_investment_portal_acknowledge_notice_v5_final3(PDO $pdo,array $user,array $input): array
{
    $round=mg_investment_portal_accessible_round_final3($pdo,$user,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));if(!mg_investment_has_proven_funding_audited($pdo,(int)$round['id'],(int)$user['id']))throw new MgInvestmentException('Maker/checker verified funded access is required.',403);mg_investment_portal_acknowledge_notice_v5($pdo,$user,$input);return mg_investment_portal_data_v5_final3($pdo,$user);
}
