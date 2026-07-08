<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Campaign Embed QA | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/stage12-campaign-embed-tools.css','/assets/css/campaign-embed-qa.css'];
$user = mg_current_user();
$campaignRef = trim((string)($_GET['campaign'] ?? ''));
$campaignRef = preg_replace('/[^a-zA-Z0-9_\-]/', '', $campaignRef) ?: '';
$debug = !empty($_GET['debug']) ? '1' : '1';
require __DIR__ . '/includes/header.php';
?>
<main class="mg-embed-qa-shell">
  <?php if (!$user): ?>
    <section class="mg-embed-qa-panel">
      <span class="mg-eyebrow">Merchant QA</span>
      <h1>Campaign Embed QA</h1>
      <p>Sign in to run campaign embed runtime QA checks.</p>
      <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
    </section>
  <?php else: ?>
    <section class="mg-embed-qa-hero">
      <div>
        <span class="mg-eyebrow">Campaign Embed Runtime QA</span>
        <h1>Embed Runtime Smoke Test</h1>
        <p>Use this page after importing <code>database/campaign_embed_settings_v2.sql</code> to test inline, button, compact, debug, and event ingestion behavior from a controlled host page.</p>
      </div>
      <form class="mg-embed-qa-form" method="get">
        <label>Campaign slug or public ID
          <input type="text" name="campaign" value="<?= htmlspecialchars($campaignRef, ENT_QUOTES, 'UTF-8') ?>" placeholder="summer-reward-drop">
        </label>
        <button class="mg-btn mg-btn-primary" type="submit">Load QA embeds</button>
        <a class="mg-btn mg-btn-ghost" href="/merchant-campaigns.php">Back to Campaigns</a>
      </form>
    </section>

    <section class="mg-embed-qa-grid">
      <article class="mg-embed-qa-card">
        <h2>Runtime checklist</h2>
        <ul>
          <li>Open the merchant campaign Embed modal and confirm SQL health is ready.</li>
          <li>Use this page to trigger <code>loaded</code>, <code>opened</code>, <code>invalid</code>, <code>submitted</code>, and <code>error</code> events.</li>
          <li>Return to the modal and click <strong>Refresh activity</strong>.</li>
          <li>Confirm allowed domains by saving a domain list and loading this page from the allowed host.</li>
        </ul>
      </article>
      <article class="mg-embed-qa-card">
        <h2>Health endpoint</h2>
        <p>Merchant runtime health is served by:</p>
        <code>/api/merchant/campaign-embed-runtime-health.php</code>
        <?php if ($campaignRef !== ''): ?>
          <p><a href="/api/merchant/campaign-embed-runtime-health.php?campaign=<?= rawurlencode($campaignRef) ?>" target="_blank" rel="noopener">Open health JSON for this campaign</a></p>
        <?php endif; ?>
      </article>
    </section>

    <?php if ($campaignRef === ''): ?>
      <section class="mg-embed-qa-panel">
        <h2>No campaign selected</h2>
        <p>Enter an active campaign slug or public ID above. The page will render the same campaign in all supported embed layouts.</p>
      </section>
    <?php else: ?>
      <section class="mg-embed-qa-host">
        <div class="mg-embed-qa-host-head">
          <span class="mg-eyebrow">Host CSS sample</span>
          <h2>Inline mode</h2>
          <p>This card intentionally uses local page styling so the script can prove it adopts host typography and form rhythm.</p>
        </div>
        <div class="microgifter-campaign-embed" data-microgifter-campaign="<?= htmlspecialchars($campaignRef, ENT_QUOTES, 'UTF-8') ?>" data-microgifter-display="inline" data-microgifter-source="internal_runtime_qa" data-microgifter-debug="<?= $debug ?>"></div>
      </section>

      <section class="mg-embed-qa-host is-button-mode">
        <div class="mg-embed-qa-host-head">
          <span class="mg-eyebrow">Button / popup sample</span>
          <h2>Button mode</h2>
          <p>Click the launcher to trigger the <code>opened</code> event.</p>
        </div>
        <div class="microgifter-campaign-embed" data-microgifter-campaign="<?= htmlspecialchars($campaignRef, ENT_QUOTES, 'UTF-8') ?>" data-microgifter-display="button" data-microgifter-button-label="Open QA campaign" data-microgifter-source="internal_runtime_qa" data-microgifter-debug="<?= $debug ?>"></div>
      </section>

      <section class="mg-embed-qa-host is-compact-mode">
        <div class="mg-embed-qa-host-head">
          <span class="mg-eyebrow">Compact sample</span>
          <h2>Compact mode</h2>
          <p>Use this mode for tighter host website placements.</p>
        </div>
        <div class="microgifter-campaign-embed" data-microgifter-campaign="<?= htmlspecialchars($campaignRef, ENT_QUOTES, 'UTF-8') ?>" data-microgifter-display="compact" data-microgifter-source="internal_runtime_qa" data-microgifter-debug="<?= $debug ?>"></div>
      </section>
      <script async src="/assets/js/microgifter-campaign-embed.js" data-microgifter-debug="<?= $debug ?>"></script>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
