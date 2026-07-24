<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$user=mg_require_api_user();
$userId=(int)$user['id'];
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));

try {
    if ($method==='GET') {
        mg_rate_limit('investment.portal.read','user:'.$userId,180,60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_investment_portal_data_v5_final2($pdo,$user),'Investor Portal loaded.');
    }

    if ($method!=='POST') mg_fail('Method not allowed.',405);

    mg_rate_limit('investment.portal.write','user:'.$userId,120,60);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'event')));

    $result=match($action) {
        'submit_diligence'=>mg_investment_portal_submit_diligence_v5_final2($pdo,$user,$input),
        'submit_interest'=>mg_investment_portal_submit_interest_v5_final2($pdo,$user,$input),
        'acknowledge_notice'=>mg_investment_portal_acknowledge_notice_v5_final2($pdo,$user,$input),
        'event'=>mg_investment_portal_event_final2($pdo,$user,$input),
        default=>throw new MgInvestmentException('Invalid Investor Portal action.'),
    };

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result,'Investor Portal action completed.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(),$error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error,'investment.portal.failed','Unable to update the Investor Portal.',500,[],$userId);
}
