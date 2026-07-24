<?php
declare(strict_types=1);
function mg_investment_replace_budget(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');$scenario=mg_investment_scenario_by_public_id($pdo,mg_investment_text($input['scenario_id'] ?? '',36,36,'Scenario identifier'));$items=is_array($input['items'] ?? null)?$input['items']:[];
    $pdo->beginTransaction();try{$pdo->prepare('DELETE FROM investment_scenario_budgets WHERE scenario_id=?')->execute([(int)$scenario['id']]);$stmt=$pdo->prepare('INSERT INTO investment_scenario_budgets (scenario_id,category,description,amount_cents,priority,investor_visible,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())');foreach(array_slice($items,0,100) as $index=>$item){if(!is_array($item))continue;$category=mg_investment_text($item['category'] ?? '',100,1,'Budget category');$priority=in_array($item['priority'] ?? 'normal',['critical','high','normal','optional'],true)?$item['priority']:'normal';$stmt->execute([(int)$scenario['id'],$category,mg_investment_text($item['description'] ?? '',500) ?: null,mg_investment_money($item['amount'] ?? 0),$priority,mg_investment_bool($item['investor_visible'] ?? true)?1:0,$index]);}$pdo->commit();mg_audit('investment_budget_saved','investment_scenario',['scenario_id'=>$scenario['public_id']],(int)$actor['id']);return mg_investment_workspace_detail($pdo,(string)$scenario['workspace_public_id']);}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_investment_replace_goals(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');$scenario=mg_investment_scenario_by_public_id($pdo,mg_investment_text($input['scenario_id'] ?? '',36,36,'Scenario identifier'));$items=is_array($input['items'] ?? null)?$input['items']:[];
    $pdo->beginTransaction();try{$pdo->prepare('DELETE FROM investment_scenario_goals WHERE scenario_id=?')->execute([(int)$scenario['id']]);$stmt=$pdo->prepare('INSERT INTO investment_scenario_goals (public_id,scenario_id,title,rationale,metric_key,baseline_value,target_value,unit,budget_cents,target_date,status,investor_visible,public_description,internal_notes,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');foreach(array_slice($items,0,100) as $index=>$item){if(!is_array($item))continue;$status=in_array($item['status'] ?? 'planned',['planned','active','at_risk','achieved','missed','cancelled'],true)?$item['status']:'planned';$stmt->execute([mg_investment_uuid(),(int)$scenario['id'],mg_investment_text($item['title'] ?? '',180,2,'Goal title'),mg_investment_long_text($item['rationale'] ?? '',4000) ?: null,mg_investment_text($item['metric_key'] ?? '',120) ?: null,is_numeric($item['baseline_value'] ?? null)?$item['baseline_value']:null,is_numeric($item['target_value'] ?? null)?$item['target_value']:null,mg_investment_text($item['unit'] ?? '',40) ?: null,mg_investment_money($item['budget'] ?? 0),mg_investment_date($item['target_date'] ?? null)?date('Y-m-d',strtotime((string)$item['target_date'])):null,$status,mg_investment_bool($item['investor_visible'] ?? true)?1:0,mg_investment_long_text($item['public_description'] ?? '',6000) ?: null,mg_investment_long_text($item['internal_notes'] ?? '',6000) ?: null,$index]);}$pdo->commit();mg_audit('investment_goals_saved','investment_scenario',['scenario_id'=>$scenario['public_id']],(int)$actor['id']);return mg_investment_workspace_detail($pdo,(string)$scenario['workspace_public_id']);}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_investment_save_metrics(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $workspace=mg_investment_workspace_by_public_id($pdo,mg_investment_text($input['workspace_id'] ?? '',36,36,'Workspace identifier'));
    $items=is_array($input['items'] ?? null)?$input['items']:[];
    $stmt=$pdo->prepare('INSERT INTO investment_metrics (public_id,workspace_id,metric_key,name,description,source_system,calculation_method,unit,value_type,confidence,current_value,investor_visible,refresh_frequency,last_calculated_at,last_verified_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,IF(? IS NULL,NULL,NOW()),IF(? IS NULL,NULL,NOW()),NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),source_system=VALUES(source_system),calculation_method=VALUES(calculation_method),unit=VALUES(unit),value_type=VALUES(value_type),confidence=VALUES(confidence),current_value=VALUES(current_value),investor_visible=VALUES(investor_visible),refresh_frequency=VALUES(refresh_frequency),last_calculated_at=VALUES(last_calculated_at),last_verified_at=VALUES(last_verified_at),updated_at=NOW()');
    $pdo->beginTransaction();
    try {
        foreach(array_slice($items,0,150) as $item){
            if(!is_array($item))continue;
            $type=in_array($item['value_type'] ?? 'manual',['actual','projected','manual'],true)?$item['value_type']:'manual';
            $confidence=in_array($item['confidence'] ?? 'unavailable',['verified','system_calculated','admin_confirmed','estimated','projected','unavailable'],true)?$item['confidence']:'unavailable';
            $value=is_numeric($item['current_value'] ?? null)?$item['current_value']:null;
            if(mg_investment_bool($item['investor_visible'] ?? false)&&$type==='actual'&&!in_array($confidence,['verified','system_calculated','admin_confirmed'],true))throw new MgInvestmentException('Investor-visible actual metrics require verified, system-calculated, or admin-confirmed confidence.');
            $stmt->execute([mg_investment_uuid(),(int)$workspace['id'],mg_investment_text($item['metric_key'] ?? '',120,2,'Metric key'),mg_investment_text($item['name'] ?? '',180,2,'Metric name'),mg_investment_long_text($item['description'] ?? '',4000) ?: null,mg_investment_text($item['source_system'] ?? '',120) ?: null,mg_investment_long_text($item['calculation_method'] ?? '',4000) ?: null,mg_investment_text($item['unit'] ?? '',40) ?: null,$type,$confidence,$value,mg_investment_bool($item['investor_visible'] ?? false)?1:0,mg_investment_text($item['refresh_frequency'] ?? '',40) ?: null,$value,$confidence==='verified'?$value:null]);
        }
        $pdo->commit();
        mg_audit('investment_metrics_saved','investment_workspace',['workspace_id'=>$workspace['public_id']],(int)$actor['id']);
        return mg_investment_workspace_detail($pdo,(string)$workspace['public_id']);
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}

function mg_investment_save_documents(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $workspace=mg_investment_workspace_by_public_id($pdo,mg_investment_text($input['workspace_id'] ?? '',36,36,'Workspace identifier'));
    $items=is_array($input['items'] ?? null)?$input['items']:[];
    $allowedStatus=['missing','draft','internal_review','counsel_review','approved','published','superseded','archived'];
    $allowedVisibility=['super_admin','approved_investors','selected_investors','funded_investors','public_summary'];
    $stmt=$pdo->prepare('UPDATE investment_documents SET title=?,status=?,external_url=?,visibility=?,notes=?,updated_at=NOW() WHERE public_id=? AND workspace_id=?');
    $pdo->beginTransaction();
    try {
        foreach(array_slice($items,0,100) as $item){
            if(!is_array($item))continue;
            $status=(string)($item['status'] ?? 'missing');
            $visibility=(string)($item['visibility'] ?? 'super_admin');
            if(!in_array($status,$allowedStatus,true)||!in_array($visibility,$allowedVisibility,true))throw new MgInvestmentException('Invalid document status or visibility.');
            $url=mg_investment_url($item['external_url'] ?? '');
            if($status==='published'&&$url===null)throw new MgInvestmentException('Published documents require an approved external URL.');
            $stmt->execute([mg_investment_text($item['title'] ?? '',180,2,'Document title'),$status,$url,$visibility,mg_investment_long_text($item['notes'] ?? '',4000) ?: null,mg_investment_text($item['id'] ?? '',36,36,'Document identifier'),(int)$workspace['id']]);
        }
        $pdo->commit();
        mg_audit('investment_documents_saved','investment_workspace',['workspace_id'=>$workspace['public_id']],(int)$actor['id']);
        return mg_investment_workspace_detail($pdo,(string)$workspace['public_id']);
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}
