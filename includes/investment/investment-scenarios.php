<?php
declare(strict_types=1);
function mg_investment_save_workspace(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $publicId=mg_investment_text($input['workspace_id'] ?? '',36,36,'Workspace identifier');$workspace=mg_investment_workspace_by_public_id($pdo,$publicId,true);
    $name=mg_investment_text($input['name'] ?? $workspace['name'],180,3,'Workspace name');$status=strtolower(mg_investment_text($input['status'] ?? $workspace['status'],40,1,'Status'));
    if(!in_array($status,['draft','under_review','ready_for_counsel','private_preview','archived'],true))throw new MgInvestmentException('Invalid workspace status.');
    $step=mg_investment_text($input['active_step'] ?? $workspace['active_step'],60,1,'Active step');
    $company=is_array($input['company'] ?? null)?$input['company']:mg_investment_json($workspace['company_json']);
    $capitalization=is_array($input['capitalization'] ?? null)?$input['capitalization']:mg_investment_json($workspace['capitalization_json']);
    $operating=is_array($input['operating_plan'] ?? null)?$input['operating_plan']:mg_investment_json($workspace['operating_plan_json']);
    $assumptions=is_array($input['assumptions'] ?? null)?array_replace(mg_investment_default_assumptions(),$input['assumptions']):mg_investment_json($workspace['assumptions_json'],mg_investment_default_assumptions());
    $stmt=$pdo->prepare('UPDATE investment_workspaces SET name=?,status=?,active_step=?,company_json=?,capitalization_json=?,operating_plan_json=?,assumptions_json=?,notes=?,last_saved_by_user_id=?,updated_at=NOW() WHERE id=?');
    $stmt->execute([$name,$status,$step,mg_investment_json_encode($company),mg_investment_json_encode($capitalization),mg_investment_json_encode($operating),mg_investment_json_encode($assumptions),mg_investment_long_text($input['notes'] ?? $workspace['notes'],12000),(int)$actor['id'],(int)$workspace['id']]);
    mg_audit('investment_workspace_saved','investment_workspace',['workspace_id'=>$publicId,'active_step'=>$step],(int)$actor['id']);
    return mg_investment_workspace_detail($pdo,$publicId);
}

