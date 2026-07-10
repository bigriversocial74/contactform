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

$page = $read('merchant-campaigns.php');
$sql = $read('database/predictive_campaign_studio_foundation_v1.sql');
$helper = $read('api/merchant/_predictive_campaign_studio.php');
$endpoint = $read('api/merchant/predictive-campaign-studio.php');
$js = $read('assets/js/predictive-campaign-studio.js');
$css = $read('assets/css/predictive-campaign-studio.css');

$check(
    str_contains($page, '/assets/css/predictive-campaign-studio.css')
    && str_contains($page, '/assets/js/predictive-campaign-studio.js'),
    '1. Merchant Campaigns loads the Predictive Campaign Studio assets'
);

$check(
    str_contains($sql, 'CREATE TABLE IF NOT EXISTS mg_predictive_campaign_recommendations')
    && str_contains($sql, 'reward_template_id BIGINT UNSIGNED NULL')
    && str_contains($sql, 'campaign_id BIGINT UNSIGNED NULL')
    && str_contains($sql, 'FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id)')
    && str_contains($sql, 'FOREIGN KEY (campaign_id) REFERENCES campaigns(id)'),
    '2. The new schema stores recommendation intelligence and references canonical rewards/campaigns'
);

$check(
    substr_count($sql, 'CREATE TABLE') === 1
    && !str_contains($sql, 'CREATE TABLE IF NOT EXISTS predictive_rewards')
    && !str_contains($sql, 'CREATE TABLE IF NOT EXISTS predictive_campaigns'),
    '3. No parallel reward or campaign dataset is created'
);

$check(
    str_contains($helper, "mg_predictive_campaign_table_exists($pdo, 'mg_merchant_customer_behavior_profiles')")
    && str_contains($helper, "mg_predictive_campaign_table_exists($pdo, 'wallet_items')")
    && str_contains($helper, "mg_predictive_campaign_table_exists($pdo, 'campaigns')")
    && str_contains($helper, "mg_predictive_campaign_table_exists($pdo, 'reward_templates')")
    && str_contains($helper, "mg_predictive_campaign_table_exists($pdo, 'mg_store_sessions')"),
    '4. Recommendations derive from current behavior, Wallet, Campaigns, Rewards, and Store Canvas history'
);

$check(
    str_contains($helper, "'store_trend'")
    && str_contains($helper, "'customer_segment'")
    && str_contains($helper, "'individual_customer_targeting_enabled' => false")
    && !str_contains($helper, "'individual_customer_targeting_enabled' => true"),
    '5. Strategy is store/segment based and individual targeting remains disabled'
);

$check(
    str_contains($helper, "WHERE merchant_user_id=? AND status='active'")
    && str_contains($helper, "'reuse_existing'")
    && str_contains($helper, "'create_draft'"),
    '6. Recommendations reuse current active Reward Inventory or propose a draft reward'
);

$check(
    str_contains($helper, 'INSERT INTO reward_templates')
    && str_contains($helper, 'INSERT INTO campaigns')
    && str_contains($helper, "'draft'")
    && str_contains($helper, "'automatic_launch' => false"),
    '7. Merchant approval materializes canonical reward and campaign records as drafts only'
);

$check(
    !str_contains($helper, 'INSERT INTO wallet_items')
    && !str_contains($helper, 'INSERT INTO campaign_contacts')
    && !str_contains($helper, 'INSERT INTO campaign_events')
    && !str_contains($helper, 'mg_store_reward_issue')
    && !str_contains($helper, 'mg_store_send_direct_message_via_messaging'),
    '8. Predictive materialization cannot issue Wallet items, contacts, events, rewards, or messages'
);

$check(
    str_contains($helper, "'merchant_approval_required' => true")
    && str_contains($helper, "'automatic_message' => false")
    && str_contains($helper, "'automatic_reward_issue' => false")
    && str_contains($helper, "'materializes_draft_campaigns_only' => true"),
    '9. The server payload explicitly exposes the approval and authority boundaries'
);

$check(
    str_contains($endpoint, "mg_merchant_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage')")
    && str_contains($endpoint, "mg_rate_limit('merchant.predictive_campaign_studio.read'")
    && str_contains($endpoint, "mg_rate_limit('merchant.predictive_campaign_studio.write'")
    && str_contains($endpoint, 'mg_require_csrf_for_write($input)'),
    '10. The merchant endpoint is authenticated, permissioned, rate-limited, and CSRF protected'
);

$check(
    str_contains($endpoint, "$action === 'generate'")
    && str_contains($endpoint, "$action === 'materialize'")
    && str_contains($endpoint, "$action === 'dismiss'")
    && str_contains($endpoint, 'mg_predictive_campaign_materialize'),
    '11. The endpoint supports generate, merchant-approved materialize, and dismiss actions'
);

$check(
    str_contains($js, "link.textContent = 'Recommendations'")
    && str_contains($js, "panel.id = 'campaign-recommendations'")
    && str_contains($js, '/api/merchant/predictive-campaign-studio.php')
    && str_contains($js, 'Generate recommendations'),
    '12. Campaigns receives a working Recommendations tab and API-backed workspace'
);

$check(
    str_contains($js, 'Create reward + campaign drafts')
    && str_contains($js, 'Nothing will launch or be issued automatically')
    && str_contains($js, 'Individual reward creation is disabled')
    && str_contains($js, 'Current rewards and campaigns remain authoritative'),
    '13. The UI communicates draft-only creation, canonical authority, and targeting boundaries'
);

$check(
    str_contains($css, '.mg-predictive-studio')
    && str_contains($css, '.mg-predictive-kpis')
    && str_contains($css, '.mg-predictive-card')
    && str_contains($css, '.mg-predictive-projections')
    && str_contains($css, '@media(max-width:640px)'),
    '14. The Recommendations workspace has responsive production styles'
);

$check(
    str_contains($helper, "'comeback_reactivation'")
    && str_contains($helper, "'loyalty_milestone'")
    && str_contains($helper, "'post_redemption_feedback'")
    && str_contains($helper, "'unclaimed_reward_recovery'")
    && str_contains($helper, "'product_interest_followup'")
    && str_contains($helper, "'new_customer_welcome'"),
    '15. The foundation includes the initial store/customer trend recommendation families'
);

if ($failures !== []) {
    fwrite(STDERR, "Predictive Campaign Studio Foundation v1 validation failed:\n");
    foreach ($failures as $failure) fwrite(STDERR, ' - ' . $failure . "\n");
    fwrite(STDERR, count($failures) . ' of ' . $checks . " checks failed.\n");
    exit(1);
}

fwrite(STDOUT, 'Predictive Campaign Studio Foundation v1 validation passed: ' . $checks . " checks.\n");
