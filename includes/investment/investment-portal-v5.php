<?php
declare(strict_types=1);

function mg_investment_portal_log_v5(PDO $pdo,int $userId,int $roundId,string $eventType,string $subjectPublicId,array $metadata=[]): void
{
    $subjects=[
        'meeting_summary_view'=>'Investor viewed a published board meeting summary',
        'governance_document_open'=>'Investor opened a published governance document',
        'tax_document_open'=>'Investor opened a published tax or annual document',
        'material_notice_view'=>'Investor viewed a material notice',
        'material_notice_acknowledge'=>'Investor acknowledged a material notice',
    ];
    if(!isset($subjects[$eventType]))return;
    mg_investment_pipeline_activity($pdo,$userId,$roundId,$eventType==='governance_document_open'||$eventType==='tax_document_open'?'document_view':'portal_view',$subjects[$eventType],'',null,$metadata+['subject_public_id'=>$subjectPublicId,'event_type'=>$eventType]);
}

function mg_investment_portal_data_v5(PDO $pdo,array $user): array
{
    $base=mg_investment_portal_data_v4($pdo,$user);$userId=(int)$user['id'];$canGovernance=mg_investment_has_permission($user,'investment.governance.view');$canTax=mg_investment_has_permission($user,'investment.tax_documents.view');
    foreach($base['rounds'] as &$portalRound){
        $q=$pdo->prepare('SELECT id FROM investment_rounds WHERE public_id=? LIMIT 1');$q->execute([(string)$portalRound['id']]);$roundId=(int)$q->fetchColumn();if($roundId<1){$portalRound['governance']=null;continue;}
        $funded=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined")');$funded->execute([$roundId,$userId]);if((int)$funded->fetchColumn()<1||!$canGovernance){$portalRound['governance']=null;continue;}
        $meetingQ=$pdo->prepare('SELECT public_id,meeting_type,title,starts_at,ends_at,location,meeting_url,status,investor_visible_summary,summary_published_at FROM investment_board_meetings WHERE round_id=? AND summary_status="published" AND confidentiality="funded_investors_summary" ORDER BY starts_at DESC');$meetingQ->execute([$roundId]);
        $packetQ=$pdo->prepare('SELECT d.public_id,d.document_type,d.title,d.external_url,d.version_number,d.published_at,m.public_id AS meeting_public_id,m.title AS meeting_title,m.starts_at FROM investment_board_packet_documents d INNER JOIN investment_board_meetings m ON m.id=d.meeting_id WHERE m.round_id=? AND d.status="published" AND d.confidentiality="funded_investors" ORDER BY m.starts_at DESC,d.title');$packetQ->execute([$roundId]);
        $rightsQ=$pdo->prepare('SELECT public_id,right_type,title,description,source_document_reference,source_document_url,cadence,custom_cadence,starts_at,expires_at FROM investment_investor_rights WHERE round_id=? AND investor_user_id=? AND status="active" AND investor_visible=1 AND (expires_at IS NULL OR expires_at>=CURRENT_DATE()) ORDER BY right_type,title');$rightsQ->execute([$roundId,$userId]);
        $obligationQ=$pdo->prepare('SELECT public_id,obligation_type,title,reporting_period,due_at,recipient_scope,portal_publication_status,status,completed_at,completion_reference FROM investment_reporting_obligations WHERE round_id=? AND portal_publication_status="published" AND (investor_user_id IS NULL OR investor_user_id=?) ORDER BY due_at DESC');$obligationQ->execute([$roundId,$userId]);
        $holdingQ=$pdo->prepare('SELECT public_id,instrument_type,verified_funded_cents,closing_batch_reference,agreement_reference,conversion_or_maturity_reference,information_rights_status,tax_document_status,latest_reconciliation_public_id,generated_at FROM investment_holdings_references WHERE round_id=? AND investor_user_id=? LIMIT 1');$holdingQ->execute([$roundId,$userId]);
        $tax=[];if($canTax){$taxQ=$pdo->prepare('SELECT td.public_id,td.document_type,td.reporting_year,td.title,td.external_provider,td.published_at,td.first_viewed_at,td.last_viewed_at,td.view_count,v.public_id AS version_public_id,v.version_number,v.external_url,v.external_reference,v.published_at AS version_published_at FROM investment_tax_documents td INNER JOIN investment_tax_document_versions v ON v.tax_document_id=td.id AND v.version_number=td.current_version_number WHERE td.round_id=? AND td.investor_user_id=? AND td.status="published" AND v.status="published" ORDER BY td.reporting_year DESC,td.title');$taxQ->execute([$roundId,$userId]);$tax=$taxQ->fetchAll(PDO::FETCH_ASSOC);}
        $noticeQ=$pdo->prepare('SELECT n.public_id,n.notice_type,n.title,n.body,n.effective_at,n.published_at,n.expires_at,n.related_document_url,n.related_document_reference,nr.status AS recipient_status,nr.first_viewed_at,nr.last_viewed_at,nr.acknowledged_at,nr.view_count FROM investment_material_notices n INNER JOIN investment_material_notice_recipients nr ON nr.notice_id=n.id WHERE n.round_id=? AND nr.investor_user_id=? AND n.status="published" AND nr.status IN ("published","viewed","acknowledged") AND (n.expires_at IS NULL OR n.expires_at>NOW()) ORDER BY n.published_at DESC');$noticeQ->execute([$roundId,$userId]);
        $consentQ=$pdo->prepare('SELECT public_id,consent_type,title,effective_at,executed_document_reference,executed_document_url FROM investment_written_consents WHERE round_id=? AND status="executed" ORDER BY effective_at DESC,updated_at DESC');$consentQ->execute([$roundId]);
        $portalRound['governance']=[
            'meetings'=>$meetingQ->fetchAll(PDO::FETCH_ASSOC),
            'documents'=>$packetQ->fetchAll(PDO::FETCH_ASSOC),
            'rights'=>$rightsQ->fetchAll(PDO::FETCH_ASSOC),
            'obligations'=>$obligationQ->fetchAll(PDO::FETCH_ASSOC),
            'holding'=>$holdingQ->fetch(PDO::FETCH_ASSOC)?:null,
            'tax_documents'=>$tax,
            'notices'=>$noticeQ->fetchAll(PDO::FETCH_ASSOC),
            'executed_consents'=>$consentQ->fetchAll(PDO::FETCH_ASSOC),
            'disclaimer'=>'Administrative governance and holdings references only; not legal advice or the official stock ledger.',
        ];
    }
    unset($portalRound);return $base;
}

