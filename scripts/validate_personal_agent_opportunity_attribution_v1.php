<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;
$read = static function(string $path) use ($root): string {
    $full = $root . '/' . ltrim($path,'/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read: ' . $path);
    return $content;
};
$expect = static function(bool $condition, string $label) use (&$failures,&$passes): void {
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if ($condition) $passes++; else $failures[] = $label;
};

try {
    $migration = $read('database/stage_18ak_personal_agent_opportunity_attribution_v1.sql');
    $service = $read('includes/personal-agent/opportunity-attribution.php');
    $response = $read('includes/personal-agent/opportunity-response.php');
    $bootstrap = $read('includes/personal-gifting-agent.php');
    $credit = $read('includes/personal-agent/credit-response.php');
    $recoveryResponse = is_file($root . '/includes/personal-agent/recovery-response.php') ? $read('includes/personal-agent/recovery-response.php') : '';
    $actionsApi = $read('api/user-agent/opportunity-action.php');
    $listApi = $read('api/user-agent/opportunities.php');
    $merchantApi = $read('api/merchant/personal-agent-attribution.php');
    $cartItems = $read('api/commerce/cart-items.php');
    $checkout = $read('api/commerce/cart-checkout.php');
    $agentPage = $read('agent.php');
    $listsPage = $read('lists.php');
    $savesPage = $read('saves.php');
    $sidebar = $read('includes/personal-agent-sidebar.php');
    $successPage = $read('checkout-success.php');
    $footer = $read('includes/footer.php');
    $actionsJs = $read('assets/js/personal-agent-opportunity-actions.js');
    $runtimeJs = $read('assets/js/personal-agent-attribution-runtime.js');
    $savedJs = $read('assets/js/saved-opportunities.js');
    $commerceJs = $read('assets/js/customer-commerce.js');
    $merchantJs = $read('assets/js/merchant-agent-roi.js');
    $merchantView = $read('includes/merchant-agent-roi-view.php');
    $css = $read('assets/css/personal-agent-opportunity-actions.css');
    $savesCss = $read('assets/css/personal-agent-my-saves.css');

    foreach (['personal_agent_opportunities','personal_agent_opportunity_events','attribution_token','merchant_user_id','order_public_id','stage_18ak_personal_agent_opportunity_attribution_v1'] as $marker) {
        $expect(str_contains($migration,$marker),'Migration contains ' . $marker);
    }
    foreach (['mg_personal_agent_opportunity_upsert','mg_personal_agent_opportunity_event','mg_personal_agent_opportunity_change_state','mg_personal_agent_opportunity_list','mg_personal_agent_opportunity_merchant_analytics'] as $marker) {
        $expect(str_contains($service,$marker),'Opportunity service contains ' . $marker);
    }
    $expect(str_contains($response,'mg_personal_agent_chat_with_opportunity_attribution') && str_contains($response,'recommendation_created') && str_contains($response,"'buy_self'") && str_contains($response,"'send_gift'") && str_contains($response,"'join_campaign'"),'Marketplace responses create attributed action cards');
    $runtimeCallsAttribution = str_contains($credit,'mg_personal_agent_chat_with_opportunity_attribution')
        || (str_contains($credit,'mg_personal_agent_chat_with_recovery_response') && str_contains($recoveryResponse,'mg_personal_agent_chat_with_opportunity_attribution'));
    $expect(str_contains($bootstrap,"opportunity-attribution.php") && str_contains($bootstrap,"opportunity-response.php") && $runtimeCallsAttribution,'Personal Agent runtime loads attribution decorator directly or through recovery wrapper');
    $expect(str_contains($actionsApi,"mg_require_csrf_for_write") && str_contains($actionsApi,"purchase_completed") && str_contains($actionsApi,"campaign_join_completed"),'Opportunity action endpoint is protected and records conversions');
    $expect(
        str_contains($listApi,'mg_personal_agent_opportunity_list')
        && str_contains($listsPage,'href="/saves.php"')
        && !str_contains($listsPage,'data-saved-opportunities')
        && str_contains($savesPage,'data-saved-opportunities')
        && str_contains($savesPage,'data-saved-opportunity-filter="product"')
        && str_contains($savedJs,'/api/user-agent/opportunities.php')
        && str_contains($savedJs,"action:'unsave'")
        && str_contains($savedJs,'>Unsave</button>'),
        'My Saves separates saved recommendations from contact lists and supports unsave'
    );
    $expect(str_contains($sidebar,"'saves.php' => 'saves'") && str_contains($sidebar,'href="/saves.php"') && str_contains($sidebar,'My Saves'),'Customer sidebar exposes a dedicated My Saves tab');
    $expect(str_contains($savesCss,'.mg-user-lists-hero-actions') && str_contains($savesCss,'.mg-saves-filter-tabs') && str_contains($savesCss,'button.is-danger'),'My Saves controls and unsave action are responsive and clearly styled');
    $expect(str_contains($cartItems,'cart_added') && str_contains($checkout,'checkout_started') && str_contains($commerceJs,'agent_attribution_token'),'Cart and checkout carry Personal Agent attribution');
    $expect(str_contains($successPage,'personal-agent-attribution-runtime.js') && str_contains($runtimeJs,'purchase_completed') && str_contains($runtimeJs,'campaign_join_completed'),'Global runtime records purchase and campaign outcomes');
    $expect(str_contains($footer,'personal-agent-attribution-runtime.js?v=1.0.0'),'Signed-in pages load the attribution runtime');
    $expect(preg_match("/personal-agent-opportunity-actions\\.js\\?v=1\\.[0-9]+\\.0/",$agentPage) === 1 && str_contains($agentPage,'personal-agent-opportunity-actions.css?v=1.0.0'),'Agent page loads versioned opportunity action UI');
    foreach (['Buy for myself','Send as a gift','Join campaign','Save','Hide'] as $label) {
        $expect(str_contains($response,$label),'Opportunity cards include ' . $label);
    }
    $expect(str_contains($actionsJs,"action === 'save'") && str_contains($actionsJs,"action === 'hide'") && !str_contains($actionsJs,'window.confirm('),'Opportunity UI uses inline save and hide actions');
    $expect(str_contains($merchantApi,'mg_personal_agent_opportunity_merchant_analytics') && str_contains($merchantView,'Personal Agent Opportunity Funnel') && str_contains($merchantJs,'personal-agent-attribution.php'),'Merchant ROI displays Personal Agent opportunity attribution');
    $expect(str_contains($css,'.mg-agent-opportunity-button') && str_contains($css,'.mg-saved-opportunities') && str_contains($css,'@media(max-width:760px)'),'Opportunity actions and saved list UI are responsive');
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo '[FAIL] ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR,sprintf("\nPersonal Agent opportunity attribution validation failed: %d failure(s), %d pass(es).\n",count($failures),$passes));
    foreach ($failures as $failure) fwrite(STDERR,' - ' . $failure . PHP_EOL);
    exit(1);
}
echo sprintf("\nPersonal Agent opportunity attribution validation passed: %d checks.\n",$passes);