<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$user = mg_require_api_user();
$userId = (int)$user['id'];
try {
    mg_rate_limit('investment.portal.read', 'user:' . $userId, 180, 60);
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(mg_investment_portal_data(mg_db(), $user), 'Investor Portal loaded.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'investment.portal.failed', 'Unable to load the Investor Portal.', 500, [], $userId);
}
