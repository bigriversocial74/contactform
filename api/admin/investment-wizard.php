<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));

try {
    mg_investment_require_permission($actor,'admin.investment.view');

    if ($method==='GET') {
        mg_rate_limit('admin.investment_wizard.read','user:'.$actorId,240,60);
        $workspaceId=trim((string)($_GET['workspace_id']??''));
        if ($workspaceId!=='') mg_ok(['workspace'=>mg_investment_workspace_detail($pdo,$workspaceId)],'Investment workspace loaded.');
        mg_ok(['items'=>mg_investment_workspace_list($pdo)],'Investment workspaces loaded.');
    }

    if ($method!=='POST') mg_fail('Method not allowed.',405);

    mg_investment_require_permission($actor,'admin.investment.manage');
    mg_rate_limit('admin.investment_wizard.write','user:'.$actorId,120,60);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));

    $result=match($action) {
        'create_workspace'=>mg_investment_create_workspace($pdo,$actor,$input),
        'save_workspace'=>mg_investment_save_workspace($pdo,$actor,$input),
        'save_scenario'=>mg_investment_save_scenario_audited($pdo,$actor,$input),
        'clone_scenario'=>mg_investment_clone_scenario($pdo,$actor,mg_investment_text($input['scenario_id']??'',36,36,'Scenario identifier')),
        'save_budget'=>mg_investment_replace_budget_audited($pdo,$actor,$input),
        'save_goals'=>mg_investment_replace_goals_audited($pdo,$actor,$input),
        'save_metrics'=>mg_investment_save_metrics($pdo,$actor,$input),
        'save_documents'=>mg_investment_save_documents_audited_v3($pdo,$actor,$input),
        'adopt_round'=>mg_investment_adopt_round($pdo,$actor,$input),
        'update_round'=>mg_investment_update_round_audited($pdo,$actor,$input),
        'run_ai_analysis'=>mg_investment_run_claude_analysis($pdo,$actor,$input),
        default=>throw new MgInvestmentException('Invalid Investment Wizard action.'),
    };

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(['workspace'=>$result],'Investment Wizard changes saved.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(),$error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error,'admin.investment_wizard.failed','Unable to update the Investment Wizard.',500,[],$actorId);
}
