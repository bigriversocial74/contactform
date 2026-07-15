<?php
declare(strict_types=1);
?>
<div class="hgm-page" data-merchant-hosted-games data-csrf="<?= mg_e(mg_csrf_token()) ?>">
  <section class="hgm-hero">
    <div>
      <span class="hgm-eyebrow">Developer API · Hosted games</span>
      <h1>Upload a game.<br>Connect a distribution.</h1>
      <p>Host simple HTML games, WebGL builds, audio games, or complex browser experiences. Microgifter supplies the secure player login, isolated game database, Distribution Program connection, reward issuance, Inbox delivery, and release management.</p>
    </div>
    <button class="hgm-btn is-primary" type="button" data-hgm-create>+ Create hosted game</button>
  </section>

  <div class="hgm-notice" data-hgm-notice hidden></div>

  <section class="hgm-stats" aria-label="Hosted game totals">
    <article class="hgm-stat"><span>Total games</span><strong data-hgm-stat="total">0</strong></article>
    <article class="hgm-stat"><span>Published</span><strong data-hgm-stat="active">0</strong></article>
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
    <p>Create the game record, upload the ZIP, connect its Distribution Program, then ask Microgifter Admin to verify the isolated game database.</p>
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
          <h3>Game identity</h3>
          <p>Create the permanent game record and public URL. Gameplay rules, scoring, levels, and qualification remain inside the uploaded game.</p>
          <form class="hgm-form" data-hgm-identity-form>
            <input type="hidden" name="game_id">
            <div class="hgm-form-grid">
              <label>Game name<input name="name" maxlength="180" required placeholder="Pizza Catcher"></label>
              <label>Public URL slug<input name="slug" maxlength="140" required placeholder="pizza-catcher"><span class="hgm-help">Published at /games/your-slug/</span></label>
            </div>
            <label>Description<textarea name="description" maxlength="5000" placeholder="Describe the game experience and reward opportunity."></textarea></label>
            <label>Cover image URL<input name="cover_url" type="url" maxlength="500" placeholder="https://..."></label>
            <div><button class="hgm-btn is-primary" type="submit">Save game identity</button></div>
            <div class="hgm-form-status" data-hgm-identity-status></div>
          </form>
        </section>

        <section class="hgm-section" data-hgm-release-section>
          <h3>Game ZIP and releases</h3>
          <p>Upload a complete game package containing <strong>index.html</strong>, or include a small <strong>game.json</strong> file that declares another HTML entry point. New uploads become the current release automatically.</p>
          <label class="hgm-drop" data-hgm-drop>
            <input type="file" name="game_zip" accept=".zip,application/zip" data-hgm-file>
            <strong data-hgm-file-title>Select a game ZIP</strong>
            <span data-hgm-file-detail>HTML, CSS, JavaScript, images, audio, video, WebGL, WASM, and game assets · maximum 100 MB ZIP</span>
          </label>
          <div class="hgm-progress" aria-hidden="true"><i data-hgm-progress></i></div>
          <div class="hgm-card-actions"><button class="hgm-btn is-primary" type="button" data-hgm-upload disabled>Upload game release</button></div>
          <div class="hgm-form-status" data-hgm-upload-status></div>
          <div class="hgm-code" data-hgm-release-summary hidden></div>
        </section>

        <section class="hgm-section" data-hgm-integration-section>
          <h3>Distribution integration</h3>
          <p>Select the Distribution Program only. Microgifter automatically applies its connected campaign and active reward inventory, then creates the encrypted live Developer App credential and signed webhook.</p>
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
          <h3>Readiness and publishing</h3>
          <p>The game can publish after the ZIP, Distribution Program integration, and the main-admin isolated database connection are all ready.</p>
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
