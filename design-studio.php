<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Design Studio | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'agent';
$page_body_class = 'mg-design-studio-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/design-studio.css',
];

require __DIR__ . '/includes/header.php';

$user = mg_current_user();
$canAccessDesignStudio = $user && (
    mg_has_role('merchant')
    || mg_has_permission('merchant.manage')
);

$merchantName = $user ? mg_user_display_name() : 'Merchant';
$merchantEmail = $user ? (string) ($user['email'] ?? '') : '';
$merchantInitial = strtoupper(substr($merchantName !== '' ? $merchantName : 'M', 0, 1));
$merchantHeadline = 'Local rewards and promotional commerce';
$storefrontStatus = 'Not connected';
$storefrontUrl = '';
$profileUrl = '';

if ($user && function_exists('mg_db')) {
    try {
        $pdo = mg_db();
        $accountUserId = (int) ($user['id'] ?? 0);

        if ($accountUserId > 0) {
            $profileStmt = $pdo->prepare("SELECT slug, headline FROM public_profiles WHERE user_id = ? LIMIT 1");
            $profileStmt->execute([$accountUserId]);
            $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($profile)) {
                $profileSlug = trim((string) ($profile['slug'] ?? ''));
                $profileHeadline = trim((string) ($profile['headline'] ?? ''));
                if ($profileHeadline !== '') {
                    $merchantHeadline = $profileHeadline;
                }
                if ($profileSlug !== '') {
                    $profileUrl = '/profile.php?slug=' . rawurlencode($profileSlug) . '&preview=1';
                }
            }

            $storeStmt = $pdo->prepare("SELECT slug, status FROM merchant_storefronts WHERE merchant_user_id = ? LIMIT 1");
            $storeStmt->execute([$accountUserId]);
            $storefront = $storeStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($storefront)) {
                $storeSlug = trim((string) ($storefront['slug'] ?? ''));
                $storefrontStatus = ucfirst((string) ($storefront['status'] ?? 'draft'));
                if ($storeSlug !== '') {
                    $storefrontUrl = '/store.php?s=' . rawurlencode($storeSlug);
                }
            }
        }
    } catch (Throwable) {
        $storefrontStatus = 'Profile sync pending';
    }
}

$defaultQrDestination = $storefrontUrl !== '' ? $storefrontUrl : ($profileUrl !== '' ? $profileUrl : '/store.php');

$designStudioScripts = [
    '/assets/js/microgifter.js',
    '/assets/js/header-signals.js',
    '/assets/js/create-menu.js',
    '/assets/js/auth-state.js',
    '/assets/js/agent-tabs.js',
    '/assets/js/agent-sidebar.js',
    '/assets/js/agent-tools.js',
    '/assets/js/design-studio.js',
];
?>
<section
  class="mg-app-shell mg-design-studio-app"
  data-design-studio-app
  data-merchant-only="<?= $canAccessDesignStudio ? 'true' : 'false' ?>"
  data-qr-api="/api/merchant/qr-library.php"
  data-design-api="/api/merchant/design-studio-assets.php"
  data-default-qr-destination="<?= mg_e($defaultQrDestination) ?>"
