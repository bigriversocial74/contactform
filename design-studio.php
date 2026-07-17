<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/design-studio.php');
$pdo = mg_db();
$displayName = trim((string) mg_user_display_name()) ?: 'Your Business';
$activeView = 'design';
$designStudioStandalone = true;
$designStudioIncludeCalendar = false;

$page_title = 'Design Studio | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'design';
$page_body_class = 'mg-design-studio-standalone-page';
$page_styles = [
    '/assets/css/personal-agent-design-studio.css?v=1.2.0',
    '/assets/css/personal-agent-design-studio-social.css?v=1.0.0',
    '/assets/css/personal-agent-design-studio-calendar.css?v=1.1.0',
    '/assets/css/design-studio-advertising-workflow-v2.css?v=2.0.0',
    '/assets/css/design-studio-standalone.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/personal-agent-design-studio.js?v=1.4.0',
    '/assets/js/personal-agent-design-studio-social.js?v=1.0.0',
    '/assets/js/design-studio-creative-save.js?v=2.0.0',
];
$page_manifest = [
    'id' => 'design-studio',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'design-studio', 'sections' => []],
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
      <a class="is-active" href="/design-studio.php" aria-current="page">Design</a>
      <a href="/design-calendar.php">Calendar</a>
    </nav>
    <a class="mg-standalone-creative-exit" href="/inbox.php">Exit workspace</a>
  </header>

  <div class="mg-standalone-creative-canvas">
    <?php require __DIR__ . '/includes/personal-agent/workspace-design.php'; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>