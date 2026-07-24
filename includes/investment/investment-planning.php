<?php
declare(strict_types=1);
function mg_investment_default_assumptions(): array
{
    return [
        'starting_merchants'=>5,
        'new_merchants_per_month'=>20,
        'merchant_churn_percent'=>2,
        'merchant_subscription_monthly'=>25,
        'transactions_per_merchant_monthly'=>25,
        'average_transaction_value'=>25,
        'platform_commission_percent'=>15,
        'revenue_start_month'=>1,
        'starting_cash'=>0,
        'contingency_percent'=>10,
    ];
}

function mg_investment_operating_plan_monthly(array $plan): int
{
    $keys = ['founder_compensation','payroll','contractors','hosting','ai_software','marketing','merchant_acquisition','sales','legal','accounting','insurance','administrative','other'];
    $total = 0;
    foreach ($keys as $key) $total += mg_investment_money($plan[$key] ?? 0);
    return $total;
}

function mg_investment_scenario_calculate(array $workspace, array $scenario): array
{
    $operating = mg_investment_json($workspace['operating_plan_json'] ?? null);
    $workspaceAssumptions = array_replace(mg_investment_default_assumptions(),mg_investment_json($workspace['assumptions_json'] ?? null));
    $scenarioAssumptions = array_replace($workspaceAssumptions,mg_investment_json($scenario['assumptions_json'] ?? null));
    $stress = mg_investment_json($scenario['stress_tests_json'] ?? null);
    $monthlyBurn = mg_investment_operating_plan_monthly($operating);
    $oneTime = mg_investment_money($operating['one_time_launch_expenses'] ?? 0);
    $contingencyPercent = (float)($scenarioAssumptions['contingency_percent'] ?? 10);
    $target = (int)($scenario['target_raise_cents'] ?? 0);
    $contingency = (int)round(($monthlyBurn * max(1,(int)($scenario['desired_runway_months'] ?? 12)) + $oneTime) * ($contingencyPercent / 100));
    $available = max(0,$target - $oneTime - $contingency);
    $runway = $monthlyBurn > 0 ? round($available / $monthlyBurn,1) : null;
    $cap = (int)($scenario['valuation_cap_cents'] ?? 0);
    $approxOwnership = ($cap > 0 && $target > 0) ? min(100,($target / $cap) * 100) : null;
    $warnings = [];
    if ((int)($scenario['minimum_raise_cents'] ?? 0) > $target) $warnings[] = 'Minimum raise exceeds target raise.';
    if ((int)($scenario['maximum_raise_cents'] ?? 0) > 0 && (int)$scenario['maximum_raise_cents'] < $target) $warnings[] = 'Maximum raise is below target raise.';
    if ($runway !== null && $runway < (int)($scenario['desired_runway_months'] ?? 12)) $warnings[] = 'The current operating plan does not reach the desired runway.';
    if ($approxOwnership !== null && $approxOwnership * 100 > (int)($scenario['maximum_dilution_bps'] ?? 1000)) $warnings[] = 'Approximate SAFE ownership exceeds the entered maximum dilution.';
    if ($monthlyBurn === 0) $warnings[] = 'Monthly operating costs are incomplete.';
    if ($cap === 0) $warnings[] = 'Valuation cap is not finalized.';
    return [
        'monthly_burn_cents'=>$monthlyBurn,
        'annual_burn_cents'=>$monthlyBurn * 12,
        'one_time_costs_cents'=>$oneTime,
        'contingency_cents'=>$contingency,
        'modeled_runway_months'=>$runway,
        'approximate_investor_ownership_percent'=>$approxOwnership !== null ? round($approxOwnership,2) : null,
        'approximate_founder_pre_pool_percent'=>$approxOwnership !== null ? round(100-$approxOwnership,2) : null,
        'desired_runway_months'=>(int)($scenario['desired_runway_months'] ?? 12),
        'warnings'=>$warnings,
        'disclaimer'=>'Planning estimate only. Actual dilution and ownership depend on executed documents, capitalization definitions, other instruments, option pools and future financings.',
        'assumptions'=>$scenarioAssumptions,
        'stress_tests'=>$stress,
    ];
}

