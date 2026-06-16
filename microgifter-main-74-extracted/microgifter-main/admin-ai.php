<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

if (!mg_has_role('super_admin') && !mg_has_permission('admin.settings.manage')) {
    http_response_code(403);
    exit('Forbidden');
}

$page_title = 'AI Provider Settings | Microgifter';
$page_section = 'account';
$header_mode = 'account';

$providers = [
    ['name' => 'Claude', 'provider' => 'Anthropic', 'env' => 'MG_ANTHROPIC_API_KEY', 'configured' => (bool) mg_env('MG_ANTHROPIC_API_KEY', '')],
    ['name' => 'GPT', 'provider' => 'OpenAI', 'env' => 'MG_OPENAI_API_KEY', 'configured' => (bool) mg_env('MG_OPENAI_API_KEY', '')],
    ['name' => 'Gemma', 'provider' => 'Google / self-hosted', 'env' => 'MG_GEMMA_API_KEY', 'configured' => (bool) mg_env('MG_GEMMA_API_KEY', '')],
    ['name' => 'Kimi', 'provider' => 'Moonshot AI', 'env' => 'MG_KIMI_API_KEY', 'configured' => (bool) mg_env('MG_KIMI_API_KEY', '')],
    ['name' => 'Llama', 'provider' => 'Meta / self-hosted', 'env' => 'MG_LLAMA_API_KEY', 'configured' => (bool) mg_env('MG_LLAMA_API_KEY', '')],
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-ai-admin-page">
  <header class="mg-ai-admin-head">
    <div>
      <span>Admin security</span>
      <h1>AI provider settings</h1>
      <p>Provider secrets are loaded from server environment variables only. Keys are never rendered into HTML, JavaScript, local storage, logs, or the browser network payload.</p>
    </div>
    <a class="mg-btn mg-btn-ghost" href="/account-admin.php">Back to admin</a>
  </header>

  <section class="mg-ai-security-notice">
    <strong>Secure deployment requirement</strong>
    <p>Set provider keys in the hosting environment or a server-local ignored configuration layer. Do not paste production keys into source code or commit them to GitHub.</p>
  </section>

  <div class="mg-ai-provider-list">
    <?php foreach ($providers as $provider): ?>
      <article class="mg-ai-provider-card">
        <div>
          <span><?= mg_e($provider['provider']) ?></span>
          <h2><?= mg_e($provider['name']) ?></h2>
          <code><?= mg_e($provider['env']) ?></code>
        </div>
        <span class="mg-ai-provider-status <?= $provider['configured'] ? 'is-ready' : 'is-missing' ?>">
          <?= $provider['configured'] ? 'Configured' : 'Not configured' ?>
        </span>
      </article>
    <?php endforeach; ?>
  </div>

  <section class="mg-ai-admin-instructions">
    <h2>Server configuration</h2>
    <p>Example environment variable names:</p>
    <pre>MG_ANTHROPIC_API_KEY=...
MG_OPENAI_API_KEY=...
MG_GEMMA_API_KEY=...
MG_KIMI_API_KEY=...
MG_LLAMA_API_KEY=...</pre>
    <p>The application should call provider APIs from server-side endpoints only. Browser clients should receive short-lived application responses, never provider credentials.</p>
  </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>