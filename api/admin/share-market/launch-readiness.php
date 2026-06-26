<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/launch-readiness.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.launch_readiness.admin', 'user:' . (int)$user['id'], 30, 300);

try {
    if ($method === 'GET') mg_ok(['launch_readiness' => mg_share_market_launch_readiness_admin_queue(mg_db())], 'Launch readiness queue loaded.');
    if ($method === 'POST') {
        $input = mg_input();
        mg_require_csrf_for_write($input);
        mg_ok(['launch_readiness' => mg_share_market_launch_readiness_mark_ready(mg_db(), $input, $user)], 'Series marked ready for future execution review. No launch was executed.');
    }
    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (DomainException $e) {
    mg_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    mg_fail('Unable to process launch readiness.', 500);
}
