<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-lqc-heading">
  <div>
    <span class="mg-eyebrow">Loyalty Quest Creative Studio</span>
    <h1>Build campaign-ready quest media.</h1>
    <p>Select merchant-owned artwork, generate a scannable quest QR, download promotional formats, and copy a host-friendly website embed.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-soft" href="/merchant-loyalty-quests.php">Manage quests</a>
    <a class="mg-btn mg-btn-ghost" href="/merchant-media.php">Open media library</a>
  </div>
</section>

<section class="mg-app-panel mg-lqc-shell" data-lqc-studio data-initial-campaign="<?= mg_e((string)($_GET['campaign'] ?? '')) ?>">
  <div class="mg-app-panel-head mg-lqc-command-head">
    <div>
      <span class="mg-eyebrow">Campaign Creative</span>
      <h2>Choose a Loyalty Quest</h2>
    </div>
    <label class="mg-lqc-campaign-select"><span>Loyalty Quest</span><select data-lqc-campaign><option value="">Select a quest</option></select></label>
  </div>
  <div class="mg-app-panel-body">
    <div class="mg-form-status" data-lqc-status role="status" aria-live="polite">Loading Loyalty Quests…</div>
    <div class="mg-lqc-empty" data-lqc-empty hidden><h3>Create a Loyalty Quest first.</h3><p>Creative tools become available after a merchant campaign exists.</p><a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php#campaign-create">Create Loyalty Quest</a></div>
    <div class="mg-lqc-workspace" data-lqc-workspace hidden>
      <section class="mg-lqc-editor">
        <form data-lqc-form>
          <input type="hidden" name="campaign_id">
          <div class="mg-lqc-editor-head"><div><span class="mg-eyebrow">Quest Artwork</span><h2 data-lqc-title>Loyalty Quest</h2><p data-lqc-merchant></p></div><span class="mg-lq-status" data-lqc-state>Draft</span></div>

          <div class="mg-lqc-upload-box">
            <div><strong>Upload quest cover</strong><p>JPG, PNG, or WebP. Maximum 8MB. Uploaded files remain merchant-owned catalog assets.</p></div>
            <label class="mg-btn mg-btn-soft">Choose image<input type="file" accept="image/jpeg,image/png,image/webp" data-lqc-upload hidden></label>
          </div>

          <fieldset class="mg-lqc-media-fieldset">
            <legend>Media library</legend>
            <div class="mg-lqc-media-grid" data-lqc-assets></div>
          </fieldset>

          <div class="mg-grid-2">
            <label>External HTTPS image URL<input name="cover_url" type="url" maxlength="700" placeholder="https://…"></label>
            <label>Image alt text<input name="image_alt" maxlength="240" placeholder="Describe the quest image"></label>
          </div>
          <div class="mg-grid-2">
            <label>Promotional headline<input name="headline" maxlength="180" required></label>
            <label>Call to action<input name="cta" maxlength="80" value="Start Loyalty Quest"></label>
          </div>
          <div class="mg-grid-2">
            <label>Terms line<input name="terms" maxlength="500" value="Terms and availability apply."></label>
            <label>Accent color<input name="accent" type="color" value="#111827"></label>
          </div>
          <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="submit" data-lqc-save>Save creative</button><a class="mg-btn mg-btn-soft" data-lqc-public target="_blank" rel="noopener">Open public quest</a></div>
        </form>
      </section>

      <section class="mg-lqc-preview-panel">
        <div class="mg-lqc-preview-head"><div><span class="mg-eyebrow">Promotional Assets</span><h2>Preview and download</h2></div><label>Format<select data-lqc-format><option value="social">Social Square · 1080×1080</option><option value="story">Story · 1080×1920</option><option value="poster">Poster · 1200×1800</option><option value="table_tent">Table Tent · 1200×1500</option><option value="email">Email Banner · 1200×600</option></select></label></div>
        <div class="mg-lqc-canvas-wrap"><canvas data-lqc-canvas width="1080" height="1080" aria-label="Loyalty Quest promotional artwork preview"></canvas></div>
        <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="button" data-lqc-download>Download PNG</button><a class="mg-btn mg-btn-soft" data-lqc-qr-download>Download QR SVG</a><button class="mg-btn mg-btn-ghost" type="button" data-lqc-copy-url>Copy quest URL</button></div>
        <p class="mg-muted">For reliable PNG downloads, use an uploaded media-library image. External images must permit cross-origin canvas access.</p>

        <div class="mg-lqc-embed-card">
          <span class="mg-eyebrow">Website Embed</span><h3>Host-friendly quest card</h3><p>The embed adds semantic HTML to the merchant website without Shadow DOM, so the host page can restyle the card.</p>
          <textarea readonly rows="7" data-lqc-embed aria-label="Loyalty Quest embed code"></textarea>
          <button class="mg-btn mg-btn-soft" type="button" data-lqc-copy-embed>Copy embed code</button>
        </div>
      </section>
    </div>
  </div>
</section>
