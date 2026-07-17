<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/design-calendar.php');
$pdo = mg_db();
$displayName = trim((string) mg_user_display_name()) ?: 'Your Business';
$hasMerchantAccess = mg_user_has_merchant_access($user, $pdo);

$page_title = 'Design Calendar | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'calendar';
$page_body_class = 'mg-design-calendar-standalone-page';
$page_styles = [
    '/assets/css/personal-agent-design-studio.css?v=1.2.0',
    '/assets/css/personal-agent-design-studio-calendar.css?v=1.1.0',
    '/assets/css/design-studio-advertising-workflow-v2.css?v=2.0.0',
    '/assets/css/design-studio-standalone.css?v=1.0.0',
    '/assets/css/design-calendar-standalone.css?v=1.0.0',
];
$page_scripts = $hasMerchantAccess ? [
    '/assets/js/personal-agent-design-studio-calendar.js?v=2.1.0',
    '/assets/js/design-studio-creative-save.js?v=2.0.0',
    '/assets/js/design-calendar-modal.js?v=1.0.0',
] : [];
$page_manifest = [
    'id' => 'design-calendar',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'design-calendar', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-standalone-creative-shell" data-standalone-creative-shell>
  <header class="mg-standalone-creative-bar">
    <a class="mg-standalone-creative-brand" href="/inbox.php" aria-label="Return to Microgifter Inbox">
      <img src="/images/logo_main_drk.png" alt="Microgifter">
      <span><strong>Microgifter</strong><small>Creative Workspace</small></span>
    </a>
    <nav aria-label="Creative workspace pages">
      <a href="/design-studio.php">Design</a>
      <a class="is-active" href="/design-calendar.php" aria-current="page">Calendar</a>
    </nav>
    <a class="mg-standalone-creative-exit" href="/inbox.php">Exit workspace</a>
  </header>

  <div class="mg-standalone-creative-canvas">
    <?php if (!$hasMerchantAccess): ?>
      <section class="mg-standalone-access-card">
        <span class="mg-agent-design-step">Merchant access required</span>
        <h1>Content Calendar is for merchant workspaces.</h1>
        <p>Use a merchant account to schedule product posts, formats, campaign themes, creative layouts, and publishing status.</p>
        <a class="mg-btn mg-btn-primary" href="/account-subscriptions.php?agent=merchant">Review merchant packages</a>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/personal-agent/workspace-design-calendar.php'; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>