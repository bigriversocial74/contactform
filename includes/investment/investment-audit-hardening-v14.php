<?php
declare(strict_types=1);

function mg_investment_governance_save_meeting_audited_v3(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['summary_status']??'draft')==='published'){
        $publicId=mg_investment_text($input['meeting_id']??'',36,36,'Meeting identifier');
        $q=$pdo->prepare('SELECT status,counsel_status,summary_status FROM investment_board_meetings WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);$current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Board meeting not found.',404);
        if((string)$current['summary_status']!=='approved')throw new MgInvestmentException('Approve the exact funded-investor meeting summary before publishing it.',409);
        mg_investment_audit_require_exact($current,[
            'status'=>(string)($input['status']??'planning'),
            'counsel_status'=>(string)($input['counsel_status']??'not_started'),
        ],'Published meeting status and counsel authority must exactly match the approved summary.');
    }
    return mg_investment_governance_save_meeting_audited_v2($pdo,$actor,$input);
}

function mg_investment_governance_save_notice_audited_v5(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['status']??'draft')==='published'){
        $publicId=mg_investment_text($input['notice_id']??'',36,36,'Notice identifier');
        $q=$pdo->prepare('SELECT status,counsel_status FROM investment_material_notices WHERE public_id=? LIMIT 1');
        $q->execute([$publicId]);$current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Material notice not found.',404);
        if((string)$current['status']!=='approved')throw new MgInvestmentException('Approve the exact material notice before publishing it.',409);
        mg_investment_audit_require_exact($current,[
            'counsel_status'=>(string)($input['counsel_status']??'not_started'),
        ],'Published notice counsel authority must exactly match the approved notice.');
    }
    return mg_investment_governance_save_notice_audited_v4($pdo,$actor,$input);
}

function mg_investment_governance_save_obligation_audited_v3(PDO $pdo,array $actor,array $input): array
{
    if((string)($input['portal_publication_status']??'not_required')==='published'){
        $round=mg_investment_governance_round($pdo,$input['round_id']??'');
        $publicId=mg_investment_text($input['obligation_id']??'',36,36,'Obligation identifier');
        $q=$pdo->prepare('SELECT status,counsel_review_required,portal_publication_status FROM investment_reporting_obligations WHERE public_id=? AND round_id=? LIMIT 1');
        $q->execute([$publicId,(int)$round['id']]);$current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Reporting obligation not found.',404);
        if((string)$current['portal_publication_status']!=='approved')throw new MgInvestmentException('Approve the exact reporting obligation before publishing it.',409);
        mg_investment_audit_require_exact($current,[
            'status'=>(string)($input['status']??'planned'),
            'counsel_review_required'=>mg_investment_bool($input['counsel_review_required']??false)?1:0,
        ],'Published obligation status and counsel-review requirement must exactly match the approved obligation.');
    }
    return mg_investment_governance_save_obligation_audited_v2($pdo,$actor,$input);
}
