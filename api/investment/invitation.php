<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$user = mg_require_api_user();
$userId = (int)$user['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));

try {
    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    mg_rate_limit('investment.invitation.accept', 'user:' . $userId, 8, 3600);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'accept')));
    if ($action !== 'accept') throw new MgInvestmentException('Invalid Investor invitation action.');
    $result = mg_investment_invitation_accept($pdo, $user, $input);
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result, 'Investor onboarding submitted for Super Admin review.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'investment.invitation.failed', 'Unable to complete Investor onboarding.', 500, [], $userId);
}
