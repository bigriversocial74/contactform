<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/investment/investment-service.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));

try {
    mg_investment_require_permission($actor,'admin.investment.governance.view');

    if ($method==='GET') {
        mg_rate_limit('admin.investor_governance.read','user:'.$actorId,300,60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_investment_governance_dashboard_audited($pdo,$_GET),'Investor governance data loaded.');
    }

    if ($method!=='POST') mg_fail('Method not allowed.',405);

    mg_rate_limit('admin.investor_governance.write','user:'.$actorId,180,60);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));

    $result=match($action) {
        'save_participant'=>mg_investment_governance_save_participant($pdo,$actor,$input),
        'save_appointment'=>mg_investment_governance_save_appointment($pdo,$actor,$input),
        'save_meeting'=>mg_investment_governance_save_meeting_audited($pdo,$actor,$input),
        'save_attendee'=>mg_investment_governance_save_attendee($pdo,$actor,$input),
        'save_agenda'=>mg_investment_governance_save_agenda($pdo,$actor,$input),
        'save_packet_document'=>mg_investment_governance_save_packet_document_audited($pdo,$actor,$input),
        'save_minutes'=>mg_investment_governance_save_minutes_audited($pdo,$actor,$input),
        'save_consent'=>mg_investment_governance_save_consent_audited($pdo,$actor,$input),
        'record_consent_response'=>mg_investment_governance_record_consent_response_audited($pdo,$actor,$input),
        'set_consent_visibility'=>mg_investment_governance_set_consent_visibility_audited($pdo,$actor,$input),
        'save_right'=>mg_investment_governance_save_right_audited_v2($pdo,$actor,$input),
        'save_obligation'=>mg_investment_governance_save_obligation_audited($pdo,$actor,$input),
        'complete_obligation'=>mg_investment_governance_complete_obligation($pdo,$actor,$input),
        'refresh_holdings'=>mg_investment_governance_refresh_holdings_audited($pdo,$actor,$input),
        'save_tax_document'=>mg_investment_governance_save_tax_document_audited_v3($pdo,$actor,$input),
        'save_notice'=>mg_investment_governance_save_notice_audited_v3($pdo,$actor,$input),
        'ai_draft'=>mg_investment_governance_ai_draft($pdo,$actor,$input),
        default=>throw new MgInvestmentException('Invalid investor governance action.'),
    };

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result,'Investor governance changes saved.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(),$error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error,'admin.investor_governance.failed','Unable to update investor governance operations.',500,[],$actorId);
}
