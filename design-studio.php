<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/personal-agent/social-design-center.php';

$user = mg_require_auth('/signin.php', '/design-studio.php');
$packageContext = mg_user_package_context(null, $user);
$hasDesignAccess = !empty($packageContext['is_paid']) || !empty($packageContext['merchant_access']);
if (!$hasDesignAccess) {
    header('Cache-Control: no-store, private');
    header('Location: /account-subscriptions.php?agent=personal&feature=design', true, 302);
    exit;
}

$pdo = mg_db();
$displayName = trim((string) mg_user_display_name()) ?: 'Your Business';
$activeView = 'design';
$agent_sidebar_mode = 'personal';
$suppress_agent_sidebar_footer = true;
$suppress_agent_sidebar_tools = true;
$designStudioStandalone = true;
$designStudioIncludeCalendar = false;
$socialDesignRegistry = mg_social_design_registry();

$page_title = 'Design Studio | Microgifter';
$page_section = 'agent';
$page_body_class = 'mg-design-studio-standalone-page';
$page_styles = [
    '/assets/css/personal-agent-chat-history.css?v=1.4.0',
    '/assets/css/personal-agent-sidebar-cleanup.css?v=1.0.0',
    '/assets/css/personal-agent-design-studio.css?v=1.2.0',
    '/assets/css/personal-agent-design-studio-social.css?v=2.0.0',
    '/assets/css/personal-agent-design-studio-calendar.css?v=1.1.0',
    '/assets/css/design-studio-advertising-workflow-v2.css?v=2.0.0',
    '/assets/css/design-studio-standalone.css?v=1.1.0',
    '/assets/css/design-studio-runtime-fix.css?v=1.0.0',
    '/assets/css/design-studio-loaded-template-v1.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/personal-agent-chat-history.js?v=1.2.0',
    '/assets/js/personal-agent-design-studio.js?v=1.5.0',
    '/assets/js/personal-agent-design-studio-social.js?v=2.0.0',
    '/assets/js/design-studio-template-variants.js?v=1.0.1',
    '/assets/js/design-studio-schedule-context.js?v=1.0.1',
    '/assets/js/design-studio-creative-save.js?v=2.2.0',
];
$page_manifest = [
    'id' => 'design-studio',
    'title' => $page_title,
    'section' => $page_section,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'design-studio', 'sections' => []],
];

require __DIR__ . '/includes/standalone-creative-header.php';
?>
<section class="mg-app-shell mg-standalone-creative-shell" data-standalone-creative-shell>
  <?php require __DIR__ . '/includes/personal-agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-standalone-creative-workspace">
    <button class="mg-standalone-sidebar-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open workspace sidebar" aria-expanded="false"><span></span><span></span><span></span></button>
    <div class="mg-standalone-creative-canvas">
      <?php require __DIR__ . '/includes/personal-agent/workspace-design.php'; ?>
    </div>
  </div>
</section>
<script type="application/json" id="mg-social-design-registry"><?= json_encode(
    $socialDesignRegistry,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>
<?php require __DIR__ . '/includes/standalone-creative-footer.php'; ?>
