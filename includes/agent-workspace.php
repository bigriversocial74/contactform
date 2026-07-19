<?php
declare(strict_types=1);

require_once __DIR__ . '/personal-gifting-agent.php';

$user = mg_current_user();
$displayName = $user ? mg_user_display_name() : 'Guest';
$activeView = (string) ($agent_personal_view ?? 'home');
$packageContext = $user ? mg_user_package_context(null, $user) : [];
$merchantAgentAccess = $user && !empty($packageContext['merchant_access']);
$schemaReady = false;
$phase3SchemaReady = false;
if ($user) {
    try {
        $pdo = mg_db();
        $schemaReady = mg_personal_agent_table_exists($pdo, 'user_agent_settings')
            && mg_personal_agent_table_exists($pdo, 'user_gifting_plans')
            && mg_personal_agent_table_exists($pdo, 'user_agent_messages');
        $phase3SchemaReady = $schemaReady && mg_personal_agent_table_exists($pdo, 'user_gifting_schedules')
            && mg_personal_agent_table_exists($pdo, 'user_recipient_data_requests')
            && mg_personal_agent_table_exists($pdo, 'user_gift_bundles');
    } catch (Throwable) {
        $schemaReady = false;
        $phase3SchemaReady = false;
    }
}
?>
<section class="mg-app-shell mg-agent-app mg-personal-agent-app"
         data-agent-control-center
         data-personal-gifting-agent
         data-active-view="<?= mg_e($activeView) ?>"
         data-display-name="<?= mg_e($displayName) ?>"
         data-merchant-agent-access="<?= $merchantAgentAccess ? 'true' : 'false' ?>"
         data-schema-ready="<?= $schemaReady ? 'true' : 'false' ?>"
         data-phase3-schema-ready="<?= $phase3SchemaReady ? 'true' : 'false' ?>">
  <?php require __DIR__ . '/personal-agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-agent-workspace">
    <?php if (!$user): ?>
      <section class="mg-app-panel mg-personal-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Personal Gifting Agent</span><h1>Sign in to plan gifts around the people who matter.</h1><p>Your lists, dates, draft plans, reminders, and Agent Memory stay connected to your account.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-ghost" href="/signup.php">Create account</a></div>
      </section>
    <?php elseif (!$schemaReady): ?>
      <section class="mg-app-panel mg-personal-agent-access">
        <div class="mg-app-panel-head"><div><span class="mg-agent-toolbar-eyebrow">Database setup required</span><h1>Personal Gifting Agent Phase 2 is ready for migration.</h1><p>Import <code>database/20260714_personal_gifting_agent_phase2.sql</code> after the Phase 1 contact-list migration.</p></div></div>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/personal-agent/workspace-dashboard.php'; ?>
      <?php require __DIR__ . '/personal-agent/multi-agent-workspace.php'; ?>

      <form class="mg-app-composer mg-personal-agent-composer" data-agent-composer data-personal-agent-composer>
        <button class="mg-personal-agent-context-chip" type="button" data-personal-agent-context-chip hidden aria-label="Clear selected gifting context"></button>
        <div class="mg-personal-agent-credit-bar" aria-live="polite">
          <span class="mg-personal-agent-credit-chip" data-personal-agent-credit-chip><strong>Loading AI credits…</strong></span>
          <span class="mg-personal-agent-credit-detail" data-personal-agent-credit-detail></span>
          <a class="mg-personal-agent-credit-manage" href="/account-subscriptions.php">Manage package</a>
        </div>
        <div class="mg-personal-agent-composer-row">
          <button class="mg-personal-agent-menu-trigger" type="button" data-open-agent-dialog="menu" aria-label="Open Personal Agent menu" title="Open Agent menu">+</button>
          <textarea rows="1" maxlength="2000" placeholder="Ask about contacts, gifts, saves, or type /m followed by a merchant request…" aria-label="Message the Personal Gifting Agent"></textarea>
          <button class="mg-btn mg-btn-primary" type="submit">Send</button>
        </div>
      </form>

      <?php require __DIR__ . '/personal-agent/workspace-dialogs.php'; ?>
      <?php require __DIR__ . '/personal-agent/workspace-workflow-dialogs.php'; ?>
    <?php endif; ?>
  </div>
</section>
