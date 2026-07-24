<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$user = mg_require_api_user();
$userId = (int)$user['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        mg_rate_limit('investment.access.read', 'user:' . $userId, 120, 60);
        $request = mg_investment_find_access_request($pdo, $userId);
        mg_ok(['request' => $request ? mg_investment_access_public($request) : null], 'Investor-access status loaded.');
    }
    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    mg_rate_limit('investment.access.write', 'user:' . $userId, 8, 3600);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'submit')));
    $result = match ($action) {
        'submit', 'resubmit' => mg_investment_submit_access_request($pdo, $user, $input),
        'withdraw' => mg_investment_withdraw_access_request($pdo, $user),
        default => throw new MgInvestmentException('Invalid investor-access action.'),
    };
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(['request' => $result], 'Investor-access request updated.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'investment.access.failed', 'Unable to update investor access.', 500, [], $userId);
}
