<?php
declare(strict_types=1);

$user = mg_current_user();
$currentSidebarScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$activeSidebarKey = match ($currentSidebarScript) {
    'inbox.php' => 'inbox',
    'feed.php' => 'feed',
    'loyalty-cards.php' => 'loyalty-cards',
    'lists.php' => 'lists',
    'saves.php' => 'saves',
    'agent.php' => ((string) ($agent_personal_view ?? 'home')) === 'design' ? 'design' : 'new-chat',
    'design-studio.php' => 'design',
    default => '',
};

$sidebarLinkClass = static function (string $key) use ($activeSidebarKey): string {
    return 'mg-personal-chat-action' . ($activeSidebarKey === $key ? ' is-active' : '');
};
?>
<aside class="mg-app-sidebar mg-universal-sidebar mg-utility-sidebar is-text-sidebar mg-personal-chat-sidebar" data-app-sidebar data-sidebar-variant="utility" data-personal-agent-chat-sidebar>
  <div class="mg-app-sidebar-brand mg-universal-sidebar-brand">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
  </div>

  <?php if ($user): ?>
    <nav class="mg-personal-chat-actions" aria-label="Customer and Personal Agent navigation">
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
      <button class="<?= mg_e($sidebarLinkClass('new-chat')) ?>" type="button" data-personal-agent-new-chat>
        <span aria-hidden="true">+</span><strong>New Chat</strong>
      </button>
      <a class="<?= mg_e($sidebarLinkClass('design')) ?>" href="/design-studio.php">
        <span aria-hidden="true">✦</span><strong>Design</strong>
      </a>
    </nav>

    <div class="mg-personal-chat-divider" role="separator" aria-hidden="true"></div>

    <div class="mg-personal-chat-history" data-personal-agent-thread-groups aria-live="polite">
      <div class="mg-personal-chat-loading">Loading chats…</div>
    </div>

    <footer class="mg-personal-chat-sidebar-footer">
      <span>Private to your account</span>
      <small>Chat titles and dates are generated from your conversation history.</small>
    </footer>
  <?php else: ?>
    <div class="mg-personal-chat-empty-sidebar"><strong>Personal Agent</strong><p>Sign in to create and manage private chats.</p></div>
  <?php endif; ?>
</aside>