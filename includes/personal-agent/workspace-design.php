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
<section class="mg-personal-agent-view mg-agent-design-view" data-personal-agent-view="design" hidden data-agent-design-studio data-default-destination="<?= mg_e($designDestination) ?>">
  <header class="mg-agent-design-header">
    <div>
      <span>Merchant Design Studio</span>
      <h1>Choose a template and download.</h1>
      <p>Your merchant name, Microgifter branding, and profile QR code are applied automatically.</p>
    </div>
    <button type="button" class="mg-btn mg-btn-primary" data-design-download>Download JPG</button>
  </header>

  <div class="mg-agent-design-shell">
    <aside class="mg-agent-design-controls" aria-label="Template choices">
      <section>
        <span class="mg-agent-design-step">Templates</span>
        <h2>Choose one design</h2>
        <div class="mg-agent-template-picker">
          <button type="button" class="is-active" data-design-format="poster"><strong>5 × 5 Poster Card</strong><small>Square counter or window display</small></button>
          <button type="button" data-design-format="tent"><strong>Table Tent</strong><small>Folded countertop display</small></button>
        </div>
      </section>

      <section>
        <span class="mg-agent-design-step">Profile QR</span>
        <h2><?= mg_e($designMerchantName) ?></h2>
        <p class="mg-agent-design-help">This QR code links directly to your Microgifter merchant profile.</p>
        <code><?= mg_e($designDestination) ?></code>
      </section>

      <section class="mg-agent-design-download-card">
        <strong>Ready to print</strong>
        <span>Select a template, download the JPG, and print it locally.</span>
        <button type="button" class="mg-btn mg-btn-primary" data-design-download>Download JPG</button>
      </section>
    </aside>

    <div class="mg-agent-design-preview-column">
      <div class="mg-agent-design-preview-toolbar">
        <div><span>Live preview</span><strong data-design-format-label>5 × 5 Poster Card</strong></div>
        <small>Two locked templates · profile QR applied automatically</small>
      </div>

      <div class="mg-agent-design-stage">
        <article class="mg-agent-print-design is-poster" data-design-canvas>
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