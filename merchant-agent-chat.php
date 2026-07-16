<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Merchant Agent | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'agent';
$page_body_class = 'mg-integrated-merchant-agent-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/personal-gifting-agent.css',
    '/assets/css/personal-agent-chat-history.css?v=1.2.0',
    '/assets/css/agent-mode-switch.css?v=1.0.0',
    '/assets/css/merchant-agent-chat.css',
    '/assets/css/merchant-agent-chat-followup.css',
    '/assets/css/merchant-agent-chat-skills.css',
    '/assets/css/merchant-agent-chat-voice.css',
    '/assets/css/merchant-agent-chat-control-panel.css',
    '/assets/css/merchant-agent-memory-menu.css',
    '/assets/css/merchant-agent-creative-draft-actions.css',
    '/assets/css/merchant-agent-chat-recipe-cards.css',
    '/assets/css/sponsored-campaign-card.css',
    '/assets/css/merchant-agent-integrated-workspace.css?v=1.0.0',
    '/assets/css/merchant-agent-integrated-compat.css?v=1.0.0',
    '/assets/css/agent-header-tabs-shared.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/merchant-agent-chat.js?v=2.2.0',
    '/assets/js/merchant-agent-chat-voice.js',
    '/assets/js/merchant-agent-chat-speech-results.js',
    '/assets/js/merchant-agent-chat-control-panel.js?v=1.1.0',
    '/assets/js/merchant-agent-memory-menu.js',
    '/assets/js/merchant-agent-creative-draft-actions.js',
    '/assets/js/merchant-agent-chat-scroll-latest.js',
    '/assets/js/merchant-agent-chat-json-format.js',
    '/assets/js/merchant-agent-chat-mobile.js?v=2.1.0',
    '/assets/js/merchant-agent-sidebar-history.js?v=1.0.0',
    '/assets/js/merchant-agent-handoff-receiver.js?v=1.0.0',
    '/assets/js/sponsored-campaign-card.js',
    '/assets/js/merchant-agent-chat-admin-mode.js',
];

$user = mg_current_user();
$mg_package_context = $user ? mg_user_package_context(null, $user) : [];
$hasMerchantAccess = $user && !empty($mg_package_context['merchant_access']);
$hasMerchantPlanPermission = $user && (
    (function_exists('mg_has_permission') && mg_has_permission('merchant.ai.plan'))
    || (function_exists('mg_workspace_role_allows_permission') && mg_workspace_role_allows_permission($mg_package_context, 'merchant.ai.plan'))
);
$hasMerchantReviewPermission = $user && (
    (function_exists('mg_has_permission') && mg_has_permission('merchant.ai.review'))
    || (function_exists('mg_workspace_role_allows_permission') && mg_workspace_role_allows_permission($mg_package_context, 'merchant.ai.review'))
);
$merchantAgentAllowed = $hasMerchantAccess && $hasMerchantPlanPermission && $hasMerchantReviewPermission;
$agent_sidebar_mode = 'merchant';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-personal-agent-app mg-merchant-agent-integrated-app"
         data-agent-control-center
         data-merchant-agent-access="<?= $merchantAgentAllowed ? 'true' : 'false' ?>"<?= $merchantAgentAllowed ? ' data-merchant-agent-chat' : '' ?>>
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-agent-workspace">
    <?php if (!$user): ?>
      <section class="mg-app-panel mg-personal-agent-access mg-merchant-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Merchant Agent</span><h1>Sign in to work with merchant campaigns, CRM, products, and analytics.</h1><p>Merchant conversations are stored separately from Personal Agent chats and remain scoped to your authorized business workspace.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php?return=%2Fmerchant-agent-chat.php">Sign in</a><a class="mg-btn mg-btn-ghost" href="/agent.php">Open Personal Agent</a></div>
      </section>
    <?php elseif (!$hasMerchantAccess): ?>
      <section class="mg-app-panel mg-personal-agent-access mg-merchant-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Merchant access required</span><h1>Merchant Agent is available to merchant accounts and assigned merchant team members.</h1><p>Your Personal Agent remains available. Add a merchant package or join an authorized merchant workspace to use business data and merchant tools.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/account-subscriptions.php">Review merchant packages</a><a class="mg-btn mg-btn-ghost" href="/agent.php">Return to Personal Agent</a></div>
      </section>
    <?php elseif (!$merchantAgentAllowed): ?>
      <section class="mg-app-panel mg-personal-agent-access mg-merchant-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Merchant AI permission required</span><h1>Your merchant workspace is active, but Merchant Agent permissions are not enabled.</h1><p>Enable <code>merchant.ai.plan</code> and <code>merchant.ai.review</code> for this account or workspace role. The API will continue to enforce these permissions on every request.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/merchant.php">Open Merchant Center</a><a class="mg-btn mg-btn-ghost" href="/agent.php">Return to Personal Agent</a></div>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/merchant-agent-chat-view.php'; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