>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>

  <section class="mg-design-mobile-lock" aria-labelledby="mg-design-mobile-title">
    <div class="mg-design-mobile-card">
      <span class="mg-design-lock-icon">▣</span>
      <h1 id="mg-design-mobile-title">Design Studio is desktop only.</h1>
      <p>This tool needs a wide canvas for print templates, media controls, QR placement, social post layouts, and export settings. Open it on a desktop or large laptop screen.</p>
      <a class="mg-btn mg-btn-primary" href="/agent.php">Back to workspace</a>
    </div>
  </section>

  <?php if (!$canAccessDesignStudio): ?>
    <div class="mg-app-workspace mg-design-studio-workspace">
      <section class="mg-app-panel mg-design-access-panel">
        <div class="mg-app-panel-head">
          <div>
            <span class="mg-design-eyebrow">Merchant only</span>
            <h1>Design Studio is available to merchant accounts.</h1>
            <p>This workspace creates print-ready and social-ready promotional assets from merchant profile data, media files, saved templates, and real QR codes.</p>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-design-access-grid">
            <article><strong>Required access</strong><span>Merchant role or merchant workspace permission.</span></article>
            <article><strong>Current account</strong><span><?= mg_e($merchantEmail !== '' ? $merchantEmail : 'Signed-in account') ?></span></article>
          </div>
          <div class="mg-design-access-actions">
            <a class="mg-btn mg-btn-primary" href="/merchant-onboarding.php">Open merchant onboarding</a>
            <a class="mg-btn mg-btn-soft" href="/account.php">Account settings</a>
          </div>
        </div>
      </section>
    </div>
  <?php else: ?>
    <div class="mg-app-workspace mg-design-studio-workspace">
      <header class="mg-design-studio-hero">
        <div>
          <span class="mg-design-eyebrow">Merchant Marketing Studio</span>
          <h1>Create print and social promotion assets.</h1>
          <p>Choose a format, save reusable templates, import merchant media, place real QR codes, and generate assets for table tents, flyers, coasters, Instagram posts, stories, Facebook posts, LinkedIn posts, and campaign graphics.</p>
        </div>
        <div class="mg-design-hero-actions">
          <button type="button" class="mg-btn mg-btn-soft" data-design-save>Save project</button>
          <button type="button" class="mg-btn mg-btn-soft" data-design-save-template>Save as template</button>
          <button type="button" class="mg-btn mg-btn-primary" data-design-export>Export package</button>
        </div>
      </header>

      <section class="mg-design-status-strip" aria-label="Design studio status">
        <article><span>Merchant</span><strong><?= mg_e($merchantName) ?></strong></article>
        <article><span>Storefront</span><strong><?= mg_e($storefrontStatus) ?></strong></article>
        <article><span>Saved Templates</span><strong data-template-count>Loading…</strong></article>
        <article><span>QR Library</span><strong>Ready for live codes</strong></article>
      </section>

      <section class="mg-design-layout" aria-label="Design studio workspace">
        <aside class="mg-design-controls" aria-label="Design controls">
          <section class="mg-design-panel">
            <div class="mg-design-panel-head">
              <span>01</span>
              <div><h2>Format</h2><p>Pick a print or social product.</p></div>
            </div>
            <div class="mg-design-format-grid" data-format-options>
              <button type="button" class="is-active" data-template-type="print" data-format="table-tent" data-title="Table Tent" data-size="4 × 6 in folded" data-ratio="portrait" data-print-width="4" data-print-height="6"><strong>Table Tent</strong><span>Counter + table promo</span></button>
              <button type="button" data-template-type="print" data-format="flyer" data-title="Basic Flyer" data-size="8.5 × 11 in" data-ratio="letter" data-print-width="8.5" data-print-height="11"><strong>Basic Flyer</strong><span>Handout + window poster</span></button>
              <button type="button" data-template-type="print" data-format="coaster" data-title="Coaster" data-size="4 × 4 in" data-ratio="square" data-print-width="4" data-print-height="4"><strong>Coaster</strong><span>Bar + event placement</span></button>
              <button type="button" data-template-type="print" data-format="rack-card" data-title="Rack Card" data-size="4 × 9 in" data-ratio="tall" data-print-width="4" data-print-height="9"><strong>Rack Card</strong><span>Front desk takeaway</span></button>
              <button type="button" data-template-type="print" data-format="receipt-insert" data-title="Receipt Insert" data-size="3 × 6 in" data-ratio="slim" data-print-width="3" data-print-height="6"><strong>Receipt Insert</strong><span>Bag + receipt stuffer</span></button>
              <button type="button" data-template-type="social" data-format="instagram-square" data-title="Instagram Post" data-size="1080 × 1080 px" data-ratio="square" data-width-px="1080" data-height-px="1080"><strong>Instagram Post</strong><span>Square social promo</span></button>
              <button type="button" data-template-type="social" data-format="story-reel" data-title="Story / Reel" data-size="1080 × 1920 px" data-ratio="story" data-width-px="1080" data-height-px="1920"><strong>Story / Reel</strong><span>Vertical social creative</span></button>
              <button type="button" data-template-type="social" data-format="facebook-link" data-title="Facebook Post" data-size="1200 × 630 px" data-ratio="wide" data-width-px="1200" data-height-px="630"><strong>Facebook Post</strong><span>Feed + link preview</span></button>
              <button type="button" data-template-type="social" data-format="linkedin-post" data-title="LinkedIn Post" data-size="1200 × 627 px" data-ratio="wide" data-width-px="1200" data-height-px="627"><strong>LinkedIn Post</strong><span>Business promo graphic</span></button>
            </div>
          </section>

          <section class="mg-design-panel">
            <div class="mg-design-panel-head">
              <span>02</span>
              <div><h2>Saved templates</h2><p>Reusable merchant assets.</p></div>
            </div>
            <div class="mg-design-saved-template-list" data-saved-template-list>
              <button type="button" disabled><strong>No saved templates yet</strong><span>Save this canvas as a template.</span></button>
            </div>
          </section>

          <section class="mg-design-panel">
            <div class="mg-design-panel-head">
              <span>03</span>
              <div><h2>Merchant data</h2><p>Loaded from account profile.</p></div>
            </div>
            <div class="mg-design-merchant-card">
              <div class="mg-design-avatar"><?= mg_e($merchantInitial) ?></div>
              <div><strong><?= mg_e($merchantName) ?></strong><span><?= mg_e($merchantHeadline) ?></span></div>
            </div>
            <label>Primary headline<input type="text" value="Give local. Claim instantly." data-design-field="headline"></label>
            <label>Promotion line<textarea rows="3" data-design-field="offer">Scan to unlock today’s featured microgift, local reward, or pre-sale offer.</textarea></label>
            <label>Call to action<input type="text" value="Scan to claim your reward" data-design-field="cta"></label>
          </section>

          <section class="mg-design-panel">
            <div class="mg-design-panel-head">
              <span>04</span>
              <div><h2>Media imports</h2><p>Merchant files, AI, and campaign assets.</p></div>
            </div>
            <div class="mg-design-media-grid" aria-label="Imported media">
              <button type="button" class="is-active" data-media-swatch="gradient"><span></span><strong>Brand gradient</strong></button>
              <button type="button" data-media-swatch="food"><span></span><strong>Product photo</strong></button>
              <button type="button" data-media-swatch="event"><span></span><strong>Event image</strong></button>
              <button type="button" data-media-swatch="logo"><span></span><strong>Logo mark</strong></button>
            </div>
            <button type="button" class="mg-design-link-button" data-design-import-media>Import from media library</button>
            <button type="button" class="mg-design-link-button" data-design-ai-image>Queue AI image concept</button>
          </section>

          <section class="mg-design-panel">
            <div class="mg-design-panel-head">
              <span>05</span>
              <div><h2>QR code library</h2><p>Use real claim and campaign codes.</p></div>
            </div>
            <div class="mg-design-qr-list" data-qr-library>
              <button type="button" class="is-active" data-qr-label="Featured Gift" data-qr-kind="Claim QR"><strong>Featured Gift</strong><span>Claim QR · active</span></button>
              <button type="button" data-qr-label="Newsletter Signup" data-qr-kind="Lead QR"><strong>Newsletter Signup</strong><span>Lead QR · draft</span></button>
              <button type="button" data-qr-label="Contest Entry" data-qr-kind="Campaign QR"><strong>Contest Entry</strong><span>Campaign QR · active</span></button>
            </div>
            <button type="button" class="mg-design-link-button" data-design-create-qr>Create new QR code</button>
          </section>
        </aside>

        <section class="mg-design-canvas-column" aria-label="Template preview">
          <div class="mg-design-canvas-toolbar">
            <div><span data-preview-format-label>Table Tent</span><strong data-preview-size>4 × 6 in folded</strong></div>
            <div class="mg-design-canvas-tools" aria-label="Preview tools">
              <button type="button" class="is-active" data-preview-side="front">Front</button>
              <button type="button" data-preview-side="back">Back</button>
              <button type="button" data-preview-fit>Fit</button>
            </div>
          </div>

          <section class="mg-design-canvas-stage">
            <article class="mg-design-template is-table-tent" data-design-template data-ratio="portrait">
              <div class="mg-design-template-safe-zone">
                <header class="mg-design-template-brand"><span><?= mg_e($merchantName) ?></span><b>MICROGIFT</b></header>
                <div class="mg-design-template-media" data-template-media></div>
                <div class="mg-design-template-copy">
                  <span class="mg-design-template-kicker">LOCAL REWARD</span>
                  <h2 data-template-headline>Give local. Claim instantly.</h2>
                  <p data-template-offer>Scan to unlock today’s featured microgift, local reward, or pre-sale offer.</p>
                </div>
                <div class="mg-design-template-qr-row">
                  <div class="mg-design-template-qr" aria-label="QR code placeholder" data-template-qr><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                  <div><strong data-template-qr-label>Featured Gift</strong><span data-template-qr-kind>Claim QR</span><small data-template-cta>Scan to claim your reward</small><small data-template-qr-payload><?= mg_e($defaultQrDestination) ?></small></div>
                </div>
                <footer><span><?= mg_e($storefrontUrl !== '' ? $storefrontUrl : ($profileUrl !== '' ? $profileUrl : 'microgifter.com')) ?></span><span>Powered by Microgifter</span></footer>
              </div>
            </article>
          </section>

          <div class="mg-design-template-notes">
            <article><strong>Signed template</strong><span>Saved templates receive a signature hash now; admin/template approval can lock production-safe templates later.</span></article>
            <article><strong>Social + print canvas</strong><span>Formats now include print products and basic social posts. AI image jobs are queued for later provider execution.</span></article>
          </div>
        </section>

        <aside class="mg-design-print-options" aria-label="Print and export options">
          <section class="mg-design-panel">
            <div class="mg-design-panel-head"><span>06</span><div><h2>Export setup</h2><p>Production settings.</p></div></div>
            <label>Template source<select data-print-setting="template"><option>Microgifter signed template</option><option>Merchant saved template</option><option>Designer locked template</option></select></label>
            <label>Paper / stock<select data-print-setting="stock"><option>14pt matte card stock</option><option>16pt gloss card stock</option><option>100lb text flyer</option><option>Waterproof coaster stock</option><option>Social PNG export</option></select></label>
            <label>Quantity<select data-print-setting="quantity"><option>Digital only</option><option>25 prints</option><option>50 prints</option><option>100 prints</option><option>250 prints</option><option>500 prints</option></select></label>
            <div class="mg-design-toggle-list">
              <label><input type="checkbox" checked> Include bleed when print format</label>
              <label><input type="checkbox" checked> Add crop marks when print format</label>
              <label><input type="checkbox" checked> Verify QR scan before export</label>
              <label><input type="checkbox"> Lock merchant edits after approval</label>
            </div>
          </section>

          <section class="mg-design-panel">
            <div class="mg-design-panel-head"><span>07</span><div><h2>Asset package</h2><p>Export checklist.</p></div></div>
            <div class="mg-design-export-list">
              <article class="is-ready"><span></span><div><strong>Merchant account data</strong><small><?= mg_e($merchantName) ?></small></div></article>
              <article class="is-ready"><span></span><div><strong>Template format</strong><small data-export-format>Table Tent · 4 × 6 in folded</small></div></article>
              <article class="is-warning"><span></span><div><strong>Saved project</strong><small data-export-project>Not saved yet</small></div></article>
              <article class="is-ready"><span></span><div><strong>QR destination</strong><small data-export-qr>Featured Gift · Claim QR</small></div></article>
              <article class="is-warning"><span></span><div><strong>Print/social proof</strong><small>Needs approval before production</small></div></article>
            </div>
          </section>

          <section class="mg-design-panel mg-design-proof-panel">
            <div><span>Estimated proof</span><strong data-proof-estimate>Ready in studio</strong><p>Generate a print or social package when QR verification, template signing, and merchant approval are complete.</p></div>
            <button type="button" class="mg-btn mg-btn-primary" data-design-proof>Generate proof</button>
          </section>
        </aside>
      </section>
    </div>
  <?php endif; ?>
</section>
</main>
<?php foreach (array_unique($designStudioScripts) as $script): ?><script src="<?= mg_e($script) ?>" defer></script><?php endforeach; ?>
</body>
</html>
