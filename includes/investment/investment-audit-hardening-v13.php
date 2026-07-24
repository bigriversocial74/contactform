<?php
declare(strict_types=1);

function mg_investment_audit_same_value(mixed $left,mixed $right): bool
{
    if($left===null||$left==='')$left='';
    if($right===null||$right==='')$right='';
    return (string)$left===(string)$right;
}

function mg_investment_audit_require_exact(array $current,array $proposed,string $message): void
{
    foreach($proposed as $field=>$value){
        if(!mg_investment_audit_same_value($current[$field]??null,$value)){
            throw new MgInvestmentException($message,409);
        }
    }
}

function mg_investment_dataroom_save_document_audited_v2(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='published'){
        $round=mg_investment_diligence_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));
        $publicId=mg_investment_text($input['document_id']??'',36,36,'Document identifier');
        $q=$pdo->prepare('SELECT * FROM investment_dataroom_documents WHERE public_id=? AND round_id=? LIMIT 1');
        $q->execute([$publicId,(int)$round['id']]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Data-room document not found.',404);
        if((string)$current['status']!=='approved')throw new MgInvestmentException('Approve the exact data-room document version before publishing it.',409);
        $folderId=null;
        if(!empty($input['folder_id'])){
            $folderQ=$pdo->prepare('SELECT id FROM investment_dataroom_folders WHERE public_id=? AND round_id=? LIMIT 1');
            $folderQ->execute([mg_investment_text($input['folder_id'],36,36,'Folder identifier'),(int)$round['id']]);
            $folderId=(int)$folderQ->fetchColumn()?:null;
            if($folderId===null)throw new MgInvestmentException('Data-room folder not found.',404);
        }
        mg_investment_audit_require_exact($current,[
            'folder_id'=>$folderId,
            'title'=>mg_investment_text($input['title']??'',220,2,'Document title'),
            'investor_description'=>mg_investment_audit_nullable_long_text($input['investor_description']??'',4000),
            'external_url'=>mg_investment_url($input['external_url']??'',true),
            'classification'=>(string)($input['classification']??'standard'),
            'visibility'=>(string)($input['visibility']??'approved_investors'),
            'download_allowed'=>mg_investment_bool($input['download_allowed']??false)?1:0,
            'expires_at'=>mg_investment_governance_datetime($input['expires_at']??''),
            'requires_legal_review'=>mg_investment_bool($input['requires_legal_review']??false)?1:0,
        ],'The published request must exactly match the approved data-room document version. Save changes as approved before publishing.');
    }
    return mg_investment_dataroom_save_document_audited($pdo,$actor,$input);
}

function mg_investment_qa_save_audited_v2(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='published'){
        $publicId=mg_investment_text($input['qa_id']??'',36,36,'Q&A identifier');
        $q=$pdo->prepare('SELECT * FROM investment_qa_entries WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Q&A entry not found.',404);
        if((string)$current['status']!=='approved')throw new MgInvestmentException('Approve the exact Q&A entry before publishing it.',409);
        $roundId=null;
        if(!empty($input['round_id']))$roundId=(int)mg_investment_diligence_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'))['id'];
        mg_investment_audit_require_exact($current,[
            'round_id'=>$roundId,
            'category'=>mg_investment_text($input['category']??'general',80,2,'Category'),
            'question'=>mg_investment_text($input['question']??'',500,4,'Question'),
            'answer'=>mg_investment_long_text($input['answer']??'',30000,2,'Answer'),
            'requires_legal_review'=>mg_investment_bool($input['requires_legal_review']??false)?1:0,
        ],'The published request must exactly match the approved Q&A entry. Save changes as approved before publishing.');
    }
    return mg_investment_qa_save_audited($pdo,$actor,$input);
}

function mg_investment_communication_save_audited_v3(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='published'){
        $publicId=mg_investment_text($input['communication_id']??'',36,36,'Communication identifier');
        $q=$pdo->prepare('SELECT * FROM investor_communications WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Communication not found.',404);
        if((string)$current['status']!=='approved')throw new MgInvestmentException('Approve the exact investor communication before publishing it.',409);
        $roundId=null;
        if(!empty($input['round_id']))$roundId=(int)mg_investment_diligence_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'))['id'];
        mg_investment_audit_require_exact($current,[
            'round_id'=>$roundId,
            'communication_type'=>(string)($input['communication_type']??'round_update'),
            'audience_type'=>(string)($input['audience_type']??'approved_investors'),
            'subject'=>mg_investment_text($input['subject']??'',220,2,'Subject'),
            'body'=>mg_investment_long_text($input['body']??'',30000,2,'Communication body'),
            'requires_legal_review'=>mg_investment_bool($input['requires_legal_review']??false)?1:0,
        ],'The published request must exactly match the approved investor communication. Save changes as approved before publishing.');
    }
    return mg_investment_communication_save_audited_v2($pdo,$actor,$input);
}

