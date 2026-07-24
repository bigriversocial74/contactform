<?php
declare(strict_types=1);

function mg_investment_has_proven_signed_audited(PDO $pdo,int $roundId,int $investorUserId): bool
{
    $q=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND signed_amount_cents>0 AND signed_verification_source="maker_checker" AND status NOT IN ("withdrawn","declined")');
    $q->execute([$roundId,$investorUserId]);
    return (int)$q->fetchColumn()>0;
}

function mg_investment_has_proven_funding_audited(PDO $pdo,int $roundId,int $investorUserId): bool
{
    $q=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0 AND funding_verification_source="maker_checker" AND status NOT IN ("withdrawn","declined")');
    $q->execute([$roundId,$investorUserId]);
    return (int)$q->fetchColumn()>0;
}

function mg_investment_closing_sync_audited(PDO $pdo,?int $actorUserId=null): array
{
    return mg_investment_audit_transaction($pdo,function()use($pdo,$actorUserId):array{
        $profiles=$pdo->prepare("INSERT INTO investment_closing_profiles
          (public_id,round_id,stage,readiness_score,counsel_status,board_status,created_by_user_id,updated_by_user_id,created_at,updated_at)
          SELECT UUID(),r.id,'planning',0,
                 CASE WHEN r.counsel_status='approved' THEN 'approved' WHEN r.counsel_status='requested' THEN 'requested' ELSE 'not_started' END,
                 'not_started',?,?,NOW(),NOW()
          FROM investment_rounds r
          LEFT JOIN investment_closing_profiles cp ON cp.round_id=r.id
          WHERE cp.id IS NULL");
        $profiles->execute([$actorUserId,$actorUserId]);

        $records=$pdo->prepare("INSERT INTO investor_closing_records
          (public_id,round_id,investor_user_id,status,instrument_type,proposed_amount_cents,final_amount_cents,signed_amount_cents,signed_verification_source,reported_funded_cents,verified_funded_cents,funding_verification_source,created_by_user_id,updated_by_user_id,created_at,updated_at)
          SELECT UUID(),ri.round_id,ri.investor_user_id,
            CASE WHEN ri.funded_cents>0 THEN 'funds_reported' WHEN ri.soft_commitment_cents>0 OR ri.signed_cents>0 THEN 'soft_committed' ELSE 'interested' END,
            CASE r.instrument_type WHEN 'post_money_safe' THEN 'post_money_safe' WHEN 'convertible_note' THEN 'convertible_note' WHEN 'priced_equity' THEN 'priced_equity' ELSE 'not_finalized' END,
            ri.indicated_interest_cents,GREATEST(ri.soft_commitment_cents,ri.signed_cents),0,'unverified',ri.funded_cents,0,'unverified',?,?,NOW(),NOW()
          FROM investor_round_interests ri
          INNER JOIN investment_rounds r ON r.id=ri.round_id
          LEFT JOIN investor_closing_records cr ON cr.round_id=ri.round_id AND cr.investor_user_id=ri.investor_user_id
          WHERE cr.id IS NULL AND ri.status NOT IN ('passed','declined','archived')");
        $records->execute([$actorUserId,$actorUserId]);
        return ['profiles_created'=>$profiles->rowCount(),'records_created'=>$records->rowCount()];
    });
}

function mg_investment_closing_dashboard_audited(PDO $pdo,array $filters=[]): array
{
    $roundPublicId=trim((string)($filters['round_id']??''));$roundId=null;
    if($roundPublicId!=='')$roundId=(int)mg_investment_closing_round($pdo,$roundPublicId)['id'];
    $rounds=$pdo->query('SELECT r.public_id,r.public_name,r.status,r.instrument_type,r.minimum_raise_cents,r.target_raise_cents,r.maximum_raise_cents,r.signed_cents,r.funded_cents,r.counsel_status,cp.stage AS closing_stage,cp.readiness_score,cp.planned_first_close_at,cp.planned_final_close_at FROM investment_rounds r LEFT JOIN investment_closing_profiles cp ON cp.round_id=r.id ORDER BY r.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $admins=$pdo->query('SELECT DISTINCT u.id,u.full_name,u.email FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ("admin","super_admin") ORDER BY u.full_name')->fetchAll(PDO::FETCH_ASSOC);
    $params=[];$where='';if($roundId!==null){$where=' WHERE cr.round_id=?';$params[]=$roundId;}
    $stmt=$pdo->prepare('SELECT cr.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,ip.firm_name,b.public_id AS batch_public_id,b.batch_name,
      ob.kyc_status,ob.accreditation_status,ob.counsel_status AS onboarding_counsel_status,
      (SELECT COUNT(*) FROM investment_closing_packets p WHERE p.round_id=cr.round_id AND p.investor_user_id=cr.investor_user_id) AS packet_count,
      (SELECT COUNT(*) FROM investment_financial_verification_requests vr WHERE vr.closing_record_id=cr.id AND vr.status="pending") AS pending_verifications
      FROM investor_closing_records cr INNER JOIN investment_rounds r ON r.id=cr.round_id INNER JOIN users u ON u.id=cr.investor_user_id LEFT JOIN investor_profiles ip ON ip.user_id=cr.investor_user_id LEFT JOIN investment_closing_batches b ON b.id=cr.batch_id LEFT JOIN investor_onboarding_reviews ob ON ob.round_id=cr.round_id AND ob.investor_user_id=cr.investor_user_id'.$where.' ORDER BY FIELD(cr.status,"funds_reported","funding_pending","signed","investor_reviewing","documents_sent","documents_requested","soft_committed","interested","funds_verified","included_in_closing","closing_complete","withdrawn","declined"),cr.updated_at DESC');
    $stmt->execute($params);$records=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $whereB=$roundId!==null?' WHERE b.round_id='.(int)$roundId:'';
    $batches=$pdo->query('SELECT b.*,r.public_id AS round_public_id,r.public_name,(SELECT COUNT(*) FROM investment_closing_batch_investors bi WHERE bi.batch_id=b.id) AS investor_count FROM investment_closing_batches b INNER JOIN investment_rounds r ON r.id=b.round_id'.$whereB.' ORDER BY r.updated_at DESC,b.sequence_number')->fetchAll(PDO::FETCH_ASSOC);
    $whereC=$roundId!==null?' WHERE c.round_id='.(int)$roundId:'';
    $compliance=$pdo->query('SELECT c.*,r.public_id AS round_public_id,r.public_name,u.full_name AS assigned_name FROM investment_compliance_requirements c INNER JOIN investment_rounds r ON r.id=c.round_id LEFT JOIN users u ON u.id=c.assigned_user_id'.$whereC.' ORDER BY FIELD(c.status,"overdue","changes_required","in_progress","requested","not_started","filed","confirmed","approved","not_applicable"),COALESCE(c.due_at,"2999-12-31"),c.id')->fetchAll(PDO::FETCH_ASSOC);
    $whereV=$roundId!==null?' WHERE vr.round_id='.(int)$roundId:'';
    $verifications=$pdo->query('SELECT vr.*,cr.public_id AS closing_record_public_id,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,s.full_name AS submitted_by_name,d.decision,d.decision_notes,rv.full_name AS reviewer_name FROM investment_financial_verification_requests vr INNER JOIN investor_closing_records cr ON cr.id=vr.closing_record_id INNER JOIN investment_rounds r ON r.id=vr.round_id INNER JOIN users u ON u.id=cr.investor_user_id INNER JOIN users s ON s.id=vr.submitted_by_user_id LEFT JOIN investment_financial_verification_decisions d ON d.request_id=vr.id LEFT JOIN users rv ON rv.id=d.reviewer_user_id'.$whereV.' ORDER BY FIELD(vr.status,"pending","approved","rejected","cancelled"),vr.submitted_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $whereP=$roundId!==null?' WHERE p.round_id='.(int)$roundId:'';
    $packets=$pdo->query('SELECT p.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,(SELECT COUNT(*) FROM investment_closing_documents d WHERE d.packet_id=p.id) AS document_count FROM investment_closing_packets p INNER JOIN investment_rounds r ON r.id=p.round_id INNER JOIN users u ON u.id=p.investor_user_id'.$whereP.' ORDER BY p.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $whereR=$roundId!==null?' WHERE rp.round_id='.(int)$roundId:'';
    $reports=$pdo->query('SELECT rp.*,r.public_id AS round_public_id,r.public_name,(SELECT MAX(version_number) FROM investment_reporting_snapshots s WHERE s.reporting_period_id=rp.id) AS latest_version FROM investment_reporting_periods rp INNER JOIN investment_rounds r ON r.id=rp.round_id'.$whereR.' ORDER BY rp.starts_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $summary=['investors'=>count($records),'awaiting_documents'=>0,'signed'=>0,'funding_pending'=>0,'funds_reported'=>0,'funds_verified'=>0,'closing_complete'=>0,'pending_verifications'=>0,'open_compliance'=>0,'overdue_compliance'=>0,'verified_funded_cents'=>0,'readiness_score'=>0,'unproven_signed'=>0,'unproven_funded'=>0];
    foreach($records as $row){
        if(in_array($row['status'],['documents_requested','documents_sent','investor_reviewing'],true))$summary['awaiting_documents']++;
        if((int)$row['signed_amount_cents']>0&&$row['signed_verification_source']==='maker_checker')$summary['signed']++;
        elseif((int)$row['signed_amount_cents']>0)$summary['unproven_signed']++;
        if($row['status']==='funding_pending')$summary['funding_pending']++;
        if($row['status']==='funds_reported')$summary['funds_reported']++;
        if((int)$row['verified_funded_cents']>0&&$row['funding_verification_source']==='maker_checker'){$summary['funds_verified']++;$summary['verified_funded_cents']+=(int)$row['verified_funded_cents'];}
        elseif((int)$row['verified_funded_cents']>0)$summary['unproven_funded']++;
        if($row['status']==='closing_complete')$summary['closing_complete']++;
        $summary['pending_verifications']+=(int)$row['pending_verifications'];
    }
    foreach($compliance as $row){if(!in_array($row['status'],['confirmed','approved','not_applicable'],true))$summary['open_compliance']++;if($row['status']==='overdue'||(!empty($row['due_at'])&&strtotime((string)$row['due_at'])<time()&&!in_array($row['status'],['confirmed','approved','not_applicable'],true)))$summary['overdue_compliance']++;}
    if($roundId!==null){$q=$pdo->prepare('SELECT readiness_score FROM investment_closing_profiles WHERE round_id=?');$q->execute([$roundId]);$summary['readiness_score']=(int)$q->fetchColumn();}
    return compact('rounds','admins','records','batches','compliance','verifications','packets','reports','summary');
}

function mg_investment_recalculate_round_totals_audited(PDO $pdo,int $roundId,int $actorId): array
{
    $softQ=$pdo->prepare('SELECT COALESCE(SUM(soft_commitment_cents),0) FROM investor_round_interests WHERE round_id=? AND status NOT IN ("passed","declined","archived")');$softQ->execute([$roundId]);$soft=(int)$softQ->fetchColumn();
    $signedQ=$pdo->prepare('SELECT COALESCE(SUM(signed_amount_cents),0) FROM investor_closing_records WHERE round_id=? AND signed_verification_source="maker_checker" AND status NOT IN ("withdrawn","declined")');$signedQ->execute([$roundId]);$signed=(int)$signedQ->fetchColumn();
    $fundedQ=$pdo->prepare('SELECT COALESCE(SUM(verified_funded_cents),0) FROM investor_closing_records WHERE round_id=? AND funding_verification_source="maker_checker" AND status NOT IN ("withdrawn","declined")');$fundedQ->execute([$roundId]);$funded=(int)$fundedQ->fetchColumn();
    $pdo->prepare('UPDATE investment_rounds SET soft_commitment_cents=?,signed_cents=?,funded_cents=?,status=CASE WHEN status IN ("cancelled","paused","closed") THEN status WHEN ?>=target_raise_cents AND target_raise_cents>0 THEN "minimum_reached" ELSE status END,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$soft,$signed,$funded,$funded,$actorId,$roundId]);
    return ['soft_commitment_cents'=>$soft,'signed_cents'=>$signed,'funded_cents'=>$funded];
}

function mg_investment_closing_save_profile_audited(PDO $pdo,array $actor,array $input): array
{
    $round=mg_investment_closing_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));
    $q=$pdo->prepare('SELECT readiness_score FROM investment_closing_profiles WHERE round_id=? LIMIT 1');$q->execute([(int)$round['id']]);$safe=$input;$safe['readiness_score']=(int)$q->fetchColumn();
    if((string)($safe['stage']??'')==='complete'&&($safe['counsel_status']??'')!=='approved')throw new MgInvestmentException('Closing cannot be marked complete without approved counsel status.',409);
    return mg_investment_closing_save_profile($pdo,$actor,$safe);
}

function mg_investment_closing_save_record_audited(PDO $pdo,array $actor,array $input): array
{
    $record=mg_investment_closing_record($pdo,mg_investment_text($input['record_id']??'',36,36,'Closing record identifier'));
    $status=(string)($input['status']??$record['status']);
    if(in_array($status,['signed','funding_pending','funds_reported','funds_verified','included_in_closing','closing_complete'],true)&&$record['signed_verification_source']!=='maker_checker')throw new MgInvestmentException('Maker/checker signed verification is required for this closing status.',409);
    if(in_array($status,['funds_verified','included_in_closing','closing_complete'],true)&&$record['funding_verification_source']!=='maker_checker')throw new MgInvestmentException('Maker/checker funded verification is required for this closing status.',409);
    if((string)$record['status']==='closing_complete'){
        $core=['status'=>$status,'instrument_type'=>(string)($input['instrument_type']??$record['instrument_type']),'agreement_reference'=>mg_investment_audit_nullable_text($input['agreement_reference']??'',220),'funding_reference'=>mg_investment_audit_nullable_text($input['funding_reference']??'',220)];
        foreach($core as $field=>$value)if((string)($record[$field]??'')!==(string)($value??''))throw new MgInvestmentException('Completed investor closing records are immutable. Reopen the closing batch before changing closing terms.',409);
    }
    return mg_investment_closing_save_record($pdo,$actor,$input);
}

function mg_investment_financial_request_audited(PDO $pdo,array $actor,array $input): array
{
    $type=(string)($input['verification_type']??'funded_amount');
    if($type==='adjustment')throw new MgInvestmentException('Use an explicit signed or funded verification/reversal type; generic financial adjustments are not allowed.',409);
    $amount=mg_investment_money($input['requested_amount']??0);
    if($amount>0)mg_investment_text($input['evidence_reference']??'',220,2,'Evidence reference');
    return mg_investment_financial_request($pdo,$actor,$input);
}

function mg_investment_financial_decide_audited(PDO $pdo,array $actor,array $input): array
{
    $requestPublicId=mg_investment_text($input['request_id']??'',36,36,'Verification request identifier');
    $q=$pdo->prepare('SELECT vr.verification_type,vr.round_id,vr.closing_record_id,r.public_id AS round_public_id FROM investment_financial_verification_requests vr INNER JOIN investment_rounds r ON r.id=vr.round_id WHERE vr.public_id=? LIMIT 1');$q->execute([$requestPublicId]);$request=$q->fetch(PDO::FETCH_ASSOC);if(!$request)throw new MgInvestmentException('Financial verification request not found.',404);
    mg_investment_financial_decide($pdo,$actor,$input);
    if((string)($input['decision']??'approved')==='approved'){
        if(in_array($request['verification_type'],['signed_amount','signed_reversal'],true))$pdo->prepare('UPDATE investor_closing_records SET signed_verification_source="maker_checker",updated_at=NOW() WHERE id=?')->execute([(int)$request['closing_record_id']]);
        if(in_array($request['verification_type'],['funded_amount','funded_reversal'],true))$pdo->prepare('UPDATE investor_closing_records SET funding_verification_source="maker_checker",updated_at=NOW() WHERE id=?')->execute([(int)$request['closing_record_id']]);
        mg_investment_recalculate_round_totals_audited($pdo,(int)$request['round_id'],(int)$actor['id']);
    }
    return mg_investment_closing_dashboard_audited($pdo,['round_id'=>$request['round_public_id']]);
}

function mg_investment_closing_assign_batch_audited(PDO $pdo,array $actor,array $input): array
{
    $record=mg_investment_closing_record($pdo,mg_investment_text($input['record_id']??'',36,36,'Closing record identifier'));
    if($record['signed_verification_source']!=='maker_checker'||$record['funding_verification_source']!=='maker_checker'||(int)$record['verified_funded_cents']<1)throw new MgInvestmentException('Only maker/checker verified funded records can be assigned to a closing batch.',409);
    return mg_investment_closing_assign_batch($pdo,$actor,$input);
}

function mg_investment_closing_complete_batch_audited(PDO $pdo,array $actor,array $input): array
{
    $batchId=mg_investment_text($input['batch_id']??'',36,36,'Batch identifier');$q=$pdo->prepare('SELECT COUNT(*) FROM investment_closing_batch_investors bi INNER JOIN investor_closing_records cr ON cr.id=bi.closing_record_id WHERE bi.batch_id=(SELECT id FROM investment_closing_batches WHERE public_id=? LIMIT 1) AND (cr.signed_verification_source<>"maker_checker" OR cr.funding_verification_source<>"maker_checker" OR bi.included_amount_cents>cr.verified_funded_cents)');$q->execute([$batchId]);if((int)$q->fetchColumn()>0)throw new MgInvestmentException('Every included investor requires maker/checker provenance and an included amount within verified funding.',409);
    return mg_investment_closing_complete_batch($pdo,$actor,$input);
}

function mg_investment_closing_reopen_batch_audited(PDO $pdo,array $actor,array $input): array
{
    if(!mg_investment_is_super($actor))throw new MgInvestmentException('Only Super Admin can reopen a completed closing batch.',403);$reason=mg_investment_long_text($input['reason']??'',2000,10,'Reopen reason');$batchPublicId=mg_investment_text($input['batch_id']??'',36,36,'Batch identifier');$actorId=(int)$actor['id'];
    return mg_investment_audit_transaction($pdo,function()use($pdo,$batchPublicId,$reason,$actorId):array{
        $q=$pdo->prepare('SELECT b.*,r.public_id AS round_public_id FROM investment_closing_batches b INNER JOIN investment_rounds r ON r.id=b.round_id WHERE b.public_id=? LIMIT 1 FOR UPDATE');$q->execute([$batchPublicId]);$batch=$q->fetch(PDO::FETCH_ASSOC);if(!$batch)throw new MgInvestmentException('Closing batch not found.',404);if($batch['locked_at']===null)throw new MgInvestmentException('Only completed batches can be reopened.',409);
        $pdo->prepare('UPDATE investment_closing_batches SET status="reopened",locked_at=NULL,locked_by_user_id=NULL,actual_close_at=NULL,notes=CONCAT(COALESCE(notes,""),"\nReopened: ",?),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$reason,$actorId,(int)$batch['id']]);
        $records=$pdo->prepare('SELECT cr.id,cr.status,cr.signed_amount_cents,cr.signed_verification_source,cr.verified_funded_cents,cr.funding_verification_source FROM investment_closing_batch_investors bi INNER JOIN investor_closing_records cr ON cr.id=bi.closing_record_id WHERE bi.batch_id=? FOR UPDATE');$records->execute([(int)$batch['id']]);
        foreach($records->fetchAll(PDO::FETCH_ASSOC) as $record){$next=$record['funding_verification_source']==='maker_checker'&&(int)$record['verified_funded_cents']>0?'included_in_closing':($record['signed_verification_source']==='maker_checker'&&(int)$record['signed_amount_cents']>0?'signed':'soft_committed');$pdo->prepare('UPDATE investor_closing_records SET status=?,closing_completed_at=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$next,$actorId,(int)$record['id']]);mg_investment_closing_event($pdo,(int)$record['id'],$actorId,'adjustment',(string)$record['status'],$next,null,['batch_id'=>$batchPublicId,'reason'=>$reason,'reopened'=>true]);}
        mg_audit('investment_closing_batch_reopened','investment_closing_batch',['batch_id'=>$batchPublicId,'reason'=>$reason],$actorId);return mg_investment_closing_dashboard_audited($pdo,['round_id'=>$batch['round_public_id']]);
    });
}

function mg_investment_governance_refresh_holdings_audited(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.governance.manage');$round=mg_investment_governance_round($pdo,$input['round_id']??'');$actorId=(int)$actor['id'];
    return mg_investment_audit_transaction($pdo,function()use($pdo,$round,$actorId,$input):array{
        $q=$pdo->prepare('SELECT cr.*,b.batch_name,b.public_id AS batch_public_id FROM investor_closing_records cr LEFT JOIN investment_closing_batches b ON b.id=cr.batch_id WHERE cr.round_id=? AND cr.verified_funded_cents>0 AND cr.funding_verification_source="maker_checker" AND cr.status NOT IN ("withdrawn","declined")');$q->execute([(int)$round['id']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $eligible=array_map(static fn(array $r):int=>(int)$r['investor_user_id'],$rows);if($eligible===[])$pdo->prepare('DELETE FROM investment_holdings_references WHERE round_id=?')->execute([(int)$round['id']]);else{$marks=implode(',',array_fill(0,count($eligible),'?'));$pdo->prepare('DELETE FROM investment_holdings_references WHERE round_id=? AND investor_user_id NOT IN ('.$marks.')')->execute([(int)$round['id'],...$eligible]);}
        $reconQ=$pdo->prepare('SELECT public_id FROM investment_cap_reconciliation_snapshots WHERE round_id=? ORDER BY created_at DESC LIMIT 1');$reconQ->execute([(int)$round['id']]);$recon=$reconQ->fetchColumn()?:null;
        foreach($rows as $row){$rightsQ=$pdo->prepare('SELECT COUNT(*) FROM investment_investor_rights WHERE round_id=? AND investor_user_id=? AND status="active"');$rightsQ->execute([(int)$round['id'],(int)$row['investor_user_id']]);$rights=(int)$rightsQ->fetchColumn()>0?'active':'none_recorded';$taxQ=$pdo->prepare('SELECT COUNT(*) FROM investment_tax_documents WHERE round_id=? AND investor_user_id=? AND status="published"');$taxQ->execute([(int)$round['id'],(int)$row['investor_user_id']]);$tax=(int)$taxQ->fetchColumn()>0?'available':'not_started';$snapshot=['round_public_id'=>$round['public_id'],'closing_record_public_id'=>$row['public_id'],'investor_user_id'=>(int)$row['investor_user_id'],'instrument_type'=>$row['instrument_type'],'verified_funded_cents'=>(int)$row['verified_funded_cents'],'funding_verification_source'=>'maker_checker','agreement_reference'=>$row['agreement_reference'],'batch_public_id'=>$row['batch_public_id']];$publicId=mg_investment_uuid();$pdo->prepare('INSERT INTO investment_holdings_references (public_id,round_id,investor_user_id,closing_record_id,instrument_type,verified_funded_cents,closing_batch_reference,agreement_reference,conversion_or_maturity_reference,information_rights_status,tax_document_status,latest_reconciliation_public_id,source_snapshot_json,generated_by_user_id,generated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE public_id=VALUES(public_id),closing_record_id=VALUES(closing_record_id),instrument_type=VALUES(instrument_type),verified_funded_cents=VALUES(verified_funded_cents),closing_batch_reference=VALUES(closing_batch_reference),agreement_reference=VALUES(agreement_reference),conversion_or_maturity_reference=VALUES(conversion_or_maturity_reference),information_rights_status=VALUES(information_rights_status),tax_document_status=VALUES(tax_document_status),latest_reconciliation_public_id=VALUES(latest_reconciliation_public_id),source_snapshot_json=VALUES(source_snapshot_json),generated_by_user_id=VALUES(generated_by_user_id),generated_at=NOW()')->execute([$publicId,(int)$round['id'],(int)$row['investor_user_id'],(int)$row['id'],(string)$row['instrument_type'],(int)$row['verified_funded_cents'],$row['batch_name']?:$row['batch_public_id'],$row['agreement_reference'],null,$rights,$tax,$recon,mg_investment_json_encode($snapshot),$actorId]);}
        mg_audit('investment_holdings_references_refreshed','investment_round',['round_id'=>$round['public_id'],'records'=>count($rows),'maker_checker_only'=>true],$actorId);return mg_investment_governance_dashboard_audited($pdo,$input);
    });
}

function mg_investment_governance_save_right_audited_v2(PDO $pdo,array $actor,array $input): array
{
    $round=mg_investment_governance_round($pdo,$input['round_id']??'');$investorId=(int)($input['investor_user_id']??0);if(!mg_investment_has_proven_funding_audited($pdo,(int)$round['id'],$investorId))throw new MgInvestmentException('Investor rights require maker/checker verified funded money for this round.',409);return mg_investment_governance_save_right_audited($pdo,$actor,$input);
}

function mg_investment_governance_save_tax_document_audited_v2(PDO $pdo,array $actor,array $input): array
{
    $round=mg_investment_governance_round($pdo,$input['round_id']??'');$investorId=(int)($input['investor_user_id']??0);if(!mg_investment_has_proven_funding_audited($pdo,(int)$round['id'],$investorId))throw new MgInvestmentException('Tax and annual documents require maker/checker verified funded money.',409);return mg_investment_governance_save_tax_document_audited($pdo,$actor,$input);
}
