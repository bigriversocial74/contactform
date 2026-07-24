<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));

try {
    mg_investment_require_permission($actor,'admin.investment.diligence.view');

    if ($method==='GET') {
        mg_rate_limit('admin.investor_diligence.read','user:'.$actorId,300,60);
        $action=strtolower(trim((string)($_GET['action']??'dashboard')));
        $result=match($action) {
            'dashboard'=>mg_investment_diligence_dashboard($pdo,$_GET),
            default=>throw new MgInvestmentException('Invalid diligence read action.'),
        };
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($result,'Investor diligence data loaded.');
    }

    if ($method!=='POST') mg_fail('Method not allowed.',405);

    mg_investment_require_permission($actor,'admin.investment.diligence.manage');
    mg_rate_limit('admin.investor_diligence.write','user:'.$actorId,180,60);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));

    $result=match($action) {
        'save_folder'=>mg_investment_dataroom_save_folder($pdo,$actor,$input),
        'save_document'=>mg_investment_dataroom_save_document_audited_v2($pdo,$actor,$input),
        'save_request'=>mg_investment_diligence_admin_save_request_audited($pdo,$actor,$input),
        'save_qa'=>mg_investment_qa_save_audited_v2($pdo,$actor,$input),
        'save_meeting'=>mg_investment_meeting_save_audited($pdo,$actor,$input),
        'save_communication'=>mg_investment_communication_save_audited_v3($pdo,$actor,$input),
        'review_interest'=>mg_investment_interest_review($pdo,$actor,$input),
        'refresh_engagement'=>mg_investment_engagement_refresh($pdo,$actor,$input),
        'ai_draft'=>mg_investment_diligence_ai_draft($pdo,$actor,$input),
        default=>throw new MgInvestmentException('Invalid diligence action.'),
    };

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result,'Investor diligence changes saved.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(),$error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error,'admin.investor_diligence.failed','Unable to update investor diligence operations.',500,[],$actorId);
}
