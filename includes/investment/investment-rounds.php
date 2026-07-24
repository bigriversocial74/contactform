<?php
declare(strict_types=1);
function mg_investment_adopt_round(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $scenarioPublicId=mg_investment_text($input['scenario_id'] ?? '',36,36,'Scenario identifier');
    $actorId=(int)$actor['id'];
    $pdo->beginTransaction();
    try {
        $scenario=mg_investment_scenario_by_public_id($pdo,$scenarioPublicId,true);
        $budget=$pdo->prepare('SELECT category,description,amount_cents,priority,investor_visible,sort_order FROM investment_scenario_budgets WHERE scenario_id=? ORDER BY sort_order,id');
        $budget->execute([(int)$scenario['id']]);
        $goals=$pdo->prepare('SELECT title,rationale,metric_key,baseline_value,target_value,unit,budget_cents,target_date,status,investor_visible,public_description,sort_order FROM investment_scenario_goals WHERE scenario_id=? ORDER BY sort_order,id');
        $goals->execute([(int)$scenario['id']]);
        $snapshot=['workspace'=>['company'=>mg_investment_json($scenario['company_json']),'capitalization'=>mg_investment_json($scenario['capitalization_json']),'operating_plan'=>mg_investment_json($scenario['operating_plan_json']),'assumptions'=>mg_investment_json($scenario['workspace_assumptions_json'])],'scenario'=>array_diff_key($scenario,array_flip(['company_json','capitalization_json','operating_plan_json','workspace_assumptions_json'])),'calculations'=>mg_investment_json($scenario['calculations_json']),'projection'=>mg_investment_json($scenario['projection_json']),'budgets'=>$budget->fetchAll(PDO::FETCH_ASSOC),'goals'=>$goals->fetchAll(PDO::FETCH_ASSOC),'adopted_at'=>date(DATE_ATOM)];
        $publicId=mg_investment_uuid();
        $internal=mg_investment_text($input['internal_name'] ?? ($scenario['name'].' Round'),180,3,'Internal round name');
        $public=mg_investment_text($input['public_name'] ?? 'Microgifter Pre-Seed Round',180,3,'Public round name');
        $stmt=$pdo->prepare('INSERT INTO investment_rounds (public_id,workspace_id,adopted_scenario_id,internal_name,public_name,status,visibility,instrument_type,minimum_raise_cents,target_raise_cents,maximum_raise_cents,valuation_cap_cents,discount_bps,minimum_investment_cents,snapshot_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?, ?,?,"planning","super_admin",?,?,?,?,?,?,?, ?,?,?,NOW(),NOW())');
        $stmt->execute([$publicId,(int)$scenario['workspace_id'],(int)$scenario['id'],$internal,$public,$scenario['instrument_type'],$scenario['minimum_raise_cents'],$scenario['target_raise_cents'],$scenario['maximum_raise_cents'],$scenario['valuation_cap_cents'],$scenario['discount_bps'],$scenario['minimum_investment_cents'],mg_investment_json_encode($snapshot),$actorId,$actorId]);
        $roundId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO investment_round_versions (round_id,version_number,snapshot_json,change_reason,created_by_user_id,created_at) VALUES (?,1,?,"Adopted from approved planning scenario",?,NOW())')->execute([$roundId,mg_investment_json_encode($snapshot),$actorId]);
        $pdo->commit();
        mg_audit('investment_round_adopted','investment_round',['round_id'=>$publicId,'scenario_id'=>$scenario['public_id']],$actorId);
        return mg_investment_workspace_detail($pdo,(string)$scenario['workspace_public_id']);
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}

function mg_investment_update_round(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $publicId=mg_investment_text($input['round_id'] ?? '',36,36,'Round identifier');
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM investment_rounds WHERE public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId]);
        $round=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$round)throw new MgInvestmentException('Investment round not found.',404);
        $status=(string)($input['status'] ?? $round['status']);
        $visibility=(string)($input['visibility'] ?? $round['visibility']);
        if(!in_array($status,['planning','awaiting_counsel','private_preview','open','minimum_reached','closing','closed','paused','cancelled'],true)||!in_array($visibility,['super_admin','approved_investors','selected_investors','funded_investors','public_summary'],true))throw new MgInvestmentException('Invalid round status or visibility.');
        $reason=mg_investment_text($input['change_reason'] ?? '',500,8,'Change reason');
        $counsel=(string)($input['counsel_status'] ?? $round['counsel_status']);
        if(!in_array($counsel,['not_started','drafting','under_review','approved'],true))throw new MgInvestmentException('Invalid counsel status.');
        if(in_array($status,['open','minimum_reached','closing'],true)&&$counsel!=='approved')throw new MgInvestmentException('The round cannot be opened or advanced until counsel status is approved.',409);
        $fields=['internal_name'=>mg_investment_text($input['internal_name'] ?? $round['internal_name'],180,3),'public_name'=>mg_investment_text($input['public_name'] ?? $round['public_name'],180,3),'status'=>$status,'visibility'=>$visibility,'soft_commitment_cents'=>mg_investment_money($input['soft_commitment'] ?? ((int)$round['soft_commitment_cents']/100)),'signed_cents'=>mg_investment_money($input['signed'] ?? ((int)$round['signed_cents']/100)),'funded_cents'=>mg_investment_money($input['funded'] ?? ((int)$round['funded_cents']/100)),'opens_at'=>mg_investment_date($input['opens_at'] ?? $round['opens_at']),'target_close_at'=>mg_investment_date($input['target_close_at'] ?? $round['target_close_at']),'final_close_at'=>mg_investment_date($input['final_close_at'] ?? $round['final_close_at']),'counsel_status'=>$counsel,'offering_exemption'=>mg_investment_text($input['offering_exemption'] ?? $round['offering_exemption'],120) ?: null,'general_solicitation'=>mg_investment_bool($input['general_solicitation'] ?? $round['general_solicitation'])?1:0,'accredited_investors_required'=>array_key_exists('accredited_investors_required',$input)?(mg_investment_bool($input['accredited_investors_required'])?1:0):$round['accredited_investors_required'],'first_sale_at'=>mg_investment_date($input['first_sale_at'] ?? $round['first_sale_at']),'form_d_status'=>mg_investment_text($input['form_d_status'] ?? $round['form_d_status'],80) ?: null];
        if($fields['signed_cents']<$fields['funded_cents'])throw new MgInvestmentException('Funded amount cannot exceed signed amount.');
        $update=$pdo->prepare('UPDATE investment_rounds SET internal_name=?,public_name=?,status=?,visibility=?,soft_commitment_cents=?,signed_cents=?,funded_cents=?,opens_at=?,target_close_at=?,final_close_at=?,counsel_status=?,offering_exemption=?,general_solicitation=?,accredited_investors_required=?,first_sale_at=?,form_d_status=?,published_at=IF(? IN ("private_preview","open","minimum_reached","closing","closed"),COALESCE(published_at,NOW()),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?');
        $update->execute([$fields['internal_name'],$fields['public_name'],$fields['status'],$fields['visibility'],$fields['soft_commitment_cents'],$fields['signed_cents'],$fields['funded_cents'],$fields['opens_at'],$fields['target_close_at'],$fields['final_close_at'],$fields['counsel_status'],$fields['offering_exemption'],$fields['general_solicitation'],$fields['accredited_investors_required'],$fields['first_sale_at'],$fields['form_d_status'],$fields['status'],(int)$actor['id'],(int)$round['id']]);
        $fresh=$pdo->prepare('SELECT * FROM investment_rounds WHERE id=?');
        $fresh->execute([(int)$round['id']]);
        $versionSnapshot=$fresh->fetch(PDO::FETCH_ASSOC);
        $next=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM investment_round_versions WHERE round_id=?');
        $next->execute([(int)$round['id']]);
        $pdo->prepare('INSERT INTO investment_round_versions (round_id,version_number,snapshot_json,change_reason,created_by_user_id,created_at) VALUES (?,?,?,?,?,NOW())')->execute([(int)$round['id'],(int)$next->fetchColumn(),mg_investment_json_encode($versionSnapshot ?: []),$reason,(int)$actor['id']]);
        $workspaceLookup=$pdo->prepare('SELECT public_id FROM investment_workspaces WHERE id=? LIMIT 1');
        $workspaceLookup->execute([(int)$round['workspace_id']]);
        $workspacePublicId=(string)$workspaceLookup->fetchColumn();
        $pdo->commit();
        mg_audit('investment_round_updated','investment_round',['round_id'=>$publicId,'reason'=>$reason],(int)$actor['id']);
        return mg_investment_workspace_detail($pdo,$workspacePublicId);
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}