function mg_investment_save_scenario(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $publicId=mg_investment_text($input['scenario_id'] ?? '',36,36,'Scenario identifier');
    $pdo->beginTransaction();
    try {
        $scenario=mg_investment_scenario_by_public_id($pdo,$publicId,true);
        $status=strtolower(mg_investment_text($input['status'] ?? $scenario['status'],40,1,'Status'));
        if(!in_array($status,['draft','under_review','preferred','illustrative','approved','archived'],true))throw new MgInvestmentException('Invalid scenario status.');
        $instrument=strtolower(mg_investment_text($input['instrument_type'] ?? $scenario['instrument_type'],60,1,'Instrument'));
        if(!in_array($instrument,['not_finalized','post_money_safe','convertible_note','priced_equity'],true))throw new MgInvestmentException('Invalid instrument.');
        $data=[
          'name'=>mg_investment_text($input['name'] ?? $scenario['name'],180,3,'Scenario name'),'status'=>$status,'instrument_type'=>$instrument,
          'minimum_raise_cents'=>mg_investment_money($input['minimum_raise'] ?? ((int)$scenario['minimum_raise_cents']/100)),
          'target_raise_cents'=>mg_investment_money($input['target_raise'] ?? ((int)$scenario['target_raise_cents']/100)),
          'maximum_raise_cents'=>mg_investment_money($input['maximum_raise'] ?? ((int)$scenario['maximum_raise_cents']/100)),
          'valuation_cap_cents'=>mg_investment_money($input['valuation_cap'] ?? ((int)$scenario['valuation_cap_cents']/100)),
          'pre_money_valuation_cents'=>mg_investment_money($input['pre_money_valuation'] ?? ((int)$scenario['pre_money_valuation_cents']/100)),
          'discount_bps'=>mg_investment_bps($input['discount_percent'] ?? ((int)$scenario['discount_bps']/100)),
          'target_dilution_bps'=>mg_investment_bps($input['target_dilution_percent'] ?? ((int)$scenario['target_dilution_bps']/100)),
          'maximum_dilution_bps'=>mg_investment_bps($input['maximum_dilution_percent'] ?? ((int)$scenario['maximum_dilution_bps']/100)),
          'minimum_investment_cents'=>mg_investment_money($input['minimum_investment'] ?? ((int)$scenario['minimum_investment_cents']/100)),
          'desired_runway_months'=>max(1,min(60,(int)($input['desired_runway_months'] ?? $scenario['desired_runway_months']))),
          'option_pool_bps'=>mg_investment_bps($input['option_pool_percent'] ?? ((int)$scenario['option_pool_bps']/100)),
          'existing_safe_cents'=>mg_investment_money($input['existing_safe'] ?? ((int)$scenario['existing_safe_cents']/100)),
          'forecast_months'=>in_array((int)($input['forecast_months'] ?? $scenario['forecast_months']),[24,36],true)?(int)($input['forecast_months'] ?? $scenario['forecast_months']):24,
          'forecast_case'=>in_array((string)($input['forecast_case'] ?? $scenario['forecast_case']),['conservative','expected','upside'],true)?(string)($input['forecast_case'] ?? $scenario['forecast_case']):'expected',
          'assumptions'=>is_array($input['assumptions'] ?? null)?$input['assumptions']:mg_investment_json($scenario['assumptions_json']),
          'stress_tests'=>is_array($input['stress_tests'] ?? null)?$input['stress_tests']:mg_investment_json($scenario['stress_tests_json']),
          'narrative'=>mg_investment_long_text($input['narrative'] ?? $scenario['narrative'],12000),
          'internal_notes'=>mg_investment_long_text($input['internal_notes'] ?? $scenario['internal_notes'],12000),
        ];
        if($data['minimum_raise_cents']>$data['target_raise_cents'])throw new MgInvestmentException('Minimum raise cannot exceed target raise.');
        if($data['maximum_raise_cents']>0&&$data['maximum_raise_cents']<$data['target_raise_cents'])throw new MgInvestmentException('Maximum raise cannot be below target raise.');
        $workspace=['operating_plan_json'=>$scenario['operating_plan_json'],'assumptions_json'=>$scenario['workspace_assumptions_json']];
        $calcInput=$scenario+$data+['assumptions_json'=>mg_investment_json_encode($data['assumptions']),'stress_tests_json'=>mg_investment_json_encode($data['stress_tests'])];
        $calculations=mg_investment_scenario_calculate($workspace,$calcInput);
        $projection=mg_investment_scenario_projection($workspace,$calcInput,$calculations);
        $stmt=$pdo->prepare('UPDATE investment_scenarios SET name=?,status=?,instrument_type=?,minimum_raise_cents=?,target_raise_cents=?,maximum_raise_cents=?,valuation_cap_cents=?,pre_money_valuation_cents=?,discount_bps=?,target_dilution_bps=?,maximum_dilution_bps=?,minimum_investment_cents=?,desired_runway_months=?,option_pool_bps=?,existing_safe_cents=?,forecast_months=?,forecast_case=?,assumptions_json=?,stress_tests_json=?,calculations_json=?,projection_json=?,narrative=?,internal_notes=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?');
        $stmt->execute([$data['name'],$data['status'],$data['instrument_type'],$data['minimum_raise_cents'],$data['target_raise_cents'],$data['maximum_raise_cents'],$data['valuation_cap_cents'],$data['pre_money_valuation_cents'],$data['discount_bps'],$data['target_dilution_bps'],$data['maximum_dilution_bps'],$data['minimum_investment_cents'],$data['desired_runway_months'],$data['option_pool_bps'],$data['existing_safe_cents'],$data['forecast_months'],$data['forecast_case'],mg_investment_json_encode($data['assumptions']),mg_investment_json_encode($data['stress_tests']),mg_investment_json_encode($calculations),mg_investment_json_encode($projection),$data['narrative'] ?: null,$data['internal_notes'] ?: null,(int)$actor['id'],(int)$scenario['id']]);
        if($status==='preferred'){
          $pdo->prepare('UPDATE investment_scenarios SET status=IF(id=?,"preferred",IF(status="preferred","draft",status)) WHERE workspace_id=?')->execute([(int)$scenario['id'],(int)$scenario['workspace_id']]);
          $pdo->prepare('UPDATE investment_workspaces SET preferred_scenario_id=?,updated_at=NOW() WHERE id=?')->execute([(int)$scenario['id'],(int)$scenario['workspace_id']]);
        }
        $pdo->commit();
        mg_audit('investment_scenario_saved','investment_scenario',['scenario_id'=>$publicId],(int)$actor['id']);
        return mg_investment_workspace_detail($pdo,(string)$scenario['workspace_public_id']);
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}

function mg_investment_clone_scenario(PDO $pdo,array $actor,string $publicId): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');$scenario=mg_investment_scenario_by_public_id($pdo,$publicId);$actorId=(int)$actor['id'];
    $pdo->beginTransaction();try{
      $newPublic=mg_investment_uuid();$stmt=$pdo->prepare('INSERT INTO investment_scenarios (public_id,workspace_id,name,status,instrument_type,minimum_raise_cents,target_raise_cents,maximum_raise_cents,valuation_cap_cents,pre_money_valuation_cents,discount_bps,target_dilution_bps,maximum_dilution_bps,minimum_investment_cents,desired_runway_months,option_pool_bps,existing_safe_cents,forecast_months,forecast_case,assumptions_json,stress_tests_json,calculations_json,projection_json,narrative,internal_notes,created_by_user_id,updated_by_user_id,created_at,updated_at) SELECT ?,workspace_id,CONCAT(name," — Copy"),"draft",instrument_type,minimum_raise_cents,target_raise_cents,maximum_raise_cents,valuation_cap_cents,pre_money_valuation_cents,discount_bps,target_dilution_bps,maximum_dilution_bps,minimum_investment_cents,desired_runway_months,option_pool_bps,existing_safe_cents,forecast_months,forecast_case,assumptions_json,stress_tests_json,calculations_json,projection_json,narrative,internal_notes,?,?,NOW(),NOW() FROM investment_scenarios WHERE id=?');$stmt->execute([$newPublic,$actorId,$actorId,(int)$scenario['id']]);$newId=(int)$pdo->lastInsertId();
      $pdo->prepare('INSERT INTO investment_scenario_budgets (scenario_id,category,description,amount_cents,priority,investor_visible,sort_order,created_at,updated_at) SELECT ?,category,description,amount_cents,priority,investor_visible,sort_order,NOW(),NOW() FROM investment_scenario_budgets WHERE scenario_id=?')->execute([$newId,(int)$scenario['id']]);
      $goals=$pdo->prepare('SELECT * FROM investment_scenario_goals WHERE scenario_id=? ORDER BY id');$goals->execute([(int)$scenario['id']]);$insert=$pdo->prepare('INSERT INTO investment_scenario_goals (public_id,scenario_id,title,rationale,metric_key,baseline_value,target_value,unit,budget_cents,target_date,status,investor_visible,public_description,internal_notes,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');foreach($goals->fetchAll(PDO::FETCH_ASSOC) as $goal)$insert->execute([mg_investment_uuid(),$newId,$goal['title'],$goal['rationale'],$goal['metric_key'],$goal['baseline_value'],$goal['target_value'],$goal['unit'],$goal['budget_cents'],$goal['target_date'],$goal['status'],$goal['investor_visible'],$goal['public_description'],$goal['internal_notes'],$goal['sort_order']]);
      $pdo->commit();mg_audit('investment_scenario_cloned','investment_scenario',['source'=>$publicId,'clone'=>$newPublic],$actorId);return mg_investment_workspace_detail($pdo,(string)$scenario['workspace_public_id']);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
