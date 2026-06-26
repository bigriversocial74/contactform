<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/credit-reserve-audit.php';

$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.credit_reserve.audit', 'user:' . (int)$user['id'], 30, 300);

try {
    mg_ok(['credit_reserve_audit' => mg_share_market_credit_reserve_audit_history(mg_db())], 'Credit reserve audit loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load credit reserve audit.', 500);
}
