<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-permission-matrix.php';
$page_title = 'Hosted Games | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css','/assets/css/hosted-games-management.css?v=1.0.0'];
$page_scripts = ['/assets/js/admin-hosted-games.js?v=1.0.0'];
$user = mg_current_user();
$hasAdminAccess = is_array($user) && (
    mg_admin_permission_user_has($user,'admin.hosted_games.view')
    || mg_admin_permission_user_has($user,'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user,'admin.settings.manage')
);
$adminActive = 'hosted-games';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace" data-admin-hosted-games data-csrf="<?= mg_e(mg_csrf_token()) ?>">
    <div class="hgm-page">
      <?php if (!$user): ?>
        <section class="hgm-empty"><h3>Admin access</h3><p>Sign in to manage hosted game databases.</p><a class="hgm-btn is-primary" href="/signin.php">Sign in</a></section>
      <?php elseif (!$hasAdminAccess): ?>
        <section class="hgm-empty"><h3>Access unavailable</h3><p>This account does not have Hosted Games administration permission.</p></section>
      <?php else: ?>
        <section class="hgm-hero">
          <div>
            <span class="hgm-eyebrow">Platform administration</span>
            <h1>Hosted Games<br>database control</h1>
            <p>Assign and test an isolated MySQL database for every merchant game. Usernames and passwords are encrypted server-side and are never returned to the browser after saving.</p>
          </div>
        </section>

        <div class="hgm-notice" data-hgm-admin-notice hidden></div>
        <section class="hgm-stats">
          <article class="hgm-stat"><span>Total games</span><strong data-hgm-admin-stat="total">0</strong></article>
          <article class="hgm-stat"><span>Database ready</span><strong data-hgm-admin-stat="ready">0</strong></article>
          <article class="hgm-stat"><span>Pending</span><strong data-hgm-admin-stat="pending">0</strong></article>
          <article class="hgm-stat"><span>Connection errors</span><strong data-hgm-admin-stat="errors">0</strong></article>
          <article class="hgm-stat"><span>Published</span><strong data-hgm-admin-stat="active">0</strong></article>
        </section>

        <div class="hgm-toolbar"><h2>Hosted game inventory</h2><input type="search" placeholder="Search game, merchant, program, campaign, or reward" data-hgm-admin-search></div>
        <section class="hgm-admin-table" data-hgm-admin-list></section>
        <section class="hgm-empty" data-hgm-admin-empty hidden><h3>No hosted games</h3><p>Merchant-created hosted games will appear here for database provisioning.</p></section>

        <div class="hgm-modal" data-hgm-admin-modal hidden>
          <div class="hgm-modal-card">
            <header class="hgm-modal-head"><h2 data-hgm-admin-modal-title>Game database</h2><button class="hgm-modal-close" type="button" data-hgm-admin-close>×</button></header>
            <div class="hgm-modal-body">
              <section class="hgm-section">
                <h3>Game and merchant</h3>
                <div class="hgm-db-summary" data-hgm-admin-summary></div>
              </section>
              <section class="hgm-section">
                <h3>Isolated MySQL connection</h3>
                <p>Use a database and database user dedicated to this game. Leave the username or password blank when editing to preserve the encrypted saved value.</p>
                <form class="hgm-form" data-hgm-admin-db-form>
                  <input type="hidden" name="game_id">
                  <div class="hgm-form-grid">
                    <label>Database host<input name="host" maxlength="255" required placeholder="localhost"></label>
                    <label>Port<input name="port" type="number" min="1" max="65535" value="3306" required></label>
                  </div>
                  <div class="hgm-form-grid">
                    <label>Database name<input name="database_name" maxlength="190" required placeholder="merchant_game_database"></label>
                    <label>Character set<select name="charset"><option value="utf8mb4">utf8mb4</option><option value="utf8">utf8</option></select></label>
                  </div>
                  <div class="hgm-form-grid">
                    <label>Database username<input name="username" maxlength="190" autocomplete="off" placeholder="Leave blank to preserve saved username"></label>
                    <label>Database password<input name="password" type="password" autocomplete="new-password" placeholder="Leave blank to preserve saved password"></label>
                  </div>
                  <label><span><input name="test_after_save" type="checkbox" value="1" checked> Test connection and initialize standard game tables after saving</span></label>
                  <div class="hgm-card-actions">
                    <button class="hgm-btn is-primary" type="submit">Save database settings</button>
                    <button class="hgm-btn is-success" type="button" data-hgm-admin-test>Test connection</button>
                    <button class="hgm-btn is-danger" type="button" data-hgm-admin-disable>Disable database</button>
                  </div>
                  <div class="hgm-form-status" data-hgm-admin-form-status></div>
                </form>
              </section>
              <section class="hgm-section">
                <h3>Platform controls</h3>
                <p>Pausing a game immediately removes its public play URL while preserving files, configuration, database credentials, and analytics.</p>
                <div class="hgm-card-actions"><button class="hgm-btn is-danger" type="button" data-hgm-admin-pause>Pause hosted game</button><a class="hgm-btn is-soft" href="#" target="_blank" rel="noopener" data-hgm-admin-open>Open public URL</a></div>
              </section>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
