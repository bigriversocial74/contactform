<?php
declare(strict_types=1);

function mg_investment_financial_decide_audited_v3(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.closing.verify');
    $requestPublicId=mg_investment_text($input['request_id']??'',36,36,'Verification request identifier');
    $decision=(string)($input['decision']??'approved');
    if(!in_array($decision,['approved','rejected'],true))throw new MgInvestmentException('Invalid verification decision.');
    $notes=mg_investment_long_text($input['decision_notes']??'',4000,10,'Decision notes');
    $actorId=(int)$actor['id'];
    $auditPayload=[];

    $result=mg_investment_audit_transaction($pdo,function()use($pdo,$requestPublicId,$decision,$notes,$actorId,&$auditPayload):array{
        $q=$pdo->prepare('SELECT vr.*,cr.public_id AS record_public_id,cr.investor_user_id,cr.signed_amount_cents,cr.signed_verification_source,cr.verified_funded_cents,cr.funding_verification_source,cr.status AS record_status,r.public_id AS round_public_id FROM investment_financial_verification_requests vr INNER JOIN investor_closing_records cr ON cr.id=vr.closing_record_id INNER JOIN investment_rounds r ON r.id=vr.round_id WHERE vr.public_id=? LIMIT 1 FOR UPDATE');
        $q->execute([$requestPublicId]);
        $request=$q->fetch(PDO::FETCH_ASSOC);
        if(!$request)throw new MgInvestmentException('Financial verification request not found.',404);
        if((string)$request['status']!=='pending')throw new MgInvestmentException('This financial verification request has already been resolved.',409);
        if((int)$request['submitted_by_user_id']===$actorId)throw new MgInvestmentException('The submitting administrator cannot approve or reject their own financial verification request.',409);

        $pdo->prepare('INSERT INTO investment_financial_verification_decisions (public_id,request_id,reviewer_user_id,decision,decision_notes,created_at) VALUES (?,?,?,?,?,NOW())')->execute([mg_investment_uuid(),(int)$request['id'],$actorId,$decision,$notes]);
        $pdo->prepare('UPDATE investment_financial_verification_requests SET status=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$decision,(int)$request['id']]);

        $type=(string)$request['verification_type'];
        $amount=(int)$request['requested_amount_cents'];
        $newStatus=(string)$request['record_status'];
        $totals=null;

        if($decision==='approved'){
            if(in_array($type,['signed_amount','signed_reversal'],true)){
                $fundedIsProven=(string)$request['funding_verification_source']==='maker_checker'&&(int)$request['verified_funded_cents']>0;
                if($amount<(int)$request['verified_funded_cents'])throw new MgInvestmentException('Signed money cannot be lower than maker/checker verified funded money.',409);
                $newStatus=$amount>0?($fundedIsProven?'funds_verified':'signed'):'soft_committed';
                $pdo->prepare('UPDATE investor_closing_records SET signed_amount_cents=?,signed_verification_source="maker_checker",status=?,investor_signed_at=IF(? > 0,COALESCE(investor_signed_at,NOW()),NULL),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$amount,$newStatus,$amount,$actorId,(int)$request['closing_record_id']]);
                $pdo->prepare('UPDATE investor_round_interests SET signed_cents=?,status=CASE WHEN funded_cents>0 THEN "funded" WHEN ?>0 THEN "signed" WHEN soft_commitment_cents>0 THEN "soft_committed" ELSE "interested" END,updated_by_user_id=?,last_activity_at=NOW(),updated_at=NOW() WHERE round_id=? AND investor_user_id=?')->execute([$amount,$amount,$actorId,(int)$request['round_id'],(int)$request['investor_user_id']]);
                mg_investment_closing_event($pdo,(int)$request['closing_record_id'],$actorId,'adjustment',(string)$request['record_status'],$newStatus,$amount,['verification_type'=>$type,'request_id'=>$requestPublicId,'decision_notes'=>$notes,'verification_source'=>'maker_checker']);
            }elseif(in_array($type,['funded_amount','funded_reversal'],true)){
                if((string)$request['signed_verification_source']!=='maker_checker'&&$amount>0)throw new MgInvestmentException('Maker/checker signed verification is required before funded verification.',409);
                if($amount>(int)$request['signed_amount_cents'])throw new MgInvestmentException('Verified funded amount cannot exceed maker/checker verified signed money.',409);
                $newStatus=$amount>0?'funds_verified':(((string)$request['signed_verification_source']==='maker_checker'&&(int)$request['signed_amount_cents']>0)?'signed':'soft_committed');
                $pdo->prepare('UPDATE investor_closing_records SET verified_funded_cents=?,reported_funded_cents=?,funding_verification_source="maker_checker",status=?,funds_verified_at=IF(? > 0,NOW(),NULL),verified_by_user_id=IF(? > 0,?,NULL),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$amount,$amount,$newStatus,$amount,$amount,$actorId,$actorId,(int)$request['closing_record_id']]);
                $pdo->prepare('UPDATE investor_round_interests SET funded_cents=?,status=CASE WHEN ?>0 THEN "funded" WHEN signed_cents>0 THEN "signed" WHEN soft_commitment_cents>0 THEN "soft_committed" ELSE "interested" END,updated_by_user_id=?,last_activity_at=NOW(),updated_at=NOW() WHERE round_id=? AND investor_user_id=?')->execute([$amount,$amount,$actorId,(int)$request['round_id'],(int)$request['investor_user_id']]);
                mg_investment_closing_event($pdo,(int)$request['closing_record_id'],$actorId,'funding_verified',(string)$request['record_status'],$newStatus,$amount,['verification_type'=>$type,'request_id'=>$requestPublicId,'decision_notes'=>$notes,'verification_source'=>'maker_checker']);
            }else{
                throw new MgInvestmentException('Generic financial adjustments are not allowed.',409);
            }

            $totals=mg_investment_recalculate_round_totals_audited($pdo,(int)$request['round_id'],$actorId);

            $aggregate=$pdo->prepare('SELECT COALESCE(SUM(CASE WHEN funding_verification_source="maker_checker" THEN verified_funded_cents ELSE 0 END),0) AS funded,COALESCE(SUM(CASE WHEN signed_verification_source="maker_checker" THEN signed_amount_cents ELSE 0 END),0) AS signed FROM investor_closing_records WHERE investor_user_id=? AND status NOT IN ("withdrawn","declined")');
            $aggregate->execute([(int)$request['investor_user_id']]);
            $money=$aggregate->fetch(PDO::FETCH_ASSOC)?:['funded'=>0,'signed'=>0];
            $softQ=$pdo->prepare('SELECT COALESCE(SUM(soft_commitment_cents),0) FROM investor_round_interests WHERE investor_user_id=? AND status NOT IN ("passed","declined","archived")');
            $softQ->execute([(int)$request['investor_user_id']]);
            $soft=(int)$softQ->fetchColumn();
            $pipelineStage=(int)$money['funded']>0?'funded':((int)$money['signed']>0?'signed':($soft>0?'soft_committed':'interested'));
            $pdo->prepare('UPDATE investor_pipeline_records SET stage=?,updated_by_user_id=?,updated_at=NOW() WHERE investor_user_id=? AND stage NOT IN ("passed","declined","archived")')->execute([$pipelineStage,$actorId,(int)$request['investor_user_id']]);

            mg_investment_pipeline_activity($pdo,(int)$request['investor_user_id'],(int)$request['round_id'],'commitment_update','Financial verification approved',$notes,$actorId,['request_id'=>$requestPublicId,'type'=>$type,'amount_cents'=>$amount,'round_totals'=>$totals,'verification_source'=>'maker_checker','pipeline_stage'=>$pipelineStage]);
        }else{
            mg_investment_pipeline_activity($pdo,(int)$request['investor_user_id'],(int)$request['round_id'],'commitment_update','Financial verification rejected',$notes,$actorId,['request_id'=>$requestPublicId,'type'=>$type]);
        }

        $auditPayload=['request_id'=>$requestPublicId,'decision'=>$decision,'reviewer_user_id'=>$actorId,'verification_type'=>$type,'amount_cents'=>$amount,'record_status'=>$newStatus,'round_totals'=>$totals];
        return ['round_public_id'=>(string)$request['round_public_id']];
    });

    mg_audit('investment_financial_verification_decided','investment_financial_verification',$auditPayload,$actorId);
    return mg_investment_closing_dashboard_audited($pdo,['round_id'=>$result['round_public_id']]);
}