function mg_investment_governance_save_meeting_audited_v2(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['summary_status']??'draft')==='published'){
        $publicId=mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier');
        $q=$pdo->prepare('SELECT * FROM investment_board_meetings WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Board meeting not found.',404);
        if((string)$current['summary_status']!=='approved')throw new MgInvestmentException('Approve the exact funded-investor meeting summary before publishing it.',409);
        $round=mg_investment_governance_round($pdo,$input['round_id']??'',false);
        mg_investment_audit_require_exact($current,[
            'round_id'=>$round?(int)$round['id']:null,
            'meeting_type'=>(string)($input['meeting_type']??'regular_board'),
            'title'=>mg_investment_text($input['title']??'',220,2,'Meeting title'),
            'starts_at'=>mg_investment_governance_datetime($input['starts_at']??'',true,'Meeting start'),
            'ends_at'=>mg_investment_governance_datetime($input['ends_at']??''),
            'location'=>mg_investment_audit_nullable_text($input['location']??'',300),
            'meeting_url'=>mg_investment_url($input['meeting_url']??''),
            'confidentiality'=>(string)($input['confidentiality']??'board_only'),
            'investor_visible_summary'=>mg_investment_audit_nullable_long_text($input['investor_visible_summary']??'',12000),
        ],'The published request must exactly match the approved meeting summary. Save changes as approved before publishing.');
    }
    return mg_investment_governance_save_meeting_audited($pdo,$actor,$input);
}

function mg_investment_governance_save_packet_document_audited_v2(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='published'){
        $publicId=mg_investment_text($input['document_id']??'',36,36,'Packet document identifier');
        $meeting=mg_investment_governance_meeting($pdo,mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier'));
        $q=$pdo->prepare('SELECT * FROM investment_board_packet_documents WHERE public_id=? AND meeting_id=? LIMIT 1');
        $q->execute([$publicId,(int)$meeting['id']]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Board packet document not found.',404);
        if((string)$current['status']!=='approved')throw new MgInvestmentException('Approve the exact board packet document before publishing it.',409);
        mg_investment_audit_require_exact($current,[
            'document_type'=>(string)($input['document_type']??'other'),
            'title'=>mg_investment_text($input['title']??'',220,2,'Document title'),
            'external_url'=>mg_investment_url($input['external_url']??'',true),
            'external_reference'=>mg_investment_audit_nullable_text($input['external_reference']??'',220),
            'confidentiality'=>(string)($input['confidentiality']??'board'),
            'counsel_status'=>(string)($input['counsel_status']??'not_started'),
        ],'The published request must exactly match the approved board packet document. Save changes as approved before publishing.');
    }
    return mg_investment_governance_save_packet_document_audited($pdo,$actor,$input);
}

function mg_investment_governance_save_consent_audited_v2(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='executed'){
        mg_investment_require_permission($actor,'admin.investment.governance.publish');
        $publicId=mg_investment_text($input['consent_id']??'',36,36,'Consent identifier');
        $q=$pdo->prepare('SELECT * FROM investment_written_consents WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Written consent not found.',404);
        if((string)$current['status']!=='approved_for_execution')throw new MgInvestmentException('Approve the exact written consent for external execution before recording it as executed.',409);
        $round=mg_investment_governance_round($pdo,$input['round_id']??'',false);
        $batchId=null;
        if(!empty($input['batch_id'])){
            $b=$pdo->prepare('SELECT id FROM investment_closing_batches WHERE public_id=? LIMIT 1');
            $b->execute([mg_investment_text($input['batch_id'],36,36,'Batch identifier')]);
            $batchId=(int)$b->fetchColumn()?:null;
        }
        mg_investment_audit_require_exact($current,[
            'round_id'=>$round?(int)$round['id']:null,
            'closing_batch_id'=>$batchId,
            'consent_type'=>(string)($input['consent_type']??'board'),
            'title'=>mg_investment_text($input['title']??'',220,2,'Consent title'),
            'resolution_text'=>mg_investment_long_text($input['resolution_text']??'',50000,20,'Resolution text'),
            'approval_group'=>mg_investment_text($input['approval_group']??'',180,2,'Approval group'),
            'approval_threshold'=>mg_investment_audit_nullable_text($input['approval_threshold']??'',180),
            'response_due_at'=>mg_investment_governance_datetime($input['response_due_at']??''),
            'counsel_status'=>(string)($input['counsel_status']??'not_started'),
        ],'The executed request must exactly match the consent approved for external execution. Create a corrective consent for changed terms.');
    }
    return mg_investment_governance_save_consent_audited($pdo,$actor,$input);
}

