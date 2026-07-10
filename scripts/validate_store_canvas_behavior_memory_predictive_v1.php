<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . ltrim($path, '/'));
    return is_string($content) ? $content : '';
};

$check = static function (bool $passed, string $label) use (&$failures, &$checks): void {
    $checks++;
    if (!$passed) $failures[] = $label;
};

$page = $read('merchant-canvas.php');
$headerJs = $read('assets/js/merchant-canvas-header-hud.js');
$headerCss = $read('assets/css/merchant-canvas-header-hud.css');
$behaviorJs = $read('assets/js/merchant-canvas-behavior-memory.js');
$behaviorCss = $read('assets/css/merchant-canvas-behavior-memory.css');
$helper = $read('api/store/_canvas_behavior_memory.php');
$customerEndpoint = $read('api/merchant-canvas/customer-behavior.php');
$activeEndpoint = $read('api/merchant-canvas/active-behavior.php');
$sql = $read('database/merchant_canvas_behavior_memory_predictive_v1.sql');

$check(
    str_contains($page, '/assets/css/merchant-canvas-header-hud.css')
    && str_contains($page, '/assets/js/merchant-canvas-header-hud.js')
    && str_contains($page, '/assets/css/merchant-canvas-behavior-memory.css')
    && str_contains($page, '/assets/js/merchant-canvas-behavior-memory.js')
    && !str_contains($page, 'Visual live canvas · guarded manual actions'),
    '1. Store Canvas loads the header/behavior upgrade and removes the obsolete HUD text'
);

$check(
    str_contains($headerJs, "document.querySelector('.mg-unified-header .mg-header-inner')")
    && str_contains($headerJs, 'headerInner.insertBefore(slot, actions || null)')
    && str_contains($headerJs, "className = 'mg-canvas-header-stats'")
    && str_contains($headerJs, "className = 'mg-canvas-header-live-pill'")
    && str_contains($headerJs, 'MutationObserver'),
    '2. Desktop Store Canvas statistics and live status are mirrored into the shared main header'
);

$check(
    str_contains($headerCss, '@media(min-width:981px)')
    && str_contains($headerCss, '.mg-canvas-header-stats')
    && str_contains($headerCss, '.mg-canvas-header-live-pill')
    && str_contains($headerCss, '.mg-canvas-map-restored .mg-canvas-hud-stats')
    && str_contains($headerCss, '@media(max-width:980px)'),
    '3. Header HUD is desktop-scoped while the existing mobile sticky-stat source remains available'
);

$check(
    str_contains($sql, 'CREATE TABLE IF NOT EXISTS mg_merchant_customer_behavior_profiles')
    && str_contains($sql, 'UNIQUE KEY uq_mg_behavior_profile_pair (merchant_user_id, customer_user_id)')
    && str_contains($sql, 'return_7d_probability')
    && str_contains($sql, 'campaign_engagement_probability')
    && str_contains($sql, 'reward_claim_probability')
    && str_contains($sql, 'inactivity_risk_probability')
    && str_contains($sql, 'evidence_json'),
    '4. SQL creates one durable merchant/customer behavior profile with transparent projection fields'
);

$check(
    str_contains($helper, "require_once __DIR__ . '/_canvas_analytics.php'")
    && str_contains($helper, 'mg_store_analytics_summary')
    && str_contains($helper, 'mg_store_analytics_journey')
    && str_contains($helper, 'mg_store_analytics_visits')
    && str_contains($helper, 'mg_store_manual_ops_crm_get')
    && str_contains($helper, 'mg_store_analytics_sync_customer'),
    '5. Behavior memory derives from canonical Store Canvas journey, CRM, visit, message, and Wallet authorities'
);

$check(
    str_contains($helper, "'protected_traits_excluded' => true")
    && str_contains($helper, "'browser_action_authority' => false")
    && str_contains($helper, "'automatic_message_authority' => false")
    && str_contains($helper, "'automatic_reward_authority' => false")
    && str_contains($helper, "'recommendation_requires_merchant_approval' => true"),
    '6. Predictive profiles exclude protected traits and cannot authorize browser, message, reward, or campaign actions'
);

$check(
    str_contains($helper, "'relationship_stage'")
    && str_contains($helper, "'greeting_mode'")
    && str_contains($helper, "'movement_mode'")
    && str_contains($helper, "'follow_state'")
    && str_contains($helper, "'release_state'")
    && str_contains($helper, "'confidence_score'")
    && str_contains($helper, "'evidence_json'"),
    '7. The server profile includes relationship, greeting, movement, follow/release, confidence, and evidence memory'
);

