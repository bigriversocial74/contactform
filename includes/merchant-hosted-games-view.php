<?php
declare(strict_types=1);
?>
<div class="hgm-page" data-merchant-hosted-games data-csrf="<?= mg_e(mg_csrf_token()) ?>">
  <section class="hgm-hero">
    <div>
      <span class="hgm-eyebrow">Developer API · Hosted games</span>
      <h1>Upload a game.<br>Connect a distribution.</h1>
      <p>Host simple HTML games, WebGL builds, audio games, or complex browser experiences. Microgifter supplies the secure player login, isolated game database, Distribution Program connection, managed API credential, signed webhook, reward issuance, Inbox delivery, cover-image hosting, and release management.</p>
    </div>
    <div class="hgm-card-actions">
      <a class="hgm-btn is-soft" href="/api/hosted-games/demo-package.php">Download demo game ZIP</a>
      <button class="hgm-btn is-primary" type="button" data-hgm-create>+ Create hosted game</button>
    </div>
  </section>

  <div class="hgm-notice" data-hgm-notice hidden></div>

  <section class="hgm-stats" aria-label="Hosted game totals">
    <article class="hgm-stat"><span>Total games</span><strong data-hgm-stat="total">0</strong></article>
    <article class="hgm-stat"><span>Enabled</span><strong data-hgm-stat="active">0</strong></article>
    <article class="hgm-stat"><span>Total plays</span><strong data-hgm-stat="plays">0</strong></article>
    <article class="hgm-stat"><span>Rewards delivered</span><strong data-hgm-stat="delivered">0</strong></article>
    <article class="hgm-stat"><span>Needs setup</span><strong data-hgm-stat="pending">0</strong></article>
  </section>

  <div class="hgm-toolbar">
    <h2>Your hosted games</h2>
    <input type="search" placeholder="Search games, campaigns, programs, or rewards" data-hgm-search>
  </div>

  <section class="hgm-grid" data-hgm-grid></section>
  <section class="hgm-empty" data-hgm-empty hidden>
    <h3>No hosted games yet</h3>
    <p>Create the game record, upload its cover and ZIP, connect its Distribution Program, then ask Microgifter Admin to verify the isolated game database.</p>
  </section>

  <div class="hgm-modal" data-hgm-modal hidden>
    <div class="hgm-modal-card">
      <header class="hgm-modal-head">
        <h2 data-hgm-modal-title>Create hosted game</h2>
        <button class="hgm-modal-close" type="button" aria-label="Close" data-hgm-close>×</button>
      </header>
      <div class="hgm-modal-body">
        <div class="hgm-step-tabs" aria-hidden="true">
          <div class="hgm-step-tab is-active" data-hgm-step-indicator="identity">1. Game identity</div>
          <div class="hgm-step-tab" data-hgm-step-indicator="release">2. Upload ZIP</div>
          <div class="hgm-step-tab" data-hgm-step-indicator="integration">3. Distribution</div>
        </div>

        <section class="hgm-section">
          <h3>Game identity and cover</h3>
          <p>Create the permanent game record, public URL, and standardized cover image. Gameplay rules, scoring, levels, and qualification remain inside the uploaded game.</p>
          <form class="hgm-form" data-hgm-identity-form>
            <input type="hidden" name="game_id">
            <div class="hgm-form-grid">
              <label>Game name<input name="name" maxlength="180" required placeholder="Pizza Catcher"></label>
              <label>Public URL slug<input name="slug" maxlength="140" required placeholder="pizza-catcher"><span class="hgm-help">Published at /games/your-slug/</span></label>
            </div>
            <label>Description<textarea name="description" maxlength="5000" placeholder="Describe the game experience and reward opportunity."></textarea></label>
            <label>Cover image URL <span class="hgm-help">Optional external-image fallback</span><input name="cover_url" type="url" maxlength="500" placeholder="https://..."></label>
            <div class="hgm-cover-uploader" data-hgm-cover-uploader>
              <div class="hgm-cover-preview" data-hgm-cover-preview>
                <img alt="Hosted game cover preview" hidden>
                <span>16:9 game cover preview<br>Recommended: 1600 × 900</span>
              </div>
              <div class="hgm-cover-controls">
                <label class="hgm-cover-file">
                  <strong>Upload cover image</strong>
                  <small>JPEG, PNG, or WebP · minimum 640 × 360 · maximum 10 MB</small>
                  <input type="file" accept="image/jpeg,image/png,image/webp" data-hgm-cover-file>
                </label>
                <div class="hgm-cover-progress" aria-hidden="true"><span data-hgm-cover-progress></span></div>
                <div class="hgm-cover-actions"><button class="hgm-btn is-soft" type="button" data-hgm-cover-upload disabled>Upload selected image</button></div>
                <div class="hgm-cover-status" data-hgm-cover-status></div>
              </div>
            </div>
            <div><button class="hgm-btn is-primary" type="submit">Save game identity</button></div>
            <div class="hgm-form-status" data-hgm-identity-status></div>
          </form>
        </section>

        <section class="hgm-section" data-hgm-release-section>
          <h3>Game ZIP and releases</h3>
          <p>Upload a complete game package containing <strong>index.html</strong>, or include a Standard v1 <strong>game.json</strong> manifest. New uploads become validated drafts; open Releases to preview, health-check, compare, and activate them.</p>
          <label class="hgm-drop" data-hgm-drop>
            <input type="file" name="game_zip" accept=".zip,application/zip" data-hgm-file>
            <strong data-hgm-file-title>Select a game ZIP</strong>
            <span data-hgm-file-detail>HTML, CSS, JavaScript, images, audio, video, WebGL, WASM, and game assets · maximum 100 MB ZIP</span>
          </label>
          <div class="hgm-progress" aria-hidden="true"><i data-hgm-progress></i></div>
          <div class="hgm-card-actions"><button class="hgm-btn is-primary" type="button" data-hgm-upload disabled>Upload draft release</button></div>
          <div class="hgm-form-status" data-hgm-upload-status></div>
          <div class="hgm-code" data-hgm-release-summary hidden></div>
        </section>

        <section class="hgm-section" data-hgm-integration-section>
          <h3>Distribution integration</h3>
          <p>Select the Distribution Program only. Microgifter applies its connected campaign and active reward inventory, then creates and encrypts the live Developer App credential, webhook secret, and state secret automatically. No per-game environment values are required.</p>
          <form class="hgm-form" data-hgm-integration-form>
            <input type="hidden" name="game_id">
            <select name="campaign_id" aria-hidden="true" tabindex="-1" style="display:none!important"><option value=""></option></select>
            <select name="reward_template_id" aria-hidden="true" tabindex="-1" style="display:none!important"><option value=""></option></select>
            <label>Distribution Program<select name="program_id" required><option value="">Select a program</option></select></label>
            <div><button class="hgm-btn is-primary" type="submit">Configure game integration</button></div>
            <div class="hgm-form-status" data-hgm-integration-status></div>
          </form>
        </section>

        <section class="hgm-section">
          <h3>Managed runtime</h3>
          <p>After a release is activated, the Distribution Program integration is ready, and the isolated database is verified, use the Game enabled switch. Disabling pauses gameplay and reward issuance without deleting the release or rotating credentials.</p>
          <div class="hgm-ready-list" data-hgm-modal-readiness></div>
          <div class="hgm-card-actions">
            <button class="hgm-btn is-success" type="button" data-hgm-publish disabled>Publish game</button>
            <a class="hgm-btn is-soft" href="#" target="_blank" rel="noopener" data-hgm-preview hidden>Open live game</a>
          </div>
          <div class="hgm-form-status" data-hgm-publish-status></div>
        </section>
      </div>
    </div>
  </div>
</div>
