<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$actor=mg_require_api_user();$actorId=(int)$actor['id'];$pdo=mg_db();$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
try{
  mg_investment_require_permission($actor,'admin.investor_pipeline.view');
  if($method==='GET'){
    mg_rate_limit('admin.investor_pipeline.read','user:'.$actorId,300,60);$action=strtolower(trim((string)($_GET['action']??'dashboard')));
    $result=match($action){
      'dashboard'=>mg_investment_pipeline_dashboard_v2($pdo,$_GET),
      'detail'=>mg_investment_pipeline_detail($pdo,mg_investment_text($_GET['investor_id']??'',36,36,'Investor identifier')),
      'publication_preview'=>mg_investment_publication_preview($pdo,mg_investment_text($_GET['round_id']??'',36,36,'Round identifier')),
      'metric_adapters'=>['adapters'=>mg_investment_metric_adapters($pdo),'history'=>!empty($_GET['workspace_id'])?mg_investment_metric_history($pdo,mg_investment_text($_GET['workspace_id'],36,36,'Workspace identifier')):[]],
      default=>throw new MgInvestmentException('Invalid Investor Pipeline read action.'),
    };
    header('Cache-Control: private, no-store, max-age=0');mg_ok($result,'Investor Pipeline data loaded.');
  }
  if($method!=='POST')mg_fail('Method not allowed.',405);
  mg_investment_require_permission($actor,'admin.investor_pipeline.manage');mg_rate_limit('admin.investor_pipeline.write','user:'.$actorId,180,60);$input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));
  $result=match($action){
    'sync_profiles'=>['synced'=>mg_investment_pipeline_sync_profiles($pdo,$actorId),'dashboard'=>mg_investment_pipeline_dashboard_v2($pdo)],
    'save_record'=>mg_investment_pipeline_save_record_v2($pdo,$actor,$input),
    'add_activity'=>mg_investment_pipeline_add_activity($pdo,$actor,$input),
    'save_task'=>mg_investment_pipeline_save_task($pdo,$actor,$input),
    'complete_task'=>mg_investment_pipeline_complete_task($pdo,$actor,$input),
    'save_interest'=>mg_investment_pipeline_save_interest($pdo,$actor,$input),
    'set_access'=>mg_investment_pipeline_set_access($pdo,$actor,$input),
    'save_publication'=>mg_investment_publication_save($pdo,$actor,$input),
    'refresh_metrics'=>mg_investment_metrics_refresh($pdo,$actor,$input),
    'ai_draft'=>mg_investment_pipeline_ai_draft($pdo,$actor,$input),
    default=>throw new MgInvestmentException('Invalid Investor Pipeline action.'),
  };
  header('Cache-Control: private, no-store, max-age=0');mg_ok($result,'Investor Pipeline changes saved.');
}catch(MgInvestmentException $error){mg_fail($error->getMessage(),$error->httpStatus());}
catch(Throwable $error){mg_fail_unexpected($error,'admin.investor_pipeline.failed','Unable to update Investor Pipeline operations.',500,[],$actorId);}