function mg_investment_portal_submit_diligence_v5(PDO $pdo,array $user,array $input): array
{
    mg_investment_portal_submit_diligence_v4($pdo,$user,$input);return mg_investment_portal_data_v5($pdo,$user);
}

function mg_investment_portal_submit_interest_v5(PDO $pdo,array $user,array $input): array
{
    mg_investment_portal_submit_interest_v4($pdo,$user,$input);return mg_investment_portal_data_v5($pdo,$user);
}

function mg_investment_portal_acknowledge_notice_v5(PDO $pdo,array $user,array $input): array
{
    if(!mg_investment_has_permission($user,'investment.governance.view'))throw new MgInvestmentException('Governance portal permission is required.',403);
    $round=mg_investment_governance_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$noticePublicId=mg_investment_text($input['notice_id']??'',36,36,'Notice identifier');$userId=(int)$user['id'];
    $q=$pdo->prepare('SELECT n.id FROM investment_material_notices n INNER JOIN investment_material_notice_recipients nr ON nr.notice_id=n.id WHERE n.public_id=? AND n.round_id=? AND n.status="published" AND nr.investor_user_id=? AND nr.status IN ("published","viewed","acknowledged") LIMIT 1');$q->execute([$noticePublicId,(int)$round['id'],$userId]);$noticeId=(int)$q->fetchColumn();if($noticeId<1)throw new MgInvestmentException('Material notice is not available.',404);
    $pdo->prepare('UPDATE investment_material_notice_recipients SET status="acknowledged",first_viewed_at=COALESCE(first_viewed_at,NOW()),last_viewed_at=NOW(),acknowledged_at=COALESCE(acknowledged_at,NOW()),view_count=view_count+1,updated_at=NOW() WHERE notice_id=? AND investor_user_id=?')->execute([$noticeId,$userId]);
    mg_investment_portal_log_v5($pdo,$userId,(int)$round['id'],'material_notice_acknowledge',$noticePublicId,['acknowledged'=>true]);mg_audit('investment_material_notice_acknowledged','investment_material_notice',['notice_id'=>$noticePublicId,'round_id'=>$round['public_id']],$userId);
    return mg_investment_portal_data_v5($pdo,$user);
}

