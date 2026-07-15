<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-permission-matrix.php';
$page_title = 'Hosted Games | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_styles = [
    '/assets/css/admin-dashboard.css',
    '/assets/css/hosted-games-management.css?v=1.1.0',
    '/assets/css/hosted-games-runtime-toggle.css?v=1.0.0',
    '/assets/css/hosted-games-cover-upload.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/admin-hosted-games.js?v=1.1.0',
    '/assets/js/admin-hosted-games-program-only.js?v=1.0.0',
    '/assets/js/admin-hosted-games-runtime-toggle.js?v=1.0.0',
    '/assets/js/hosted-games-cover-upload.js?v=1.0.0',
    '/assets/js/hosted-games-analytics-links.js?v=1.0.0',
];
$user = mg_current_user();
$hasAdminAccess = is_array($user) && (
    mg_admin_permission_user_has($user,'admin.hosted_games.view')
    || mg_admin_permission_user_has($user,'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user,'admin.settings.manage')
);
$canManageHostedGames = is_array($user) && (
    mg_admin_permission_user_has($user,'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user,'admin.settings.manage')
);
$adminActive = 'hosted-games';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace" data-admin-hosted-games data-csrf="<?= mg_e(mg_csrf_token()) ?>" data-can-manage="<?= $canManageHostedGames ? '1' : '0' ?>">
    <div class="hgm-page">
      <?php if (!$user): ?>
        <section class="hgm-empty"><h3>Admin access</h3><p>Sign in to manage hosted games.</p><a class="hgm-btn is-primary" href="/signin.php">Sign in</a></section>
      <?php elseif (!$hasAdminAccess): ?>
        <section class="hgm-empty"><h3>Access unavailable</h3><p>This account does not have Hosted Games administration permission.</p></section>
      <?php else: ?>
        <section class="hgm-hero">
          <div>
            <span class="hgm-eyebrow">Platform administration</span>
            <h1>Hosted Games<br>operations center</h1>
            <p>Create and manage merchant games, upload releases, connect Distribution Programs, assign isolated databases, and control each game runtime from one administrative workspace.</p>
          </div>
          <?php if ($canManageHostedGames): ?><button class="hgm-btn is-primary" type="button" data-hgm-admin-create>Create hosted game</button><?php endif; ?>
        </section>

        <div class="hgm-notice" data-hgm-admin-notice hidden></div>
        <section class="hgm-stats">
          <article class="hgm-stat"><span>Total games</span><strong data-hgm-admin-stat="total">0</strong></article>
          <article class="hgm-stat"><span>Publish ready</span><strong data-hgm-admin-stat="publish_ready">0</strong></article>
          <article class="hgm-stat"><span>Database ready</span><strong data-hgm-admin-stat="ready">0</strong></article>
          <article class="hgm-stat"><span>Setup required</span><strong data-hgm-admin-stat="pending">0</strong></article>
          <article class="hgm-stat"><span>Enabled</span><strong data-hgm-admin-stat="active">0</strong></article>
        </section>

        <div class="hgm-toolbar"><h2>Hosted game inventory</h2><input type="search" placeholder="Search game, merchant, program, campaign, or reward" data-hgm-admin-search></div>
        <section class="hgm-admin-table" data-hgm-admin-list></section>
        <section class="hgm-empty" data-hgm-admin-empty hidden><h3>No hosted games</h3><p>Create the first merchant game from Microgifter Admin or wait for a merchant to create one.</p></section>

        <?php if ($canManageHostedGames): ?>
        <div class="hgm-modal" data-hgm-admin-modal hidden>
          <div class="hgm-modal-card">
            <header class="hgm-modal-head"><div><span class="hgm-eyebrow">Microgifter Admin</span><h2 data-hgm-admin-modal-title>Hosted game</h2></div><button class="hgm-modal-close" type="button" data-hgm-admin-close>×</button></header>
            <div class="hgm-modal-body">
              <section class="hgm-section">
                <h3>1. Merchant and game identity</h3>
                <p>Create the hosted-game record for a merchant or update its public identity, cover image, and unique URL.</p>
                <form class="hgm-form" data-hgm-admin-game-form>
                  <input type="hidden" name="game_id">
                  <label>Merchant account<select name="merchant_user_id" required data-hgm-admin-merchant><option value="">Select merchant</option></select></label>
                  <div class="hgm-form-grid">
                    <label>Game name<input name="name" maxlength="180" required placeholder="Summer Reward Challenge"></label>
                    <label>Public URL slug<input name="slug" maxlength="140" required placeholder="summer-reward-challenge"></label>
                  </div>
                  <label>Description<textarea name="description" rows="3" maxlength="5000" placeholder="Describe the game experience."></textarea></label>
                  <label>Cover image URL <span class="hgm-help">Optional external-image fallback</span><input name="cover_url" type="url" maxlength="500" placeholder="https://..."></label>
                  <div class="hgm-cover-uploader" data-hgm-cover-uploader>
                    <div class="hgm-cover-preview" data-hgm-cover-preview>
                      <img alt="Hosted game cover preview" hidden>
                      <span>16:9 game cover preview<br>Recommended: 1600 × 900</span>
                    </div>
                    <div class="hgm-cover-controls">
                      <label class="hgm-cover-file">
                        <strong>Upload cover image</strong>
                        <small>JPEG, PNG, or WebP · minimum 640 × 360 · maximum 10 MB</small>
                        <input type="file" accept="image/jpeg,image/png,image/webp" data-hgm-cover-file>
                      </label>
                      <div class="hgm-cover-progress" aria-hidden="true"><span data-hgm-cover-progress></span></div>
                      <div class="hgm-cover-actions"><button class="hgm-btn is-soft" type="button" data-hgm-cover-upload disabled>Upload selected image</button></div>
                      <div class="hgm-cover-status" data-hgm-cover-status></div>
                    </div>
                  </div>
                  <div class="hgm-card-actions"><button class="hgm-btn is-primary" type="submit">Save game identity</button></div>
                  <div class="hgm-form-status" data-hgm-admin-game-status></div>
                </form>
              </section>

              <div data-hgm-admin-existing hidden>
                <section class="hgm-section">
                  <h3>2. Upload game release</h3>
                  <p>Upload the complete browser-game ZIP. The same traversal, executable, symlink, compression and size protections used by the merchant uploader apply here.</p>
                  <div class="hgm-upload-zone" data-hgm-admin-drop>
                    <input type="file" accept=".zip,application/zip" data-hgm-admin-file>
                    <strong data-hgm-admin-file-title>Select a game ZIP</strong>
                    <span data-hgm-admin-file-detail>HTML, JavaScript, media, WebGL, WASM and Unity assets · maximum 100 MB ZIP</span>
                  </div>
                  <div class="hgm-progress"><span data-hgm-admin-progress></span></div>
                  <div class="hgm-card-actions"><button class="hgm-btn is-primary" type="button" data-hgm-admin-upload disabled>Upload new release</button></div>
                  <div class="hgm-form-status" data-hgm-admin-upload-status></div>
                </section>

                <section class="hgm-section">
                  <h3>3. Distribution integration</h3>
                  <p>Select the game owner’s Distribution Program. The Program, Campaign and reward relationship is resolved automatically, then Microgifter creates and encrypts the dedicated API credential, webhook secret, and state secret. No per-game environment values are required.</p>
                  <form class="hgm-form" data-hgm-admin-integration-form>
                    <input type="hidden" name="game_id">
                    <select name="campaign_id" aria-hidden="true" tabindex="-1" style="display:none!important"><option value=""></option></select>
                    <select name="reward_template_id" aria-hidden="true" tabindex="-1" style="display:none!important"><option value=""></option></select>
                    <label>Distribution Program<select name="program_id" required data-hgm-admin-program><option value="">Select a Program</option></select></label>
                    <div class="hgm-card-actions"><button class="hgm-btn is-primary" type="submit">Configure game integration</button></div>
                    <div class="hgm-form-status" data-hgm-admin-integration-status></div>
                  </form>
                </section>

                <section class="hgm-section">
                  <h3>4. Isolated MySQL connection</h3>
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
                    <div class="hgm-form-status" data-hgm-admin-db-status></div>
                  </form>
                </section>

                <section class="hgm-section">
                  <h3>5. Managed runtime and publication</h3>
                  <p>Use the Game enabled switch after all readiness checks pass. Disabling pauses gameplay and reward issuance while preserving the release, Distribution Program, encrypted API credential, and webhook configuration.</p>
                  <div class="hgm-db-summary" data-hgm-admin-summary></div>
                  <div class="hgm-ready-list" data-hgm-admin-readiness></div>
                  <div class="hgm-card-actions">
                    <button class="hgm-btn is-success" type="button" data-hgm-admin-publish>Publish hosted game</button>
                    <button class="hgm-btn" type="button" data-hgm-admin-pause>Pause game</button>
                    <button class="hgm-btn is-danger" type="button" data-hgm-admin-archive>Archive game</button>
                    <a class="hgm-btn is-soft" href="#" target="_blank" rel="noopener" data-hgm-admin-open>Open public URL</a>
                  </div>
                  <div class="hgm-form-status" data-hgm-admin-publish-status></div>
                </section>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
