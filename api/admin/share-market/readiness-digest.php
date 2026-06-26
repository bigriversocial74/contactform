<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/readiness-notification-digest.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.readiness_digest.admin', 'user:' . (int)$user['id'], 10, 300);

try {
    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_ok(['readiness_digest' => mg_share_market_send_readiness_digest(mg_db())], 'Readiness digest queued.');
} catch (Throwable $e) {
    mg_fail('Unable to send readiness digest.', 500);
}
