<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));

try {
    mg_investment_require_permission($actor,'admin.investment.closing.view');

    if ($method==='GET') {
        mg_rate_limit('admin.investment_closing.read','user:'.$actorId,300,60);
        $action=strtolower(trim((string)($_GET['action']??'dashboard')));
        $result=match($action) {
            'dashboard'=>mg_investment_closing_dashboard_audited($pdo,$_GET),
            'relations'=>mg_investment_relations_detail($pdo,mg_investment_text($_GET['round_id']??'',36,36,'Round identifier')),
            default=>throw new MgInvestmentException('Invalid closing read action.'),
        };
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($result,'Investment closing data loaded.');
    }

    if ($method!=='POST') mg_fail('Method not allowed.',405);

    mg_rate_limit('admin.investment_closing.write','user:'.$actorId,180,60);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));

    $result=match($action) {
        'sync'=>mg_investment_closing_sync_audited($pdo,$actorId)+['dashboard'=>mg_investment_closing_dashboard_audited($pdo,$input)],
        'save_profile'=>mg_investment_closing_save_profile_audited($pdo,$actor,$input),
        'save_record'=>mg_investment_closing_save_record_audited($pdo,$actor,$input),
        'save_batch'=>mg_investment_closing_save_batch($pdo,$actor,$input),
        'assign_batch'=>mg_investment_closing_assign_batch_audited($pdo,$actor,$input),
        'complete_batch'=>mg_investment_closing_complete_batch_audited($pdo,$actor,$input),
        'reopen_batch'=>mg_investment_closing_reopen_batch_audited($pdo,$actor,$input),
        'save_onboarding'=>mg_investment_onboarding_save($pdo,$actor,$input),
        'save_packet'=>mg_investment_packet_save($pdo,$actor,$input),
        'save_document'=>mg_investment_packet_document_save($pdo,$actor,$input),
        'seed_compliance'=>mg_investment_compliance_seed($pdo,$actor,$input),
        'save_compliance'=>mg_investment_compliance_save_audited($pdo,$actor,$input),
        'request_verification'=>mg_investment_financial_request_audited($pdo,$actor,$input),
        'decide_verification'=>mg_investment_financial_decide_audited_v3($pdo,$actor,$input),
        'create_reconciliation'=>mg_investment_reconciliation_create($pdo,$actor,$input),
        'refresh_readiness'=>mg_investment_closing_refresh_readiness($pdo,$actor,$input),
        'save_period'=>mg_investment_reporting_period_save_audited($pdo,$actor,$input),
        'save_snapshot'=>mg_investment_reporting_snapshot_save_audited($pdo,$actor,$input),
        'save_actual'=>mg_investment_use_of_funds_actual_save_audited($pdo,$actor,$input),
        'ai_draft'=>mg_investment_closing_ai_draft($pdo,$actor,$input),
        default=>throw new MgInvestmentException('Invalid investment closing action.'),
    };

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result,'Investment closing changes saved.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(),$error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error,'admin.investment_closing.failed','Unable to update investment closing operations.',500,[],$actorId);
}
