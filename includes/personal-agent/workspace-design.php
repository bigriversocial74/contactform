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
<section class="mg-personal-agent-view mg-agent-design-view" data-personal-agent-view="design"<?= $activeView === 'design' ? '' : ' hidden' ?> data-agent-design-studio data-default-destination="<?= mg_e($designDestination) ?>">
  <header class="mg-agent-design-header">
    <div>
      <span>Merchant Design Studio</span>
      <h1>Create branded displays in minutes.</h1>
      <p>Choose a pre-designed Microgifter template, add your merchant QR code, and download a print-ready JPG.</p>
    </div>
    <button type="button" class="mg-btn mg-btn-primary" data-design-download>Download JPG</button>
  </header>

  <div class="mg-agent-design-shell">
    <aside class="mg-agent-design-controls" aria-label="Design controls">
      <section>
        <span class="mg-agent-design-step">01</span>
        <h2>Choose a format</h2>
        <div class="mg-agent-template-picker">
          <button type="button" class="is-active" data-design-format="poster"><strong>5 × 5 Poster Card</strong><small>Square counter or window display</small></button>
          <button type="button" data-design-format="tent"><strong>Table Tent</strong><small>Folded countertop display</small></button>
        </div>
      </section>

      <section>
        <span class="mg-agent-design-step">02</span>
        <h2>Merchant details</h2>
        <label>Business name<input type="text" value="<?= mg_e($designMerchantName) ?>" data-design-field="merchant"></label>
        <label>Headline<input type="text" value="Reward yourself. Support local." maxlength="80" data-design-field="headline"></label>
        <label>Support text<textarea rows="3" maxlength="180" data-design-field="support">Scan to earn rewards and discover local gifts.</textarea></label>
      </section>

      <section>
        <span class="mg-agent-design-step">03</span>
        <h2>Merchant QR code</h2>
        <label>Destination URL<input type="url" value="<?= mg_e($designDestination) ?>" data-design-field="destination"></label>
        <button type="button" class="mg-btn mg-btn-soft" data-design-generate-qr>Generate QR code</button>
        <p class="mg-agent-design-help">The QR code is created for this merchant account and placed automatically in both templates.</p>
      </section>

      <section class="mg-agent-design-download-card">
        <strong>Ready to print</strong>
        <span>Your download includes the selected template, Microgifter branding, merchant name, and QR code.</span>
        <button type="button" class="mg-btn mg-btn-primary" data-design-download>Download JPG</button>
      </section>
    </aside>

    <div class="mg-agent-design-preview-column">
      <div class="mg-agent-design-preview-toolbar">
        <div><span>Live preview</span><strong data-design-format-label>5 × 5 Poster Card</strong></div>
        <small>Simple pre-designed template · no freeform editing</small>
      </div>

      <div class="mg-agent-design-stage">
        <article class="mg-agent-print-design is-poster" data-design-canvas>
          <div class="mg-agent-print-face mg-agent-print-front">
            <header class="mg-agent-print-brand"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>MICROGIFTER</span></header>
            <div class="mg-agent-print-copy">
              <h2 data-design-preview="headline">Reward yourself.<br><em>Support local.</em></h2>
              <p data-design-preview="support">Scan to earn rewards and discover local gifts.</p>
            </div>
            <footer class="mg-agent-print-qr-band">
              <div class="mg-agent-print-qr" data-design-qr aria-label="Merchant QR code"></div>
              <div><strong data-design-preview="merchant"><?= mg_e($designMerchantName) ?></strong><span>Scan to discover, gift, and earn.</span></div>
            </footer>
          </div>

          <div class="mg-agent-print-face mg-agent-print-back" aria-hidden="true">
            <span class="mg-agent-quote-mark">“</span>
            <blockquote>Microgifter helps local customers discover what makes our business worth sharing.</blockquote>
            <div class="mg-agent-quote-byline"><strong data-design-preview="merchant"><?= mg_e($designMerchantName) ?></strong><span>Independent merchant</span></div>
          </div>
        </article>
      </div>

      <div class="mg-agent-design-note-row">
        <article><strong>Merchant assets included</strong><span>Business name, Microgifter branding, destination URL, and merchant QR code are applied automatically.</span></article>
        <article><strong>Download now</strong><span>JPG export is designed for local printing, counter displays, windows, and tabletop use.</span></article>
      </div>
    </div>
  </div>
</section>