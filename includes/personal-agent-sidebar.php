<?php
declare(strict_types=1);

require_once __DIR__ . '/agent-quick-actions.php';
require_once __DIR__ . '/multi-agent-workspace-data.php';

$user = mg_current_user();
$currentSidebarScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$agentSidebarMode = (string) ($agent_sidebar_mode ?? ($currentSidebarScript === 'merchant-agent-chat.php' ? 'merchant' : 'personal'));
$isMerchantAgentMode = $agentSidebarMode === 'merchant';
$isPersonalAgentMode = $agentSidebarMode === 'personal';
$suppressAgentSidebarFooter = !empty($suppress_agent_sidebar_footer);
$suppressAgentSidebarTools = !empty($suppress_agent_sidebar_tools);
$packageContext = $user ? mg_user_package_context(null, $user) : [];
$hasMerchantAgentAccess = $user && !empty($packageContext['merchant_access']);
$hasPersonalAgentAccess = $user && (!empty($packageContext['is_paid']) || !empty($packageContext['merchant_access']));
$personalAgentHref = $hasPersonalAgentAccess ? '/agent.php' : '/account-subscriptions.php?agent=personal';
$merchantAgentHref = $hasMerchantAgentAccess ? '/merchant-agent-chat.php' : '/account-subscriptions.php?agent=merchant';
$quickActionMode = $isMerchantAgentMode ? 'merchant' : 'personal';
$quickActions = mg_agent_quick_actions($quickActionMode);
$quickActionsEntitled = $isMerchantAgentMode ? $hasMerchantAgentAccess : $hasPersonalAgentAccess;
$activeSidebarKey = match ($currentSidebarScript) {
    'inbox.php' => 'inbox',
    'feed.php' => 'feed',
    'loyalty-cards.php' => 'loyalty-cards',
    'lists.php' => 'lists',
    'saves.php' => 'saves',
    'merchant-agent-chat.php' => 'merchant-agent',
    'agent.php' => ((string) ($agent_personal_view ?? 'home')) === 'design' ? 'design' : 'new-chat',
    'design-studio.php' => 'design',
    'design-calendar.php' => 'calendar',
    default => '',
};
$selectedSidebarAgentId = strtolower(trim((string) ($_GET['agent_id'] ?? '')));
$sidebarAgents = $user && $isPersonalAgentMode ? mg_multi_agent_active_agents($user) : [];
$sidebarTemplates = mg_multi_agent_templates();

