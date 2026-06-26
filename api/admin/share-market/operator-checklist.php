<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/operator-checklist.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.operator_checklist.admin', 'user:' . (int)$user['id'], 30, 300);

try {
    if ($method === 'GET') mg_ok(['operator_checklist' => mg_share_market_operator_admin_queue(mg_db())], 'Operator checklist queue loaded.');
    if ($method === 'POST') {
        $input = mg_input();
        mg_require_csrf_for_write($input);
        mg_ok(['operator_checklist' => mg_share_market_operator_checklist_save(mg_db(), $input, $user)], 'Operator checklist saved. Launch remains locked.');
    }
    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_fail('Unable to process operator checklist.', 500);
}
