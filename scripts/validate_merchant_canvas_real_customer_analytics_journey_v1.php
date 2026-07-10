<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function analytics_read(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) throw new RuntimeException('Unable to read ' . $path);
    return $content;
}

function analytics_require(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$page = analytics_read($root . '/merchant-canvas.php');
$runtime = analytics_read($root . '/assets/js/merchant-canvas-manual-operations.js');
$analyticsRuntime = analytics_read($root . '/assets/js/merchant-canvas-customer-analytics.js');
$analyticsCss = analytics_read($root . '/assets/css/merchant-canvas-customer-analytics.css');
$endpoint = analytics_read($root . '/api/merchant-canvas/customer-analytics.php');
$helper = analytics_read($root . '/api/store/_canvas_analytics.php');
$health = analytics_read($root . '/api/merchant-canvas/health.php');
$containment = analytics_read($root . '/assets/js/merchant-canvas-containment.js');
$sql = analytics_read($root . '/database/merchant_canvas_real_customer_analytics_journey_v1.sql');

analytics_require(str_contains($page, '/assets/js/merchant-canvas-manual-operations.js'), 'Merchant Canvas must retain the stabilized manual operations runtime.');
analytics_require(str_contains($page, '/assets/js/merchant-canvas-customer-analytics.js'), 'Merchant Canvas must load the customer analytics runtime.');
analytics_require(str_contains($page, '/assets/css/merchant-canvas-customer-analytics.css'), 'Merchant Canvas must load customer analytics styling.');
analytics_require(strpos($page, '/assets/js/merchant-canvas-manual-operations.js') < strpos($page, '/assets/js/merchant-canvas-customer-analytics.js'), 'Analytics must load after the stabilized manual runtime.');
analytics_require(!str_contains($page, "'/assets/js/merchant-canvas.js'"), 'Legacy polling runtime must remain retired.');

analytics_require(str_contains($endpoint, 'mg_user_has_merchant_access'), 'Analytics endpoint must require merchant access.');
analytics_require(str_contains($endpoint, 'mg_store_safe_public_id'), 'Analytics endpoint must validate the selected Store Canvas session.');
analytics_require(str_contains($endpoint, 'mg_store_analytics_customer_payload'), 'Analytics endpoint must use the merchant-scoped analytics payload.');
analytics_require(str_contains($endpoint, "mg_rate_limit('merchant_canvas.customer_analytics'"), 'Analytics endpoint must be rate limited.');

analytics_require(str_contains($helper, 'mg_merchant_canvas_journey_events'), 'Analytics helper must use the canonical journey table.');
analytics_require(str_contains($helper, 'ON DUPLICATE KEY UPDATE'), 'Canonical journey writes must be idempotent.');
analytics_require(str_contains($helper, "'event_key' => 'session:'"), 'Session events must use stable server-generated event keys.');
analytics_require(str_contains($helper, "'event_key' => 'message:'"), 'Message events must use stable server-generated event keys.');
analytics_require(str_contains($helper, "'event_key' => 'wallet:'"), 'Reward events must use stable server-generated event keys.');
analytics_require(str_contains($helper, 'WHERE s.merchant_user_id=? AND s.customer_user_id=?'), 'Session analytics must be scoped to the merchant/customer pair.');
analytics_require(str_contains($helper, 'WHERE wi.merchant_user_id=? AND wi.user_id=?'), 'Reward analytics must be scoped to the merchant/customer pair.');
analytics_require(str_contains($helper, 'mg_store_manual_ops_crm_get'), 'Customer segments must preserve durable CRM safeguards.');
analytics_require(!str_contains($helper, 'wi.store_session_id'), 'Analytics must not query a nonexistent wallet_items.store_session_id column.');

analytics_require(str_contains($analyticsRuntime, 'AbortController'), 'Analytics runtime must cancel stale customer requests.');
analytics_require(str_contains($analyticsRuntime, 'MutationObserver'), 'Analytics runtime must remount after stabilized CRM refreshes.');
analytics_require(str_contains($analyticsRuntime, 'role="tablist"'), 'Analytics drawer must expose accessible tab semantics.');
analytics_require(str_contains($analyticsRuntime, "data-analytics-tab=\"journey\""), 'Analytics drawer must include a Journey tab.');
analytics_require(str_contains($analyticsRuntime, "data-analytics-tab=\"history\""), 'Analytics drawer must include a History tab.');
analytics_require(str_contains($analyticsRuntime, '/api/merchant-canvas/customer-analytics.php'), 'Analytics runtime must use the merchant-scoped API.');
analytics_require(!str_contains($analyticsRuntime, 'MG.post('), 'Browser analytics must not write customer journey events.');
analytics_require(!str_contains($analyticsRuntime, 'setInterval('), 'Analytics runtime must not add interval polling.');
analytics_require(str_contains($analyticsCss, '.mg-canvas-analytics-tablist'), 'Analytics tabs must have scoped styling.');
analytics_require(str_contains($analyticsCss, '@media(prefers-reduced-motion:reduce)'), 'Analytics styling must respect reduced motion.');

analytics_require(str_contains($sql, 'CREATE TABLE IF NOT EXISTS mg_merchant_canvas_journey_events'), 'Migration must create the canonical journey table.');
analytics_require(str_contains($sql, 'UNIQUE KEY uq_mg_canvas_journey_event_key (merchant_user_id, event_key)'), 'Migration must enforce merchant-scoped event deduplication.');
analytics_require(str_contains($health, "'mg_merchant_canvas_journey_events'"), 'Diagnostics must report the customer journey table.');
analytics_require(str_contains($health, "'journey_events'"), 'Diagnostics must report merchant-scoped journey event counts.');
analytics_require(str_contains($runtime, 'stableActionKey'), 'Manual messaging and reward idempotency must remain active.');
analytics_require(str_contains($containment, '/api/merchant-canvas/auto-chat.php'), 'Production containment must remain active for automatic chat.');
analytics_require(str_contains($containment, '/api/merchant-canvas/campaign-trigger.php'), 'Production containment must remain active for automatic campaign triggers.');

if ($failures !== []) {
    fwrite(STDERR, "Merchant Canvas Real Customer Analytics & Journey Data v1 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Merchant Canvas Real Customer Analytics & Journey Data v1 validation passed.\n");