$sidebarLinkClass = static function (string $key) use ($activeSidebarKey): string {
    return 'mg-personal-chat-action' . ($activeSidebarKey === $key ? ' is-active' : '');
};
?>
<aside class="mg-app-sidebar mg-universal-sidebar mg-utility-sidebar is-text-sidebar mg-personal-chat-sidebar"
       data-app-sidebar
       data-sidebar-variant="utility"
       data-personal-agent-chat-sidebar
       data-agent-sidebar-mode="<?= mg_e($agentSidebarMode) ?>">
  <div class="mg-app-sidebar-brand mg-universal-sidebar-brand">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
  </div>

  <?php if ($user): ?>
    <nav class="mg-personal-chat-actions" aria-label="Customer and Agent navigation">
      <a class="<?= mg_e($sidebarLinkClass('inbox')) ?>" href="/inbox.php"><span aria-hidden="true">▣</span><strong>Inbox</strong></a>
      <a class="<?= mg_e($sidebarLinkClass('feed')) ?>" href="/feed.php"><span aria-hidden="true">◈</span><strong>My Feed</strong></a>
      <a class="<?= mg_e($sidebarLinkClass('loyalty-cards')) ?>" href="/loyalty-cards.php"><span aria-hidden="true">◇</span><strong>My Loyalty Cards</strong></a>
      <a class="<?= mg_e($sidebarLinkClass('lists')) ?>" href="/lists.php"><span aria-hidden="true">☷</span><strong>My Lists</strong></a>
      <a class="<?= mg_e($sidebarLinkClass('saves')) ?>" href="/saves.php"><span aria-hidden="true">☆</span><strong>My Saves</strong></a>
      <?php if ($isMerchantAgentMode && $hasMerchantAgentAccess): ?>
        <button class="<?= mg_e($sidebarLinkClass('merchant-agent')) ?>" type="button" data-merchant-agent-new-chat><span aria-hidden="true">+</span><strong>New Merchant Chat</strong></button>
      <?php elseif ($isPersonalAgentMode && $hasPersonalAgentAccess): ?>
        <!-- Compatibility marker retained for existing chat-history contracts: data-personal-agent-new-chat -->
        <button class="<?= mg_e($sidebarLinkClass('new-chat')) ?>" type="button" data-open-agent-selector><span aria-hidden="true">+</span><strong>New Chat</strong></button>
      <?php else: ?>
        <a class="<?= mg_e($sidebarLinkClass('new-chat')) ?>" href="<?= mg_e($personalAgentHref) ?>"><span aria-hidden="true">+</span><strong>New Chat</strong></a>
      <?php endif; ?>
      <a class="<?= mg_e($sidebarLinkClass('design')) ?>" href="/design-studio.php"><span aria-hidden="true">✦</span><strong>Design</strong></a>
      <a class="<?= mg_e($sidebarLinkClass('calendar')) ?>" href="/design-calendar.php"><span aria-hidden="true">▦</span><strong>Calendar</strong></a>
    </nav>

    <div class="mg-personal-chat-divider" role="separator" aria-hidden="true"></div>

    <?php if ($isPersonalAgentMode && $hasPersonalAgentAccess): ?>
      <section class="mg-sidebar-agent-list" aria-label="My agents">
        <div class="mg-sidebar-agent-list-head"><span>My agents</span><button type="button" data-open-agent-selector aria-label="Add an agent">+</button></div>
        <article class="mg-sidebar-agent-row<?= $selectedSidebarAgentId === '' ? ' is-active' : '' ?>">
          <a class="mg-sidebar-agent-open" href="/agent.php"><span aria-hidden="true">✦</span><div><strong>Agent</strong><small>Default workspace</small></div></a>
        </article>
        <?php foreach ($sidebarAgents as $sidebarAgent): ?>
          <?php $config = is_array($sidebarAgent['config'] ?? null) ? $sidebarAgent['config'] : []; $template = $sidebarTemplates[(string) ($config['template_key'] ?? '')] ?? []; ?>
          <article class="mg-sidebar-agent-row<?= $selectedSidebarAgentId === (string) $sidebarAgent['id'] ? ' is-active' : '' ?><?= ($sidebarAgent['runtime_status'] ?? '') === 'paused' ? ' is-paused' : '' ?>">
            <a class="mg-sidebar-agent-open" href="/agent.php?agent_id=<?= rawurlencode((string) $sidebarAgent['id']) ?>" data-sidebar-agent-id="<?= mg_e((string) $sidebarAgent['id']) ?>"><span aria-hidden="true"><?= mg_e((string) ($template['icon'] ?? '✦')) ?></span><div><strong><?= mg_e((string) $sidebarAgent['name']) ?></strong><small><?= ($sidebarAgent['runtime_status'] ?? '') === 'paused' ? 'Paused' : 'Agent workspace' ?></small></div></a>
            <button class="mg-sidebar-agent-manage" type="button" data-sidebar-agent-manage="<?= mg_e((string) $sidebarAgent['id']) ?>" aria-label="Manage <?= mg_e((string) $sidebarAgent['name']) ?>">•••</button>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($isMerchantAgentMode && $hasMerchantAgentAccess): ?>
      <div class="mg-personal-chat-history" data-merchant-agent-thread-groups aria-live="polite"><div class="mg-personal-chat-loading">Loading merchant chats…</div></div>
    <?php elseif ($hasPersonalAgentAccess): ?>
      <div class="mg-personal-chat-history" data-personal-agent-thread-groups aria-live="polite"><div class="mg-personal-chat-loading">Loading chats…</div></div>
    <?php else: ?>
      <div class="mg-personal-chat-history" aria-live="polite"><div class="mg-personal-chat-empty">Choose a paid customer or merchant package to unlock Agent chats.</div></div>
    <?php endif; ?>

    <?php if (!$suppressAgentSidebarFooter): ?>
      <footer class="mg-personal-chat-sidebar-footer" data-agent-sidebar-footer-tools>
        <button class="mg-agent-footer-suggestions" type="button" data-agent-suggestions-open aria-haspopup="dialog" aria-controls="agent-sidebar-tools-modal"><span aria-hidden="true">✦</span><strong>Suggestions</strong><small>Ideas + keywords</small></button>
        <nav class="mg-agent-footer-mode-switch" aria-label="Agent mode" data-agent-footer-mode-switch>
          <a class="mg-agent-footer-mode<?= $isPersonalAgentMode ? ' is-active' : '' ?><?= !$hasPersonalAgentAccess ? ' is-locked' : '' ?>" href="<?= mg_e($personalAgentHref) ?>" data-agent-mode-link="personal"<?= $isPersonalAgentMode ? ' aria-current="page"' : '' ?>><span aria-hidden="true">P</span><strong>Personal</strong></a>
          <a class="mg-agent-footer-mode<?= $isMerchantAgentMode ? ' is-active' : '' ?><?= !$hasMerchantAgentAccess ? ' is-locked' : '' ?>" href="<?= mg_e($merchantAgentHref) ?>" data-agent-mode-link="merchant"<?= $isMerchantAgentMode ? ' aria-current="page"' : '' ?>><span aria-hidden="true">M</span><strong>Merchant</strong></a>
        </nav>
      </footer>
    <?php endif; ?>
  <?php else: ?>
    <div class="mg-personal-chat-empty-sidebar"><strong><?= $isMerchantAgentMode ? 'Merchant Agent' : 'Personal Agent' ?></strong><p>Sign in to create and manage private chats.</p></div>
  <?php endif; ?>
