<?php
declare(strict_types=1);

$designMerchantName = $displayName !== '' ? $displayName : 'Your Business';
$designDestination = '/profile.php';
$isStandaloneDesign = !empty($designStudioStandalone);
$includeDesignCalendar = !isset($designStudioIncludeCalendar) || (bool) $designStudioIncludeCalendar;
try {
    $designUserId = (int) (($user['id'] ?? 0));
    if ($designUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare('SELECT slug FROM public_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$designUserId]);
        $slug = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($slug !== '') {
            $designDestination = '/profile.php?slug=' . rawurlencode($slug);
        }
    }
} catch (Throwable) {
    $designDestination = '/profile.php';
}
?>
<section class="mg-personal-agent-view mg-agent-design-view"
         data-personal-agent-view="design"
         <?= $isStandaloneDesign ? '' : 'hidden' ?>
         data-agent-design-studio
         data-default-destination="<?= mg_e($designDestination) ?>"
         data-merchant-name="<?= mg_e($designMerchantName) ?>">
  <header class="mg-agent-design-modebar">
    <span class="mg-agent-design-eyebrow">Merchant Design Studio</span>
    <nav class="mg-agent-design-mode-tabs" aria-label="Design type" role="tablist">
      <button type="button" class="is-active" role="tab" aria-selected="true" data-design-mode="print">Print</button>
      <button type="button" role="tab" aria-selected="false" data-design-mode="social">Social Media</button>
      <?php if ($includeDesignCalendar): ?>
        <button type="button" role="tab" aria-selected="false" data-calendar-mode-button>Calendar</button>
      <?php endif; ?>
    </nav>
  </header>

  <section class="mg-agent-design-mode-panel" data-design-mode-panel="print">
    <section class="mg-agent-design-object-picker" data-design-object-picker aria-label="Print formats">
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
      <aside class="mg-agent-design-rail" aria-label="Print template and export controls">
        <button type="button" class="mg-agent-design-back" data-design-back><span aria-hidden="true">←</span> All print formats</button>

        <section class="mg-agent-design-object-summary">
          <span class="mg-agent-design-step">Selected format</span>
          <strong data-design-object-label>5 × 5 Poster Card</strong>
          <small data-design-object-detail>Square counter or window display</small>
        </section>

        <section class="mg-agent-design-template-panel">
          <span class="mg-agent-design-step">Template</span>
          <h2>Choose a layout</h2>
          <div class="mg-agent-template-picker">
            <button type="button" data-design-template="support-local" data-template-formats="poster,tent">
              <span class="mg-agent-template-thumbnail is-support-local" aria-hidden="true"><span>Reward yourself.</span><em>Support local.</em><i></i></span>
              <span><strong>Support Local</strong><small>Profile QR + local rewards</small></span>
            </button>
            <button type="button" data-design-template="gift-better" data-template-formats="poster,tent">
              <span class="mg-agent-template-thumbnail is-gift-better" aria-hidden="true"><span>Give local.</span><em>Gift better.</em><i></i></span>
              <span><strong>Gift Better</strong><small>Local gifting callout</small></span>
            </button>
            <button type="button" data-design-template="reward-visit" data-template-formats="poster,tent">
              <span class="mg-agent-template-thumbnail is-reward-visit" aria-hidden="true"><span>Scan. Save.</span><em>Come back.</em><i></i></span>
              <span><strong>Reward the Visit</strong><small>Return-visit reward prompt</small></span>
            </button>
            <button type="button" data-design-template="local-favorite" data-template-formats="poster,tent">
              <span class="mg-agent-template-thumbnail is-local-favorite" aria-hidden="true"><span>Your next</span><em>local favorite.</em><i></i></span>
              <span><strong>Local Favorite</strong><small>Discovery-first profile QR</small></span>
            </button>
          </div>
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
            <span>Print workspace</span>
            <strong data-design-format-label>5 × 5 Poster Card</strong>
          </div>
          <small data-design-template-label>No template selected</small>
        </div>

        <div class="mg-agent-design-stage">
          <div class="mg-agent-design-empty" data-design-empty>
            <span class="mg-agent-design-empty-icon" aria-hidden="true">+</span>
            <strong data-design-empty-title>Choose a template</strong>
            <p data-design-empty-copy>Select a template from the side rail to place it on your poster card.</p>
          </div>

          <article class="mg-agent-print-design is-poster template-support-local" data-design-canvas data-design-template-canvas hidden>
            <div class="mg-agent-print-face mg-agent-print-front">
              <header class="mg-agent-print-brand">
                <img src="/images/logo_main_drk.png" alt="Microgifter">
              </header>
              <div class="mg-agent-print-copy">
                <h2 data-design-template-headline>Reward yourself.<br><em>Support local.</em></h2>
                <p data-design-template-copy>Scan to earn rewards and discover local gifts.</p>
              </div>
              <footer class="mg-agent-print-qr-band">
                <div class="mg-agent-print-qr" data-design-qr aria-label="Merchant profile QR code"></div>
                <div><strong><?= mg_e($designMerchantName) ?></strong><span data-design-template-qr-copy>Scan to visit our Microgifter profile.</span></div>
              </footer>
            </div>

            <div class="mg-agent-print-face mg-agent-print-back" aria-hidden="true">
              <span class="mg-agent-quote-mark">“</span>
              <blockquote data-design-template-back-copy>Discover local gifts, rewards, and experiences from <?= mg_e($designMerchantName) ?>.</blockquote>
              <div class="mg-agent-quote-byline"><strong><?= mg_e($designMerchantName) ?></strong><span>Powered by Microgifter</span></div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-agent-design-mode-panel" data-design-mode-panel="social" hidden>
    <div class="mg-agent-social-workspace">
      <aside class="mg-agent-social-rail" aria-label="Social media design controls">
        <section class="mg-agent-social-control">
          <span class="mg-agent-design-step">Product</span>
          <label class="mg-agent-social-field">
            <span>Select a merchant product</span>
            <select data-social-product-select aria-label="Select a merchant product">
              <option value="">Loading products…</option>
            </select>
          </label>
          <button type="button" class="mg-agent-social-refresh" data-social-refresh>
            <span aria-hidden="true">↻</span> Refresh product
          </button>
          <small data-social-product-meta>Most recently updated product loads first.</small>
        </section>

        <section class="mg-agent-social-control">
          <span class="mg-agent-design-step">Post type</span>
          <div class="mg-agent-social-choice-grid" data-social-format-picker>
            <button type="button" class="is-active" data-social-format="square"><strong>Post</strong><small>1:1 · 1080 × 1080</small></button>
            <button type="button" data-social-format="portrait"><strong>Portrait</strong><small>4:5 · 1080 × 1350</small></button>
            <button type="button" data-social-format="story"><strong>Reel / Story</strong><small>9:16 · 1080 × 1920</small></button>
          </div>
        </section>

        <section class="mg-agent-social-control">
          <span class="mg-agent-design-step">Ad layout</span>
          <div class="mg-agent-social-layout-picker" data-social-layout-picker>
            <button type="button" class="is-active" data-social-layout="spotlight"><span class="mg-agent-layout-swatch is-spotlight"></span><strong>Spotlight</strong></button>
            <button type="button" data-social-layout="split"><span class="mg-agent-layout-swatch is-split"></span><strong>Split Feature</strong></button>
            <button type="button" data-social-layout="bold"><span class="mg-agent-layout-swatch is-bold"></span><strong>Bold Offer</strong></button>
          </div>
        </section>

        <div class="mg-agent-social-actions">
          <button type="button" class="mg-btn mg-btn-soft" data-social-download disabled>Download JPG</button>
          <button type="button" class="mg-btn mg-btn-primary" data-social-post-feed disabled>Post to Feed</button>
          <span data-social-status role="status" aria-live="polite">Loading your latest product…</span>
        </div>
      </aside>

      <div class="mg-agent-social-preview-column">
        <div class="mg-agent-design-preview-toolbar">
          <div>
            <span>Social media workspace</span>
            <strong data-social-format-label>Post · 1:1</strong>
          </div>
          <small data-social-layout-label>Spotlight layout</small>
        </div>

        <div class="mg-agent-social-stage">
          <div class="mg-agent-social-loading" data-social-loading>
            <span class="mg-agent-design-empty-icon" aria-hidden="true">↻</span>
            <strong>Loading merchant products</strong>
            <p>Your most recently updated product will appear here.</p>
          </div>

          <article class="mg-agent-social-canvas is-square layout-spotlight" data-social-canvas hidden>
            <div class="mg-agent-social-photo">
              <img data-social-product-image alt="">
              <div class="mg-agent-social-photo-placeholder" data-social-photo-placeholder>
                <img src="/images/logo_main_drk.png" alt="">
              </div>
            </div>
            <div class="mg-agent-social-shade"></div>
            <header class="mg-agent-social-brand">
              <span class="mg-agent-social-avatar" data-social-avatar-wrap>
                <img data-social-profile-image alt="">
                <span data-social-profile-fallback><?= mg_e(mb_strtoupper(mb_substr($designMerchantName, 0, 1))) ?></span>
              </span>
              <span><strong data-social-merchant-name><?= mg_e($designMerchantName) ?></strong><small>Available on Microgifter</small></span>
            </header>
            <div class="mg-agent-social-copy">
              <span class="mg-agent-social-kicker">Support local. Gift better.</span>
              <h2 data-social-product-title>Choose a product</h2>
              <p data-social-product-description>Product details will load from your merchant catalog.</p>
              <strong class="mg-agent-social-price" data-social-product-price></strong>
            </div>
            <footer class="mg-agent-social-footer">
              <span data-social-cta-label>Shop this product</span>
              <img src="/images/logo_main_drk.png" alt="Microgifter">
            </footer>
          </article>
        </div>
      </div>
    </div>
  </section>

  <?php if ($includeDesignCalendar): ?>
    <?php require __DIR__ . '/workspace-design-calendar.php'; ?>
  <?php endif; ?>
</section>