function mg_investment_scenario_projection(array $workspace, array $scenario, array $calculations): array
{
    $assumptions = $calculations['assumptions'];
    $stress = $calculations['stress_tests'];
    $months = max(12,min(36,(int)($scenario['forecast_months'] ?? 24)));
    $case = (string)($scenario['forecast_case'] ?? 'expected');
    $caseMultiplier = ['conservative'=>0.75,'expected'=>1.0,'upside'=>1.25][$case] ?? 1.0;
    $merchantGrowthPenalty = max(0,min(0.95,((float)($stress['merchant_growth_lower_percent'] ?? 0))/100));
    $expenseIncrease = max(0,((float)($stress['expenses_higher_percent'] ?? 0))/100);
    $revenueDelay = max(0,min(24,(int)($stress['revenue_delay_months'] ?? 0)));
    $raiseMultiplier = max(0.1,min(1.5,(float)($stress['raise_close_percent'] ?? 100)/100));
    $cash = ((int)($scenario['target_raise_cents'] ?? 0) * $raiseMultiplier) + mg_investment_money($assumptions['starting_cash'] ?? 0);
    $merchants = max(0,(float)($assumptions['starting_merchants'] ?? 0));
    $newMonthly = max(0,(float)($assumptions['new_merchants_per_month'] ?? 0)) * $caseMultiplier * (1-$merchantGrowthPenalty);
    $churn = max(0,min(1,(float)($assumptions['merchant_churn_percent'] ?? 0)/100));
    $subscription = mg_investment_money($assumptions['merchant_subscription_monthly'] ?? 0);
    $txPerMerchant = max(0,(float)($assumptions['transactions_per_merchant_monthly'] ?? 0));
    $avgTx = mg_investment_money($assumptions['average_transaction_value'] ?? 0);
    $commission = max(0,min(1,(float)($assumptions['platform_commission_percent'] ?? 0)/100));
    $revenueStart = max(1,(int)($assumptions['revenue_start_month'] ?? 1) + $revenueDelay);
    $baseExpense = (int)($calculations['monthly_burn_cents'] ?? 0);
    $rows = [];
    $breakEven = null;
    $cashOut = null;
    for ($month=1;$month<=$months;$month++) {
        $beginning = $cash;
        $merchants = max(0,$merchants * (1-$churn) + $newMonthly);
        $gmv = $month >= $revenueStart ? (int)round($merchants * $txPerMerchant * $avgTx) : 0;
        $subscriptionRevenue = $month >= $revenueStart ? (int)round($merchants * $subscription) : 0;
        $commissionRevenue = $month >= $revenueStart ? (int)round($gmv * $commission) : 0;
        $revenue = $subscriptionRevenue + $commissionRevenue;
        $expense = (int)round($baseExpense * (1+$expenseIncrease));
        $net = $revenue - $expense;
        $cash += $net;
        if ($breakEven === null && $revenue >= $expense && $expense > 0) $breakEven = $month;
        if ($cashOut === null && $cash < 0) $cashOut = $month;
        $rows[] = [
            'month'=>$month,'beginning_cash_cents'=>$beginning,'merchant_count'=>round($merchants,1),
            'gmv_cents'=>$gmv,'subscription_revenue_cents'=>$subscriptionRevenue,
            'commission_revenue_cents'=>$commissionRevenue,'total_revenue_cents'=>$revenue,
            'expenses_cents'=>$expense,'net_cents'=>$net,'ending_cash_cents'=>$cash,
        ];
    }
    return ['case'=>$case,'months'=>$months,'break_even_month'=>$breakEven,'cash_out_month'=>$cashOut,'rows'=>$rows];
}

function mg_investment_workspace_by_public_id(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql = 'SELECT * FROM investment_workspaces WHERE public_id=? LIMIT 1'; if ($lock) $sql .= ' FOR UPDATE';
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new MgInvestmentException('Investment workspace not found.',404);
    return $row;
}

function mg_investment_scenario_by_public_id(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql='SELECT s.*,w.public_id AS workspace_public_id,w.company_json,w.capitalization_json,w.operating_plan_json,w.assumptions_json AS workspace_assumptions_json FROM investment_scenarios s INNER JOIN investment_workspaces w ON w.id=s.workspace_id WHERE s.public_id=? LIMIT 1';if($lock)$sql.=' FOR UPDATE';
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new MgInvestmentException('Investment scenario not found.',404);
    return $row;
}