</aside>

<?php if ($user && !$suppressAgentSidebarTools): ?>
  <div class="mg-agent-sidebar-tools-modal" id="agent-sidebar-tools-modal" data-agent-sidebar-tools-modal data-agent-tools-mode="<?= mg_e($quickActionMode) ?>" data-agent-tools-entitled="<?= $quickActionsEntitled ? 'true' : 'false' ?>" data-agent-subscriptions-url="/account-subscriptions.php?agent=<?= mg_e($quickActionMode) ?>" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="agent-sidebar-tools-title">
    <div class="mg-agent-sidebar-tools-backdrop" data-agent-suggestions-close></div>
    <section class="mg-agent-sidebar-tools-dialog" role="document">
      <header class="mg-agent-sidebar-tools-head"><div><span><?= mg_e((string) $quickActions['title']) ?></span><h2 id="agent-sidebar-tools-title">Agent shortcuts</h2><p><?= mg_e((string) $quickActions['description']) ?></p></div><button type="button" data-agent-suggestions-close aria-label="Close Agent shortcuts">×</button></header>
      <div class="mg-agent-sidebar-tools-tabs" role="tablist" aria-label="Agent shortcuts sections"><button type="button" role="tab" id="agent-suggestions-tab" aria-selected="true" aria-controls="agent-suggestions-panel" data-agent-tools-tab="suggestions">Suggestions</button><button type="button" role="tab" id="agent-keywords-tab" aria-selected="false" aria-controls="agent-keywords-panel" data-agent-tools-tab="keywords">Quick keywords</button></div>
      <div class="mg-agent-sidebar-tools-body">
        <section id="agent-suggestions-panel" role="tabpanel" aria-labelledby="agent-suggestions-tab" data-agent-tools-panel="suggestions"><div class="mg-agent-suggestion-grid"><?php foreach ((array) $quickActions['suggestions'] as $suggestion): ?><button class="mg-agent-suggestion-card" type="button" data-agent-suggestion-prompt="<?= mg_e((string) $suggestion['prompt']) ?>" data-agent-target-mode="<?= mg_e($quickActionMode) ?>"><span class="mg-agent-suggestion-icon" aria-hidden="true"><?= mg_e((string) $suggestion['icon']) ?></span><span><strong><?= mg_e((string) $suggestion['label']) ?></strong><small><?= mg_e((string) $suggestion['detail']) ?></small></span><span class="mg-agent-suggestion-run" aria-hidden="true">→</span></button><?php endforeach; ?></div></section>
        <section id="agent-keywords-panel" role="tabpanel" aria-labelledby="agent-keywords-tab" data-agent-tools-panel="keywords" hidden><p class="mg-agent-keywords-intro">Select a keyword to place its current command or example in the chat box. Navigation keywords open their page directly.</p><div class="mg-agent-keyword-groups"><?php foreach ((array) $quickActions['keyword_groups'] as $group): ?><section class="mg-agent-keyword-group"><h3><?= mg_e((string) $group['label']) ?></h3><div class="mg-agent-keyword-list"><?php foreach ((array) $group['items'] as $item): ?><button type="button" data-agent-keyword-prompt="<?= mg_e((string) ($item['prompt'] ?? '')) ?>" <?php if (!empty($item['href'])): ?>data-agent-keyword-href="<?= mg_e((string) $item['href']) ?>"<?php endif; ?> data-agent-target-mode="<?= mg_e($quickActionMode) ?>"><strong><?= mg_e((string) $item['keyword']) ?></strong><small><?= mg_e((string) $item['detail']) ?></small></button><?php endforeach; ?></div></section><?php endforeach; ?></div></section>
      </div>
    </section>
  </div>
  <script src="/assets/js/agent-sidebar-tools.js?v=1.0.0&nav=1" defer></script>
<?php endif; ?>