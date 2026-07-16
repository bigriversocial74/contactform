<?php
declare(strict_types=1);

$designMerchantName = $displayName !== '' ? $displayName : 'Your Business';
$designDestination = '/profile.php';
try {
    $designUserId = (int) (($user['id'] ?? 0));
    if ($designUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare('SELECT slug FROM public_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$designUserId]);
        $slug = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($slug !== '') $designDestination = '/profile.php?slug=' . rawurlencode($slug);
    }
} catch (Throwable) {
    $designDestination = '/profile.php';
}
?>
<section class="mg-personal-agent-view mg-agent-design-view"
         data-personal-agent-view="design"
         hidden
         data-agent-design-studio
         data-default-destination="<?= mg_e($designDestination) ?>">
  <header class="mg-agent-design-header">
    <div>
      <span>Merchant Design Studio</span>
      <h1 id="design-object-heading" data-design-page-title>Choose what you want to design.</h1>
      <p data-design-page-description>Select a print object first. Template choices open after you choose the format.</p>
    </div>
  </header>

  <section class="mg-agent-design-object-picker" data-design-object-picker aria-labelledby="design-object-heading">
    <div class="mg-agent-design-object-grid">
      <button type="button" class="mg-agent-design-object-card" data-design-object="poster">
        <span class="mg-agent-design-object-visual is-poster" aria-hidden="true">
          <span class="mg-agent-design-object-sheet">
            <span class="mg-agent-design-object-kicker"></span>
            <span class="mg-agent-design-object-title"></span>
            <span class="mg-agent-design-object-copy"></span>
            <span class="mg-agent-design-object-band"><span></span></span>
          </span>
        </span>
        <span class="mg-agent-design-object-copy-block">
          <span class="mg-agent-design-object-number">01</span>
          <strong>5 × 5 Poster Card</strong>
          <small>Square counter, register, shelf, or window display.</small>
        </span>
        <span class="mg-agent-design-object-action">Choose poster card <span aria-hidden="true">→</span></span>
      </button>

      <button type="button" class="mg-agent-design-object-card" data-design-object="tent">
        <span class="mg-agent-design-object-visual is-tent" aria-hidden="true">
          <span class="mg-agent-design-tent-face is-back"></span>
          <span class="mg-agent-design-tent-face is-front">
            <span class="mg-agent-design-object-kicker"></span>
            <span class="mg-agent-design-object-title"></span>
            <span class="mg-agent-design-object-band"><span></span></span>
          </span>
        </span>
        <span class="mg-agent-design-object-copy-block">
          <span class="mg-agent-design-object-number">02</span>
          <strong>Table Tent</strong>
          <small>Folded two-sided display for tables, counters, and events.</small>
        </span>
        <span class="mg-agent-design-object-action">Choose table tent <span aria-hidden="true">→</span></span>
      </button>
    </div>
  </section>

  <div class="mg-agent-design-workspace" data-design-workspace hidden>
    <aside class="mg-agent-design-rail" aria-label="Design template and export controls">
      <button type="button" class="mg-agent-design-back" data-design-back><span aria-hidden="true">←</span> All design objects</button>

      <section class="mg-agent-design-object-summary">
        <span class="mg-agent-design-step">Selected object</span>
        <strong data-design-object-label>5 × 5 Poster Card</strong>
        <small data-design-object-detail>Square counter or window display</small>
      </section>

      <section class="mg-agent-design-template-panel">
        <span class="mg-agent-design-step">Step 2</span>
        <h2>Choose a template</h2>
        <div class="mg-agent-template-picker">
          <button type="button" data-design-template="support-local" data-template-formats="poster,tent">
            <span class="mg-agent-template-thumbnail" aria-hidden="true">
              <span>Reward yourself.</span>
              <em>Support local.</em>
              <i></i>
            </span>
            <span><strong>Support Local</strong><small>Microgifter brand + profile QR</small></span>
          </button>
        </div>
        <p class="mg-agent-design-help">More templates can be added here without reducing the preview area.</p>
      </section>

      <section class="mg-agent-design-profile-card">
        <span class="mg-agent-design-step">Profile QR</span>
        <strong><?= mg_e($designMerchantName) ?></strong>
        <code title="<?= mg_e($designDestination) ?>"><?= mg_e($designDestination) ?></code>
      </section>

      <div class="mg-agent-design-actions">
        <button type="button" class="mg-btn mg-btn-primary" data-design-download disabled>Download JPG</button>
        <span data-design-status role="status" aria-live="polite">Choose a template to enable download.</span>
      </div>
    </aside>

    <div class="mg-agent-design-preview-column">
      <div class="mg-agent-design-preview-toolbar">
        <div>
          <span>Design workspace</span>
          <strong data-design-format-label>5 × 5 Poster Card</strong>
        </div>
        <small data-design-template-label>No template selected</small>
      </div>

      <div class="mg-agent-design-stage">
        <div class="mg-agent-design-empty" data-design-empty>
          <span class="mg-agent-design-empty-icon" aria-hidden="true">+</span>
          <strong data-design-empty-title>Choose a template</strong>
          <p data-design-empty-copy>Select a template from the compact side rail to place it on your poster card.</p>
        </div>

        <article class="mg-agent-print-design is-poster" data-design-canvas hidden>
          <div class="mg-agent-print-face mg-agent-print-front">
            <header class="mg-agent-print-brand"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>MICROGIFTER</span></header>
            <div class="mg-agent-print-copy">
              <h2>Reward yourself.<br><em>Support local.</em></h2>
              <p>Scan to earn rewards and discover local gifts.</p>
            </div>
            <footer class="mg-agent-print-qr-band">
              <div class="mg-agent-print-qr" data-design-qr aria-label="Merchant profile QR code"></div>
              <div><strong><?= mg_e($designMerchantName) ?></strong><span>Scan to visit our Microgifter profile.</span></div>
            </footer>
          </div>

          <div class="mg-agent-print-face mg-agent-print-back" aria-hidden="true">
            <span class="mg-agent-quote-mark">“</span>
            <blockquote>Discover local gifts, rewards, and experiences from <?= mg_e($designMerchantName) ?>.</blockquote>
            <div class="mg-agent-quote-byline"><strong><?= mg_e($designMerchantName) ?></strong><span>Powered by Microgifter</span></div>
          </div>
        </article>
      </div>
    </div>
  </div>
</section>