function mg_investment_seed_scenario(PDO $pdo,int $workspaceId,int $userId,string $name,int $targetCents,int $months,int $dilutionBps): int
{
    $cap = $dilutionBps > 0 ? (int)round($targetCents / ($dilutionBps/10000)) : 0;
    $stmt=$pdo->prepare('INSERT INTO investment_scenarios (public_id,workspace_id,name,status,instrument_type,minimum_raise_cents,target_raise_cents,maximum_raise_cents,valuation_cap_cents,target_dilution_bps,maximum_dilution_bps,desired_runway_months,forecast_months,forecast_case,assumptions_json,stress_tests_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?, ?, ?,"draft","post_money_safe",?,?,?,?,?,?,?,24,"expected",?,JSON_OBJECT(),?,?,NOW(),NOW())');
    $stmt->execute([mg_investment_uuid(),$workspaceId,$name,(int)round($targetCents*.5),$targetCents,(int)round($targetCents*1.5),$cap,$dilutionBps,1000,$months,mg_investment_json_encode([]),$userId,$userId]);
    return (int)$pdo->lastInsertId();
}

function mg_investment_create_workspace(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investment.manage');
    $name=mg_investment_text($input['name'] ?? 'Microgifter Pre-Seed 2026',180,3,'Workspace name');$actorId=(int)$actor['id'];
    $pdo->beginTransaction();
    try{
        $publicId=mg_investment_uuid();
        $company=['public_name'=>'Microgifter','founder_name'=>'David Evans','founder_title'=>'Founder','entity_type'=>'Delaware C-Corporation','revenue_stage'=>'Pre-revenue','product_stage'=>'Functional platform / pre-commercial launch','website'=>'https://microgifter.com'];
        $capitalization=['authorized_shares'=>null,'issued_shares'=>1000000,'outstanding_shares'=>null,'founder_shares'=>null,'option_pool_shares'=>null,'verified'=>false,'snapshot_date'=>null];
        $operating=['founder_compensation'=>0,'payroll'=>0,'contractors'=>0,'hosting'=>0,'ai_software'=>0,'marketing'=>0,'merchant_acquisition'=>0,'sales'=>0,'legal'=>0,'accounting'=>0,'insurance'=>0,'administrative'=>0,'other'=>0,'one_time_launch_expenses'=>0];
        $stmt=$pdo->prepare('INSERT INTO investment_workspaces (public_id,owner_user_id,name,status,active_step,company_json,capitalization_json,operating_plan_json,assumptions_json,last_saved_by_user_id,created_at,updated_at) VALUES (?, ?, ?,"draft","company",?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$publicId,$actorId,$name,mg_investment_json_encode($company),mg_investment_json_encode($capitalization),mg_investment_json_encode($operating),mg_investment_json_encode(mg_investment_default_assumptions()),$actorId]);
        $workspaceId=(int)$pdo->lastInsertId();
        mg_investment_seed_scenario($pdo,$workspaceId,$actorId,'Minimum Launch — $250K',25000000,8,750);
        $preferred=mg_investment_seed_scenario($pdo,$workspaceId,$actorId,'Balanced Growth — $500K',50000000,14,750);
        mg_investment_seed_scenario($pdo,$workspaceId,$actorId,'Full Market Launch — $750K',75000000,18,750);
        $pdo->prepare('UPDATE investment_scenarios SET status="preferred" WHERE id=?')->execute([$preferred]);
        $pdo->prepare('UPDATE investment_workspaces SET preferred_scenario_id=? WHERE id=?')->execute([$preferred,$workspaceId]);
        $docs=['Pitch deck','Executive summary','Financial model','Capitalization summary','Use of funds','Corporate documents','SAFE or investment agreement','Risk disclosures','Board approvals'];
        $doc=$pdo->prepare('INSERT INTO investment_documents (public_id,workspace_id,document_type,title,status,visibility,created_by_user_id,created_at,updated_at) VALUES (?, ?, ?, ?,"missing","super_admin",?,NOW(),NOW())');
        foreach($docs as $title)$doc->execute([mg_investment_uuid(),$workspaceId,strtolower(str_replace(' ','_',$title)),$title,$actorId]);
        $pdo->commit();mg_audit('investment_workspace_created','investment_workspace',['workspace_id'=>$publicId],$actorId);
        return mg_investment_workspace_detail($pdo,$publicId);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