$insertStart = strpos($helper, 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
$executeStart = strpos($helper, '$stmt->execute([');
$executeEnd = $executeStart === false ? false : strpos($helper, ']);', $executeStart);
$executeBlock = ($executeStart !== false && $executeEnd !== false) ? substr($helper, $executeStart, $executeEnd - $executeStart) : '';
$check(
    $insertStart !== false
    && substr_count('?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?', '?') === 20
    && str_contains($executeBlock, 'mg_public_uuid(), $merchantUserId, $customerUserId')
    && str_contains($executeBlock, '$profile[\'last_calculated_at\']'),
    '8. Behavior profile upsert has the required twenty bound values before server timestamps'
);

$check(
    str_contains($customerEndpoint, "mg_require_method('GET')")
    && str_contains($customerEndpoint, 'mg_require_api_user')
    && str_contains($customerEndpoint, 'mg_user_has_merchant_access')
    && str_contains($customerEndpoint, "mg_rate_limit('merchant_canvas.customer_behavior'")
    && str_contains($customerEndpoint, 'mg_store_analytics_session_context')
    && str_contains($customerEndpoint, 'mg_store_behavior_profile_sync'),
    '9. Customer behavior endpoint is merchant-authenticated, rate-limited, and session scoped'
);

$check(
    str_contains($activeEndpoint, "mg_require_method('GET')")
    && str_contains($activeEndpoint, 'mg_require_api_user')
    && str_contains($activeEndpoint, 'mg_user_has_merchant_access')
    && str_contains($activeEndpoint, "mg_rate_limit('merchant_canvas.active_behavior'")
    && str_contains($activeEndpoint, 'mg_store_behavior_active_profiles'),
    '10. Active behavior endpoint is merchant-authenticated, rate-limited, and bounded'
);

$check(
    str_contains($customerEndpoint, "'customer_to_customer_chat' => false")
    && str_contains($customerEndpoint, "'customer_item_sending' => false")
    && str_contains($customerEndpoint, "'peer_matching' => false")
    && str_contains($customerEndpoint, "'activation_policy' => 'server_gated_future_phase'"),
    '11. Future customer chat, peer matching, and item sending remain explicitly server gated'
);

$check(
    str_contains($behaviorJs, '/api/merchant-canvas/active-behavior.php')
    && str_contains($behaviorJs, '/api/merchant-canvas/customer-behavior.php')
    && str_contains($behaviorJs, "data-analytics-tab', 'behavior'")
    && str_contains($behaviorJs, 'Return in 7 days')
    && str_contains($behaviorJs, 'Why these projections')
    && str_contains($behaviorJs, 'Merchant CRM & Contacts')
    && str_contains($behaviorJs, 'Merchant Memory'),
    '12. Customer drawer exposes a Behavior tab with projections, evidence, and connected-system navigation'
);

$check(
    str_contains($behaviorJs, "card.dataset.visualMovement = 'presentation-only'")
    && str_contains($behaviorJs, "card.dataset.behaviorGuidance = 'server-profile'")
    && str_contains($behaviorJs, "mode === 'merchant_follow'")
    && str_contains($behaviorJs, "mode === 'campaign_interest'")
    && str_contains($behaviorJs, "mode === 'release'")
    && !str_contains($behaviorJs, 'localStorage')
    && !str_contains($behaviorJs, 'sessionStorage')
    && !str_contains($behaviorJs, 'MG.post(')
    && !str_contains($behaviorJs, '/api/merchant-canvas/send-message.php')
    && !str_contains($behaviorJs, '/api/merchant-canvas/send-reward.php')
    && !str_contains($behaviorJs, '/api/merchant-canvas/campaign-trigger.php'),
    '13. Greeting/follow/release movement is presentation-only and cannot perform customer-impacting writes'
);

$check(
    str_contains($behaviorCss, '.mg-canvas-behavior-probability-grid')
    && str_contains($behaviorCss, '.mg-canvas-behavior-evidence-list')
    && str_contains($behaviorCss, '.mg-canvas-behavior-action-grid')
    && str_contains($behaviorCss, '.mg-canvas-behavior-policy')
    && str_contains($behaviorCss, '[data-follow-state="release"]'),
    '14. Behavior, evidence, probability, policy, and visual-state styles exist'
);

if ($failures !== []) {
    fwrite(STDERR, "Store Canvas Header HUD & Predictive Behavior Memory v1 validation failed:\n");
    foreach ($failures as $failure) fwrite(STDERR, ' - ' . $failure . "\n");
    fwrite(STDERR, count($failures) . ' of ' . $checks . " checks failed.\n");
    exit(1);
}

fwrite(STDOUT, 'Store Canvas Header HUD & Predictive Behavior Memory v1 validation passed: ' . $checks . " checks.\n");