function mg_investment_governance_save_notice_audited_v4(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='published'){
        $publicId=mg_investment_text($input['notice_id']??'',36,36,'Notice identifier');
        $q=$pdo->prepare('SELECT * FROM investment_material_notices WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Material notice not found.',404);
        if((string)$current['status']!=='approved')throw new MgInvestmentException('Approve the exact material notice before publishing it.',409);
        mg_investment_audit_require_exact($current,mg_investment_audit_notice_public_fields($pdo,$input),'The published request must exactly match the approved material notice. Save changes as approved before publishing.');
    }
    return mg_investment_governance_save_notice_audited_v3($pdo,$actor,$input);
}

function mg_investment_governance_save_right_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $status=(string)($input['status']??'draft');
    $visible=mg_investment_bool($input['investor_visible']??false);
    if($status==='active'||$visible){
        mg_investment_require_permission($actor,'admin.investment.governance.publish');
        $publicId=mg_investment_text($input['right_id']??'',36,36,'Right identifier');
        $q=$pdo->prepare('SELECT * FROM investment_investor_rights WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Create and counsel-approve the investor right before activation or publication.',409);
        if(!in_array((string)$current['counsel_status'],['approved','not_applicable'],true))throw new MgInvestmentException('Counsel must approve the exact investor-right terms before activation or publication.',409);
        $round=mg_investment_governance_round($pdo,$input['round_id']??'');
        mg_investment_audit_require_exact($current,[
            'round_id'=>(int)$round['id'],
            'investor_user_id'=>(int)($input['investor_user_id']??0),
            'right_type'=>(string)($input['right_type']??'information'),
            'title'=>mg_investment_text($input['title']??'',220,2,'Right title'),
            'description'=>mg_investment_audit_nullable_long_text($input['description']??'',10000),
            'source_document_reference'=>mg_investment_text($input['source_document_reference']??'',220,2,'Source document reference'),
            'source_document_url'=>mg_investment_url($input['source_document_url']??''),
            'cadence'=>(string)($input['cadence']??'none'),
            'custom_cadence'=>mg_investment_audit_nullable_text($input['custom_cadence']??'',180),
            'starts_at'=>mg_investment_date($input['starts_at']??null),
            'expires_at'=>mg_investment_date($input['expires_at']??null),
            'counsel_status'=>(string)($input['counsel_status']??'not_started'),
        ],'Active or investor-visible rights must exactly match the counsel-approved terms. Save changes for counsel review before activation.');
    }
    return mg_investment_governance_save_right_audited_v2($pdo,$actor,$input);
}

function mg_investment_governance_save_obligation_audited_v2(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['portal_publication_status']??'not_required')==='published'){
        mg_investment_require_permission($actor,'admin.investment.governance.publish');
        $round=mg_investment_governance_round($pdo,$input['round_id']??'');
        $publicId=mg_investment_text($input['obligation_id']??'',36,36,'Obligation identifier');
        $q=$pdo->prepare('SELECT * FROM investment_reporting_obligations WHERE public_id=? AND round_id=? LIMIT 1');
        $q->execute([$publicId,(int)$round['id']]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Reporting obligation not found.',404);
        if(!in_array((string)$current['portal_publication_status'],['ready','published'],true))throw new MgInvestmentException('Mark the exact reporting obligation ready before publishing it.',409);
        mg_investment_audit_require_exact($current,[
            'investor_user_id'=>(int)($input['investor_user_id']??0)?:null,
            'obligation_type'=>(string)($input['obligation_type']??'quarterly_report'),
            'title'=>mg_investment_text($input['title']??'',220,2,'Obligation title'),
            'reporting_period'=>mg_investment_audit_nullable_text($input['reporting_period']??'',120),
            'due_at'=>mg_investment_governance_datetime($input['due_at']??'',true,'Obligation due date'),
            'recipient_scope'=>(string)($input['recipient_scope']??'funded_investors'),
            'completion_reference'=>mg_investment_audit_nullable_text($input['completion_reference']??'',220),
        ],'The published request must exactly match the ready reporting obligation. Save changes as ready before publishing.');
    }
    return mg_investment_governance_save_obligation_audited($pdo,$actor,$input);
}
