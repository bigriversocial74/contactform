<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-auth.php';
$user = mg_require_admin_page_permission('admin.settings.manage');
$page_title = 'AI Provider Settings | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-ai.css'];
$page_scripts = ['/assets/js/admin-ai.js'];
$adminActive = 'ai';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app" data-admin-ai>
  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-ai-admin-page">
      <header class="mg-ai-admin-head">
        <div><span>Admin security</span><h1>AI provider settings</h1><p>Provider keys remain server-side environment variables. This page controls which providers/models agents can use and enforces triple rate limits: platform key, per user, and per agent per user.</p></div>
        <a class="mg-btn mg-btn-ghost" href="/account-admin.php">Back to admin</a>
      </header>
      <section class="mg-ai-security-notice"><strong>Secure deployment requirement</strong><p>Set provider keys in the hosting environment. Do not paste production keys into source code or expose them to the browser.</p><p>For cPanel/HostGator deployments without an environment variable UI, copy <code>api/config.local.example.php</code> to <code>api/config.local.php</code> and set <code>$mgAnthropicApiKey</code> there. That file must stay server-local and out of Git.</p><pre>MG_ANTHROPIC_API_KEY=...
MG_OPENAI_API_KEY=...
MG_GEMMA_API_KEY=...
MG_KIMI_API_KEY=...
MG_LLAMA_API_KEY=...</pre></section>
      <form class="mg-ai-settings-form" data-ai-settings-form>
        <div class="mg-ai-admin-status" data-ai-settings-status>Loading AI providers…</div>
        <div class="mg-ai-provider-list" data-ai-provider-list></div>
        <footer class="mg-ai-save-bar"><button class="mg-btn mg-btn-primary" type="submit">Save AI provider settings</button></footer>
      </form>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>