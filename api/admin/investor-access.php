<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        mg_investment_require_permission($actor, 'admin.investor_access.view');
        mg_rate_limit('admin.investor_access.read', 'user:' . $actorId, 240, 60);
        $requestId = trim((string)($_GET['request_id'] ?? ''));
        if ($requestId !== '') {
            $row = mg_investment_admin_request($pdo, $requestId);
            $item = mg_investment_access_public($row) + [
                'email' => (string)$row['email'],
                'full_name' => (string)$row['full_name'],
                'display_name' => (string)($row['display_name'] ?? $row['full_name']),
            ];
            $item = mg_investment_invitation_enrich_access_items($pdo, [$item])[0];
            mg_ok(['request' => $item], 'Investor-access request loaded.');
        }
        $items = mg_investment_invitation_enrich_access_items($pdo, mg_investment_admin_access_queue($pdo, $_GET));
        mg_ok(['items' => $items], 'Investor-access queue loaded.');
    }
    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    mg_rate_limit('admin.investor_access.write', 'user:' . $actorId, 60, 60);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $row = mg_investment_admin_decide_access($pdo, $actor, $input);
    $item = mg_investment_access_public($row) + [
        'email' => (string)$row['email'],
        'full_name' => (string)$row['full_name'],
        'display_name' => (string)($row['display_name'] ?? $row['full_name']),
    ];
    $item = mg_investment_invitation_enrich_access_items($pdo, [$item])[0];
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(['request' => $item], 'Investor-access decision saved.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'admin.investor_access.failed', 'Unable to manage investor access.', 500, [], $actorId);
}
