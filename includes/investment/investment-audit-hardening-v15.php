<?php
declare(strict_types=1);

function mg_investment_with_entity_lock_audited(PDO $pdo,string $scope,string $identifier,callable $operation): array
{
    $identifier=trim($identifier);
    if($identifier==='')return $operation();
    $lockName='mg_inv_'.substr(hash('sha256',$scope.':'.$identifier),0,48);
    $q=$pdo->prepare('SELECT GET_LOCK(?,5)');
    $q->execute([$lockName]);
    if((int)$q->fetchColumn()!==1)throw new MgInvestmentException('This investor record is being changed by another administrator. Try again.',409);
    try{return $operation();}
    finally{
        try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}catch(Throwable){}
    }
}

function mg_investment_save_documents_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $workspaceId=mg_investment_text($input['workspace_id']??'',36,36,'Workspace identifier');
    return mg_investment_with_entity_lock_audited($pdo,'official_documents',$workspaceId,static fn():array=>mg_investment_save_documents_audited_v2($pdo,$actor,$input));
}

function mg_investment_dataroom_save_document_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['document_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'dataroom_document',$id,static fn():array=>mg_investment_dataroom_save_document_audited_v2($pdo,$actor,$input));
}

function mg_investment_qa_save_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['qa_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'qa',$id,static fn():array=>mg_investment_qa_save_audited_v2($pdo,$actor,$input));
}

function mg_investment_communication_save_audited_v4(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['communication_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'communication',$id,static fn():array=>mg_investment_communication_save_audited_v3($pdo,$actor,$input));
}

function mg_investment_governance_save_meeting_audited_v4(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['meeting_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'board_meeting',$id,static fn():array=>mg_investment_governance_save_meeting_audited_v3($pdo,$actor,$input));
}

function mg_investment_governance_save_packet_document_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['document_id']??''));
    $fallback=trim((string)($input['meeting_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'board_packet',$id!==''?$id:$fallback,static fn():array=>mg_investment_governance_save_packet_document_audited_v2($pdo,$actor,$input));
}

function mg_investment_governance_save_consent_audited_v3(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['consent_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'written_consent',$id,static fn():array=>mg_investment_governance_save_consent_audited_v2($pdo,$actor,$input));
}

function mg_investment_governance_save_notice_audited_v6(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['notice_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'material_notice',$id,static fn():array=>mg_investment_governance_save_notice_audited_v5($pdo,$actor,$input));
}

function mg_investment_governance_save_right_audited_v4(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['right_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'investor_right',$id,static fn():array=>mg_investment_governance_save_right_audited_v3($pdo,$actor,$input));
}

function mg_investment_governance_save_obligation_audited_v4(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['obligation_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'reporting_obligation',$id,static fn():array=>mg_investment_governance_save_obligation_audited_v3($pdo,$actor,$input));
}

function mg_investment_governance_save_tax_document_audited_v4(PDO $pdo,array $actor,array $input): array
{
    $id=trim((string)($input['tax_document_id']??''));
    return mg_investment_with_entity_lock_audited($pdo,'tax_document',$id,static fn():array=>mg_investment_governance_save_tax_document_audited_v3($pdo,$actor,$input));
}
