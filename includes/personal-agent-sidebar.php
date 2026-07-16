<?php
declare(strict_types=1);

$user = mg_current_user();
$currentSidebarScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$agentSidebarMode = (string) ($agent_sidebar_mode ?? ($currentSidebarScript === 'merchant-agent-chat.php' ? 'merchant' : 'personal'));
$isMerchantAgentMode = $agentSidebarMode === 'merchant';
$packageContext = $user ? mg_user_package_context(null, $user) : [];
$hasMerchantAgentAccess = $user && !empty($packageContext['merchant_access']);
$activeSidebarKey = match ($currentSidebarScript) {
    'inbox.php' => 'inbox',
    'feed.php' => 'feed',
    'loyalty-cards.php' => 'loyalty-cards',
    'lists.php' => 'lists',
    'saves.php' => 'saves',
    'merchant-agent-chat.php' => 'merchant-agent',
    'agent.php' => ((string) ($agent_personal_view ?? 'home')) === 'design' ? 'design' : 'new-chat',
    'design-studio.php' => 'design',
    default => '',
};

$sidebarLinkClass = static function (string $key) use ($activeSidebarKey): string {
    return 'mg-personal-chat-action' . ($activeSidebarKey === $key ? ' is-active' : '');
};
?>
<aside class="mg-app-sidebar mg-universal-sidebar mg-utility-sidebar is-text-sidebar mg-personal-chat-sidebar"
       data-app-sidebar
       data-sidebar-variant="utility"
       data-personal-agent-chat-sidebar
       data-agent-sidebar-mode="<?= $isMerchantAgentMode ? 'merchant' : 'personal' ?>">
  <div class="mg-app-sidebar-brand mg-universal-sidebar-brand">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
  </div>

  <?php if ($user): ?>
    <section class="mg-agent-mode-switch" aria-label="Agent mode">
      <div class="mg-agent-mode-switch-head">
        <span>Agent mode</span>
        <small>Separate conversations and data boundaries</small>
      </div>
      <div class="mg-agent-mode-options">
        <a class="mg-agent-mode-option<?= !$isMerchantAgentMode ? ' is-active' : '' ?>" href="/agent.php"<?= !$isMerchantAgentMode ? ' aria-current="page"' : '' ?>>
          <span class="mg-agent-mode-icon" aria-hidden="true">P</span>
          <span class="mg-agent-mode-copy"><strong>Personal Agent</strong><small>Contacts, gifting, saves, and planning</small></span>
        </a>
        <a class="mg-agent-mode-option<?= $isMerchantAgentMode ? ' is-active' : '' ?><?= !$hasMerchantAgentAccess ? ' is-locked' : '' ?>"
           href="<?= $hasMerchantAgentAccess ? '/merchant-agent-chat.php' : '/account-subscriptions.php' ?>"<?= $isMerchantAgentMode ? ' aria-current="page"' : '' ?>>
          <span class="mg-agent-mode-icon" aria-hidden="true">M</span>
          <span class="mg-agent-mode-copy"><strong>Merchant Agent</strong><small><?= $hasMerchantAgentAccess ? 'Campaigns, CRM, products, and analytics' : 'Merchant access required' ?></small></span>
          <?php if (!$isMerchantAgentMode): ?><kbd>/m</kbd><?php endif; ?>
        </a>
      </div>
    </section>

    <nav class="mg-personal-chat-actions" aria-label="Customer and Agent navigation">
      <a class="<?= mg_e($sidebarLinkClass('inbox')) ?>" href="/inbox.php">
        <span aria-hidden="true">▣</span><strong>Inbox</strong>
      </a>
      <a class="<?= mg_e($sidebarLinkClass('feed')) ?>" href="/feed.php">
        <span aria-hidden="true">◈</span><strong>My Feed</strong>
      </a>
      <a class="<?= mg_e($sidebarLinkClass('loyalty-cards')) ?>" href="/loyalty-cards.php">
        <span aria-hidden="true">◇</span><strong>My Loyalty Cards</strong>
      </a>
      <a class="<?= mg_e($sidebarLinkClass('lists')) ?>" href="/lists.php">
        <span aria-hidden="true">☷</span><strong>My Lists</strong>
      </a>
      <a class="<?= mg_e($sidebarLinkClass('saves')) ?>" href="/saves.php">
        <span aria-hidden="true">☆</span><strong>My Saves</strong>
      </a>
      <?php if ($isMerchantAgentMode): ?>
        <button class="<?= mg_e($sidebarLinkClass('merchant-agent')) ?>" type="button" data-merchant-agent-new-chat>
          <span aria-hidden="true">+</span><strong>New Merchant Chat</strong>
        </button>
      <?php else: ?>
        <button class="<?= mg_e($sidebarLinkClass('new-chat')) ?>" type="button" data-personal-agent-new-chat>
          <span aria-hidden="true">+</span><strong>New Chat</strong>
        </button>
      <?php endif; ?>
      <a class="<?= mg_e($sidebarLinkClass('design')) ?>" href="/design-studio.php">
        <span aria-hidden="true">✦</span><strong>Design</strong>
      </a>
    </nav>

    <div class="mg-personal-chat-divider" role="separator" aria-hidden="true"></div>

    <div class="mg-personal-chat-history-label">
      <span><?= $isMerchantAgentMode ? 'Merchant chats' : 'Personal chats' ?></span>
      <span><?= $isMerchantAgentMode ? 'Business data only' : 'Private' ?></span>
    </div>

    <?php if ($isMerchantAgentMode): ?>
      <div class="mg-personal-chat-history" data-merchant-agent-thread-groups aria-live="polite">
        <div class="mg-personal-chat-loading">Loading merchant chats…</div>
      </div>
    <?php else: ?>
      <div class="mg-personal-chat-history" data-personal-agent-thread-groups aria-live="polite">
        <div class="mg-personal-chat-loading">Loading chats…</div>
      </div>
    <?php endif; ?>

    <footer class="mg-personal-chat-sidebar-footer">
      <?php if ($isMerchantAgentMode): ?>
        <span>Scoped to your merchant workspace</span>
        <small>Merchant requests use permission-checked business data and approval-first actions.</small>
      <?php else: ?>
        <span>Private to your account</span>
        <small>Chat titles and dates are generated from your personal conversation history.</small>
      <?php endif; ?>
    </footer>
  <?php else: ?>
    <div class="mg-personal-chat-empty-sidebar"><strong><?= $isMerchantAgentMode ? 'Merchant Agent' : 'Personal Agent' ?></strong><p>Sign in to create and manage private chats.</p></div>
  <?php endif; ?>
</aside>
