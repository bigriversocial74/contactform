<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/ai/merchant-agent-credit-response.php';

// Golden Path compatibility markers:
// mg_has_permission('merchant.ai.plan')
// mg_has_permission('merchant.ai.review')

$user = mg_current_user();
$mg_package_context = $user ? mg_user_package_context(null, $user) : [];
$hasMerchantAccess = $user && !empty($mg_package_context['merchant_access']);
if ($user && !$hasMerchantAccess) {
    header('Location: /account-subscriptions.php?agent=merchant');
    exit;
}
$isMerchantOwner = $user && mg_merchant_agent_owner_context($mg_package_context, (int)$user['id']);
$merchantAgentAllowed = $hasMerchantAccess && $isMerchantOwner;
$merchantAgentAiStatus = $merchantAgentAllowed ? mg_merchant_agent_ai_status(mg_db(), $user, $mg_package_context) : [];

$page_title = 'Merchant Agent | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'agent';
$page_body_class = 'mg-integrated-merchant-agent-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/personal-gifting-agent.css',
    '/assets/css/personal-agent-chat-history.css?v=1.4.0',
    '/assets/css/personal-agent-sidebar-cleanup.css?v=1.0.0',
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
    '/assets/css/merchant-agent-snapshot.css?v=1.0.0',
    '/assets/css/merchant-agent-snapshot-action-center.css?v=1.0.0',
    '/assets/css/merchant-agent-automatic-snapshot.css?v=1.0.0',
    '/assets/css/merchant-agent-crm-mention-search.css?v=1.0.0',
    '/assets/css/merchant-agent-contact-action-center.css?v=1.0.0',
    '/assets/css/merchant-agent-contact-workspace-v1-1.css?v=1.1.0',
    '/assets/css/merchant-agent-personal-canvas-parity-v1.css?v=1.0.0',
    '/assets/css/agent-header-tabs-shared.css?v=1.0.0',
    '/assets/css/merchant-agent-ai-status.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/merchant-agent-crm-mention-search.js?v=1.1.0',
    '/assets/js/merchant-agent-contact-action-center.js?v=1.0.0',
    '/assets/js/merchant-agent-contact-action-center-select-bridge.js?v=1.0.0',
    '/assets/js/merchant-agent-contact-workspace-v1-1.js?v=1.1.0',
    '/assets/js/merchant-agent-ai-status.js?v=1.0.0',
    '/assets/js/merchant-agent-chat.js?v=2.4.0',
    '/assets/js/merchant-agent-automatic-snapshot.js?v=1.0.0',
    '/assets/js/merchant-agent-snapshot-action-center.js?v=1.0.0',
    '/assets/js/merchant-agent-chat-voice.js',
    '/assets/js/merchant-agent-chat-speech-results.js',
    '/assets/js/merchant-agent-chat-control-panel.js?v=1.1.0',
    '/assets/js/merchant-agent-memory-menu.js',
    '/assets/js/merchant-agent-creative-draft-actions.js',
    '/assets/js/merchant-agent-chat-scroll-latest.js',
    '/assets/js/merchant-agent-chat-json-format.js',
    '/assets/js/merchant-agent-chat-mobile.js?v=2.1.0',
    '/assets/js/merchant-agent-sidebar-history.js?v=2.1.0',
    '/assets/js/merchant-agent-chat-deep-links.js?v=1.0.0',
    '/assets/js/merchant-agent-handoff-receiver.js?v=1.0.0',
    '/assets/js/sponsored-campaign-card.js',
    '/assets/js/merchant-agent-chat-admin-mode.js',
];
$agent_sidebar_mode = 'merchant';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app mg-personal-agent-app mg-merchant-agent-integrated-app"
         data-agent-control-center
         data-merchant-agent-access="<?= $merchantAgentAllowed ? 'true' : 'false' ?>"
         data-merchant-agent-ai-can-generate="<?= !empty($merchantAgentAiStatus['can_generate']) ? 'true' : 'false' ?>"
         data-merchant-agent-ai-status="<?= mg_e((string)($merchantAgentAiStatus['key'] ?? 'unavailable')) ?>"
         data-merchant-agent-ai-message="<?= mg_e((string)($merchantAgentAiStatus['message'] ?? '')) ?>"
         data-merchant-agent-ai-manage-url="<?= mg_e((string)($merchantAgentAiStatus['manage_url'] ?? '/account-subscriptions.php?agent=merchant')) ?>"<?= $merchantAgentAllowed ? ' data-merchant-agent-chat' : '' ?>>
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-agent-workspace">
    <?php if (!$user): ?>
      <section class="mg-app-panel mg-personal-agent-access mg-merchant-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Merchant Agent</span><h1>Sign in to work with merchant campaigns, CRM, products, and analytics.</h1><p>Merchant conversations are stored separately from Personal Agent chats and remain scoped to your authorized business workspace.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php?return=%2Fmerchant-agent-chat.php">Sign in</a><a class="mg-btn mg-btn-ghost" href="/account-subscriptions.php">Review packages</a></div>
      </section>
    <?php elseif (!$isMerchantOwner): ?>
      <section class="mg-app-panel mg-personal-agent-access mg-merchant-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Merchant owner access</span><h1>This Merchant Agent workspace is reserved for the merchant owner.</h1><p>Team permissions, inherited access, and shared AI-credit pools are intentionally outside this merchant-owner build.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/merchant.php">Open Merchant Center</a><a class="mg-btn mg-btn-ghost" href="/agent.php">Return to Personal Agent</a></div>
      </section>
    <?php else: ?>
      <section class="mg-merchant-agent-ai-status is-<?= mg_e((string)$merchantAgentAiStatus['key']) ?>" data-merchant-agent-ai-status-banner role="status" aria-live="polite">
        <span class="mg-merchant-agent-ai-status-dot" aria-hidden="true"></span>
        <div><strong data-merchant-agent-ai-status-label><?= mg_e((string)$merchantAgentAiStatus['label']) ?></strong><p data-merchant-agent-ai-status-message><?= mg_e((string)$merchantAgentAiStatus['message']) ?></p></div>
        <span class="mg-merchant-agent-systematic-note">Database and systematic tools remain available.</span>
        <a href="<?= mg_e((string)$merchantAgentAiStatus['manage_url']) ?>" data-merchant-agent-ai-manage<?= !empty($merchantAgentAiStatus['can_generate']) ? ' hidden' : '' ?>>Manage AI access</a>
      </section>
      <section class="mg-merchant-latest-snapshot is-loading" data-merchant-agent-latest-snapshot aria-live="polite"><p>Preparing your latest merchant snapshot…</p></section>
      <?php require __DIR__ . '/includes/merchant-agent-chat-view.php'; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
