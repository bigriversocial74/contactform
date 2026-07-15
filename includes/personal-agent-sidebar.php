<?php
declare(strict_types=1);

$user = mg_current_user();
?>
<aside class="mg-app-sidebar mg-universal-sidebar mg-utility-sidebar is-text-sidebar mg-personal-chat-sidebar" data-app-sidebar data-sidebar-variant="utility" data-personal-agent-chat-sidebar>
  <div class="mg-app-sidebar-brand mg-universal-sidebar-brand">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
    <span class="mg-universal-sidebar-label">Agent</span>
  </div>

  <?php if ($user): ?>
    <nav class="mg-personal-chat-actions" aria-label="Personal Agent actions">
      <button class="mg-personal-chat-action" type="button" data-personal-agent-new-chat>
        <span aria-hidden="true">+</span><strong>New chat</strong>
      </button>
      <a class="mg-personal-chat-action" href="/design-studio.php">
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
