<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_permission('admin.investment.view');
$canManage = mg_admin_page_user_has_permission($user, 'admin.investment.manage');
$canAi = mg_admin_page_user_has_permission($user, 'admin.investment.ai');
$page_title = 'Investment Wizard | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-investment-page mg-investment-wizard-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0'];
$page_scripts = ['/investment-wizard-runtime.php?v=1.0.0'];
$adminActive = 'investment-wizard';
$csrfToken = mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-investment-wizard" data-investment-wizard data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManage ? '1' : '0' ?>" data-can-ai="<?= $canAi ? '1' : '0' ?>">
      <header class="mg-investment-hero is-admin"><div><a href="/account-admin.php">← Admin dashboard</a><span class="mg-eyebrow">Investment administration</span><h1>Investment Wizard</h1><p>Build saved company workspaces, compare funding scenarios, model runway and dilution, freeze official round terms, attach evidence metrics, and request draft-only Claude analysis.</p></div><div class="mg-investment-hero-actions"><a class="mg-btn mg-btn-ghost" href="/admin/investor-access-requests.php">Investor requests</a><?php if ($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-workspace>Create workspace</button><?php endif; ?></div></header>
      <div class="mg-wizard-shell">
        <aside class="mg-wizard-sidebar"><header><span>Saved workspaces</span><button type="button" data-refresh-workspaces>↻</button></header><div data-workspace-list></div><div class="mg-wizard-sidebar-empty" data-workspace-empty hidden>No workspaces yet.</div></aside>
        <section class="mg-wizard-main">
          <div class="mg-wizard-empty" data-wizard-empty><strong>Create or choose a workspace.</strong><span>Each workspace can contain multiple independent investment scenarios.</span></div>
          <div data-wizard-content hidden>
            <header class="mg-wizard-topbar"><div><span data-workspace-status>Draft</span><h2 data-workspace-title>Investment workspace</h2><p>Last saved <strong data-workspace-saved>—</strong></p></div><div><button class="mg-btn mg-btn-soft" type="button" data-save-current>Save Draft</button><button class="mg-btn mg-btn-primary" type="button" data-save-next>Save and Continue</button></div></header>
            <nav class="mg-wizard-steps" data-wizard-steps aria-label="Investment Wizard steps"></nav>
            <div class="mg-investment-notice" data-wizard-notice role="status" aria-live="polite"></div>
            <section class="mg-wizard-step" data-step-panel></section>
          </div>
        </section>
      </div>
    </section>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
