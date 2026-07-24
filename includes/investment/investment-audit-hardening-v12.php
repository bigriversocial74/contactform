<?php
declare(strict_types=1);

function mg_investment_save_documents_audited_v2(PDO $pdo,array $actor,array $input): array
{
    $workspace=mg_investment_workspace_by_public_id($pdo,mg_investment_text($input['workspace_id']??'',36,36,'Workspace identifier'));
    foreach(array_slice(is_array($input['items']??null)?$input['items']:[],0,100) as $item){
        if(!is_array($item))continue;
        $publicId=mg_investment_text($item['id']??'',36,36,'Document identifier');
        $q=$pdo->prepare('SELECT * FROM investment_documents WHERE public_id=? AND workspace_id=? LIMIT 1');
        $q->execute([$publicId,(int)$workspace['id']]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Investment document not found.',404);
        $status=(string)($item['status']??'missing');
        if($status!=='published')continue;
        mg_investment_require_permission($actor,'admin.investment.publish');
        $title=mg_investment_text($item['title']??'',180,2,'Document title');
        $url=mg_investment_url($item['external_url']??'',true);
        $visibility=(string)($item['visibility']??'super_admin');
        mg_investment_text($item['change_reason']??'',500,8,'Document change reason');
        if(!in_array((string)$current['status'],['approved','published'],true)){
            throw new MgInvestmentException('Approve the exact investment document version before publishing it.',409);
        }
        if((string)$current['status']==='published'){
            $changed=(string)$current['title']!==$title||(string)($current['external_url']??'')!==(string)$url||(string)$current['visibility']!==$visibility;
            if($changed)throw new MgInvestmentException('A changed published investment document must be saved as an approved version before it can be republished.',409);
        }
    }
    return mg_investment_save_documents_audited($pdo,$actor,$input);
}

function mg_investment_governance_save_tax_document_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $status=(string)($input['status']??'not_started');
    $publicId=trim((string)($input['tax_document_id']??''));
    if($status==='published'){
        mg_investment_require_permission($actor,'admin.investment.governance.publish');
        if($publicId==='')throw new MgInvestmentException('Create and approve the tax-document version before publishing it.',409);
        $round=mg_investment_governance_round($pdo,$input['round_id']??'');
        $q=$pdo->prepare('SELECT td.*,v.external_url,v.external_reference,v.prepared_by,v.status AS version_status FROM investment_tax_documents td LEFT JOIN investment_tax_document_versions v ON v.tax_document_id=td.id AND v.version_number=td.current_version_number WHERE td.public_id=? AND td.round_id=? LIMIT 1');
        $q->execute([mg_investment_text($publicId,36,36,'Tax document identifier'),(int)$round['id']]);
        $current=$q->fetch(PDO::FETCH_ASSOC);
        if(!$current)throw new MgInvestmentException('Tax document not found.',404);
        if(!in_array((string)$current['status'],['approved','published'],true)||!in_array((string)($current['version_status']??''),['approved','published'],true)){
            throw new MgInvestmentException('Approve the exact tax-document version before publishing it.',409);
        }
        $newUrl=mg_investment_url($input['external_url']??'',true);
        $newReference=mg_investment_audit_nullable_text($input['external_reference']??'',220);
        $newPreparedBy=mg_investment_audit_nullable_text($input['prepared_by']??'',180);
        if((string)$current['status']==='published'){
            $changed=(string)($current['external_url']??'')!==(string)$newUrl||(string)($current['external_reference']??'')!==(string)($newReference??'')||(string)($current['prepared_by']??'')!==(string)($newPreparedBy??'');
            if($changed)throw new MgInvestmentException('A changed published tax document must be saved as an approved version before it can be republished.',409);
        }
    }
    return mg_investment_governance_save_tax_document_audited_v2($pdo,$actor,$input);
}
