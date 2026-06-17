<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Demo Microgifts | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_scripts = ['/assets/js/account.js','/assets/js/admin-demo-microgifts.js'];
$user = mg_current_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$canManageDemo = $user && (in_array('super_admin', $roles, true) || in_array('admin.users.view', $permissions, true));
if (!$canManageDemo) {
  http_response_code($user ? 403 : 401);
}
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <aside class="mg-app-sidebar mg-account-left">
    <div class="mg-app-sidebar-brand">
      <a class="mg-brand" href="/index.php" aria-label="Microgifter home"><span class="mg-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M13 2 4 14h7l-1 8 10-13h-7l1-7Z" fill="currentColor"/></svg></span><span>Microgifter</span></a>
    </div>
    <nav class="mg-app-side-nav mg-account-nav" aria-label="Account pages">
      <a href="/account.php"><strong>Account</strong><span>Profile and access</span></a>
      <a class="is-active" href="/account-demo-microgifts.php"><strong>Demo Microgifts</strong><span>Sandbox Action Center records</span></a>
      <a href="/account-admin.php"><strong>Admin</strong><span>Platform controls</span></a>
    </nav>
  </aside>

  <main class="mg-app-workspace mg-account-main">
    <?php if (!$canManageDemo): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Demo Microgifts</h2><p>Admin access is required.</p></div></div></section>
    <?php else: ?>
      <section class="mg-app-panel" data-admin-demo-microgifts>
        <div class="mg-app-panel-head mg-section-head">
          <div>
            <h2>Demo Microgifts</h2>
            <p>Seed database-backed sandbox Microgifts that appear in Action Center and exercise the normal PPPM lifecycle without using frontend-only fake rows.</p>
          </div>
          <span class="mg-score-pill" data-demo-status-pill>Loading</span>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-account-section">
            <h3>Sandbox controls</h3>
            <p class="mg-muted">Enable creates a real demo Microgift instance owned by this admin session and projects it through the normal Action Center read API. Reset archives old demo rows, cancels active demo instances, and creates a fresh one.</p>
            <div class="mg-action-row">
              <button class="mg-btn mg-btn-primary" type="button" data-demo-action="enable">Enable / Seed demo</button>
              <button class="mg-btn mg-btn-soft" type="button" data-demo-action="reset">Reset demo records</button>
              <button class="mg-btn mg-btn-ghost" type="button" data-demo-action="disable">Disable demo records</button>
            </div>
            <div class="mg-form-status" data-demo-status></div>
          </div>
          <div class="mg-account-section">
            <h3>Current demo state</h3>
            <div data-demo-summary><p class="mg-muted">Loading demo status…</p></div>
          </div>
          <div class="mg-account-section">
            <h3>Latest seeded test data</h3>
            <div data-demo-seeded><p class="mg-muted">No demo record has been seeded in this session yet.</p></div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
