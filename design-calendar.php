<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/design-calendar.php');
$pdo = mg_db();
$displayName = trim((string) mg_user_display_name()) ?: 'Your Business';
$hasMerchantAccess = mg_user_has_merchant_access($user, $pdo);
$agent_sidebar_mode = 'personal';
$suppress_agent_sidebar_footer = true;
$suppress_agent_sidebar_tools = true;
$designCalendarStandalone = true;

$page_title = 'Design Calendar | Microgifter';
$page_section = 'agent';
$page_body_class = 'mg-design-calendar-standalone-page';
$page_styles = [
    '/assets/css/personal-agent-chat-history.css?v=1.4.0',
    '/assets/css/personal-agent-sidebar-cleanup.css?v=1.0.0',
    '/assets/css/personal-agent-design-studio.css?v=1.2.0',
    '/assets/css/personal-agent-design-studio-social.css?v=1.0.0',
    '/assets/css/personal-agent-design-studio-calendar.css?v=1.1.0',
    '/assets/css/design-studio-advertising-workflow-v2.css?v=2.0.0',
    '/assets/css/design-studio-standalone.css?v=1.1.0',
    '/assets/css/design-calendar-standalone.css?v=2.0.0',
    '/assets/css/design-calendar-row-layout-v1.css?v=1.0.0',
    '/assets/css/design-studio-runtime-fix.css?v=1.0.0',
];
$page_scripts = $hasMerchantAccess ? [
    '/assets/js/personal-agent-chat-history.js?v=1.1.0',
    '/assets/js/personal-agent-design-studio-calendar.js?v=3.0.0',
    '/assets/js/design-calendar-row-layout-v1.js?v=1.0.0',
    '/assets/js/design-calendar-modal.js?v=2.0.0',
    '/assets/js/design-studio-creative-save.js?v=2.0.0',
] : ['/assets/js/personal-agent-chat-history.js?v=1.1.0'];
$page_manifest = [
    'id' => 'design-calendar',
    'title' => $page_title,
    'section' => $page_section,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'design-calendar', 'sections' => []],
];

require __DIR__ . '/includes/standalone-creative-header.php';
?>
<section class="mg-app-shell mg-standalone-creative-shell" data-standalone-creative-shell>
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-standalone-creative-workspace">
    <button class="mg-standalone-sidebar-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open workspace sidebar" aria-expanded="false"><span></span><span></span><span></span></button>
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
  </div>
</section>
<?php require __DIR__ . '/includes/standalone-creative-footer.php'; ?>
