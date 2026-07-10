<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$check = static function (bool $passed, string $label) use (&$failures, &$checks): void {
    $checks++;
    if (!$passed) $failures[] = $label;
};

$page = $read('merchant-canvas.php');
$css = $read('assets/css/merchant-canvas-restoration.css');
$visual = $read('assets/js/merchant-canvas-visual-restoration.js');
$recommendations = $read('assets/js/merchant-canvas-campaign-recommendations.js');
$control = $read('api/merchant-canvas/control-center.php');
$send = $read('api/merchant-canvas/send-campaign-recommendation.php');
$helper = $read('api/store/_canvas_campaign_recommendations.php');
$redirect = $read('campaign-recommendation.php');
$containment = $read('assets/js/merchant-canvas-containment.js');

$check(
    str_contains($page, 'mg-canvas-map-restored')
    && str_contains($page, 'data-canvas-server-zones')
    && str_contains($page, 'data-canvas-open-control')
    && !str_contains($page, 'mg-canvas-containment-banner')
    && !str_contains($page, 'mg-canvas-command-strip'),
    '1. Store Canvas is one restored right-column canvas without containment and command-strip cards'
);

$check(
    str_contains($page, 'merchant-canvas-visual-restoration.js')
    && str_contains($page, 'merchant-canvas-campaign-recommendations.js')
    && str_contains($page, 'merchant-canvas-customer-analytics.js')
    && str_contains($page, 'data-canvas-drawer'),
    '2. Restored visual runtime preserves the detailed customer intelligence drawer'
);

$check(
    str_contains($css, '.mg-canvas-map-restored')
    && str_contains($css, '.mg-canvas-server-zone')
    && str_contains($css, '.mg-canvas-control-drawer')
    && str_contains($css, '.mg-canvas-zone-drawer')
    && str_contains($css, '.mg-canvas-recommendation-panel'),
    '3. Full-canvas, merchant drawer, trigger drawer, and recommendation UI styles exist'
);

$check(
    str_contains($visual, 'positionCustomers')
    && str_contains($visual, "card.dataset.visualMovement = 'presentation-only'")
    && str_contains($visual, '/api/merchant-canvas/control-center.php')
    && str_contains($visual, '/api/merchant-canvas/trigger-zone-save.php')
    && !str_contains($visual, 'localStorage')
    && !str_contains($visual, '/api/merchant-canvas/auto-chat.php')
    && !str_contains($visual, '/api/merchant-canvas/campaign-trigger.php'),
    '4. Avatar movement and trigger placement are visual/server-owned and cannot call automatic action routes'
);

$check(
    str_contains($visual, 'Merchant Control Center')
    && str_contains($visual, 'Predictive commerce intelligence')
    && str_contains($visual, 'Reward separation')
    && str_contains($visual, 'campaign completion'),
    '5. Merchant Control Center exposes campaigns, intelligence, readiness, and security boundaries'
);

$check(
    str_contains($visual, 'Server eligibility gates')
    && str_contains($visual, 'Campaign delivery contract')
    && str_contains($visual, 'Server-authoritative readiness')
    && str_contains($visual, "automation_action: 'notify_only'")
    && str_contains($visual, "fallback_action: 'analytics_only'"),
    '6. Trigger drawers are upgraded for campaign binding, eligibility, delivery, and server readiness'
);

$check(
    str_contains($control, "'automatic_trigger_execution' => false")
    && str_contains($control, "'browser_overlap_execution' => false")
    && str_contains($control, "'reward_issue_authority' => 'campaign_completion'")
    && str_contains($control, "'reward_destination' => 'wallet_then_inbox_pppm'")
    && str_contains($control, 'mg_canvas_trigger_zone_list'),
    '7. Control Center returns merchant-scoped campaign/zone truth and explicit containment capabilities'
);

$check(
    str_contains($send, "mg_require_method('POST')")
    && str_contains($send, 'mg_require_csrf_for_write')
    && str_contains($send, 'mg_user_has_merchant_access')
    && str_contains($send, "mg_rate_limit('merchant_canvas.campaign_recommendation'")
    && str_contains($send, 'mg_store_send_campaign_recommendation_notification'),
    '8. Campaign recommendation endpoint is authenticated, CSRF protected, rate limited, and merchant gated'
);

$check(
    str_contains($helper, "'campaign_recommendation'")
    && str_contains($helper, "'campaign_recommendation_sent'")
    && str_contains($helper, "'reward_issued' => false")
    && str_contains($helper, "'reward_issue_policy' => 'campaign_completion_only'")
    && str_contains($helper, 'mg_store_manual_ops_assert_message_allowed')
    && str_contains($helper, 'mg_store_manual_ops_receipt_claim')
    && str_contains($helper, 'INTERVAL 5 MINUTE')
    && str_contains($helper, 'mg_create_notification')
    && !str_contains($helper, 'INSERT INTO wallet_items'),
    '9. Recommendation delivery is notification-only with DNM, idempotency, frequency, and no wallet issuance'
);

$check(
    str_contains($redirect, "action_type='campaign_recommendation'")
    && str_contains($redirect, "'campaign_recommendation_open'")
    && str_contains($redirect, "'campaign_recommendation_opened'")
    && str_contains($redirect, 'customer_user_id=?')
    && str_contains($redirect, 'mg_notification_safe_action_url'),
    '10. Authenticated notification redirect records customer-scoped recommendation opens safely'
);

$check(
    str_contains($recommendations, '/api/merchant-canvas/send-campaign-recommendation.php')
    && str_contains($recommendations, 'Send Recommendation Notification')
    && str_contains($recommendations, 'No reward, Wallet item, Inbox item, or PPPM item is created')
    && str_contains($recommendations, 'campaign completion owns Wallet delivery'),
    '11. Customer drawer sends clear notification-only campaign recommendations'
);

$check(
    str_contains($containment, '/api/merchant-canvas/auto-chat.php')
    && str_contains($containment, '/api/merchant-canvas/campaign-trigger.php')
    && str_contains($containment, '/api/merchant-canvas/campaign-trigger-automation.php'),
    '12. Production containment still blocks legacy automatic chat and campaign trigger routes'
);

if ($failures !== []) {
    echo "Store Canvas Visual Restoration & Server-Ready Controls v1 validation failed:\n";
    foreach ($failures as $failure) echo ' - ' . $failure . "\n";
    echo count($failures) . ' of ' . $checks . " checks failed.\n";
    exit(1);
}

echo 'Store Canvas Visual Restoration & Server-Ready Controls v1 validation passed: ' . $checks . " checks.\n";