function mg_investment_portal_event_v5(PDO $pdo,array $user,array $input): array
{
    $event=(string)($input['event_type']??'');$governanceEvents=['meeting_summary_view','governance_document_open','tax_document_open','material_notice_view'];
    if(!in_array($event,$governanceEvents,true))return mg_investment_portal_event_v4($pdo,$user,$input);
    if(!mg_investment_has_permission($user,'investment.governance.view'))throw new MgInvestmentException('Governance portal permission is required.',403);
    $round=mg_investment_governance_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$subjectId=mg_investment_text($input['subject_id']??'',36,36,'Subject identifier');$userId=(int)$user['id'];
    $funded=$pdo->prepare('SELECT COUNT(*) FROM investor_closing_records WHERE round_id=? AND investor_user_id=? AND verified_funded_cents>0');$funded->execute([(int)$round['id'],$userId]);if((int)$funded->fetchColumn()<1)throw new MgInvestmentException('Funded-investor access is required.',403);
    if($event==='meeting_summary_view'){$q=$pdo->prepare('SELECT COUNT(*) FROM investment_board_meetings WHERE public_id=? AND round_id=? AND summary_status="published" AND confidentiality="funded_investors_summary"');$q->execute([$subjectId,(int)$round['id']]);if((int)$q->fetchColumn()<1)throw new MgInvestmentException('Meeting summary is not available.',404);}
    elseif($event==='governance_document_open'){$q=$pdo->prepare('SELECT COUNT(*) FROM investment_board_packet_documents d INNER JOIN investment_board_meetings m ON m.id=d.meeting_id WHERE d.public_id=? AND m.round_id=? AND d.status="published" AND d.confidentiality="funded_investors"');$q->execute([$subjectId,(int)$round['id']]);if((int)$q->fetchColumn()<1)throw new MgInvestmentException('Governance document is not available.',404);}
    elseif($event==='tax_document_open'){if(!mg_investment_has_permission($user,'investment.tax_documents.view'))throw new MgInvestmentException('Tax-document permission is required.',403);$q=$pdo->prepare('SELECT td.id FROM investment_tax_documents td INNER JOIN investment_tax_document_versions v ON v.tax_document_id=td.id AND v.version_number=td.current_version_number WHERE v.public_id=? AND td.round_id=? AND td.investor_user_id=? AND td.status="published" AND v.status="published" LIMIT 1');$q->execute([$subjectId,(int)$round['id'],$userId]);$documentId=(int)$q->fetchColumn();if($documentId<1)throw new MgInvestmentException('Tax document is not available.',404);$pdo->prepare('UPDATE investment_tax_documents SET first_viewed_at=COALESCE(first_viewed_at,NOW()),last_viewed_at=NOW(),view_count=view_count+1,updated_at=NOW() WHERE id=?')->execute([$documentId]);}
    else{$q=$pdo->prepare('SELECT n.id FROM investment_material_notices n INNER JOIN investment_material_notice_recipients nr ON nr.notice_id=n.id WHERE n.public_id=? AND n.round_id=? AND n.status="published" AND nr.investor_user_id=? AND nr.status IN ("published","viewed","acknowledged") LIMIT 1');$q->execute([$subjectId,(int)$round['id'],$userId]);$noticeId=(int)$q->fetchColumn();if($noticeId<1)throw new MgInvestmentException('Material notice is not available.',404);$pdo->prepare('UPDATE investment_material_notice_recipients SET status=IF(status="acknowledged","acknowledged","viewed"),first_viewed_at=COALESCE(first_viewed_at,NOW()),last_viewed_at=NOW(),view_count=view_count+1,updated_at=NOW() WHERE notice_id=? AND investor_user_id=?')->execute([$noticeId,$userId]);}
    mg_investment_portal_log_v5($pdo,$userId,(int)$round['id'],$event,$subjectId,['title'=>mg_investment_text($input['title']??'',220)]);return ['recorded'=>true];
}
