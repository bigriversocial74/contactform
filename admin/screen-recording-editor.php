<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/admin-screen-recording-stage3.php';

$user = mg_require_admin_page_key('admin.screen_recordings');
$canManageRecordings = mg_screen_recordings_user_can_manage($user);
$recordingId = max(0, (int)($_GET['id'] ?? 0));
$csrfToken = mg_csrf_token();
$page_title = 'Screen Recording Editor | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-screen-recording-editor-page';
$page_styles = ['/assets/css/admin-shell.css', '/assets/css/admin-screen-recording-editor.css'];
$page_scripts = ['/assets/js/admin-screen-recording-editor.js'];
$adminActive = 'screen-recordings';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-recording-editor-shell" data-recording-editor data-recording-id="<?= (int)$recordingId ?>" data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManageRecordings ? '1' : '0' ?>">
      <header class="mg-recording-editor-topbar">
        <div>
          <a href="/admin/screen-recordings.php">← Recordings</a>
          <span class="mg-eyebrow">Renderer, voiceover & tutorials</span>
          <h1 data-editor-title>Screen recording editor</h1>
        </div>
        <div class="mg-recording-editor-actions">
          <button class="mg-btn mg-btn-soft" type="button" data-editor-download-original>Download original</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-editor-save-draft <?= $canManageRecordings ? '' : 'disabled' ?>>Save draft</button>
          <button class="mg-btn mg-btn-primary" type="button" data-editor-export <?= $canManageRecordings ? '' : 'disabled' ?>>Render export</button>
        </div>
      </header>

      <main class="mg-recording-editor-main">
        <section class="mg-recording-preview-card">
          <div class="mg-recording-preview-stage" data-preview-stage>
            <video data-editor-video controls playsinline></video>
            <div class="mg-recording-overlay-layer" data-overlay-layer aria-hidden="true"></div>
          </div>
          <div class="mg-editor-status" data-editor-status role="status" aria-live="polite">Loading recording…</div>
        </section>

        <aside class="mg-recording-tools-panel">
          <nav class="mg-recording-tool-tabs" aria-label="Editor tools">
            <button type="button" class="is-active" data-tool-tab="trim">Trim</button>
            <button type="button" data-tool-tab="text">Text</button>
            <button type="button" data-tool-tab="audio">Audio</button>
            <button type="button" data-tool-tab="export">Export</button>
            <button type="button" data-tool-tab="tutorial">Tutorial</button>
          </nav>

          <section class="mg-recording-tool-panel is-active" data-tool-panel="trim">
            <h2>Timeline trim</h2>
            <label>Trim start <input type="number" min="0" step="0.1" data-trim-start value="0"></label>
            <label>Trim end <input type="number" min="0" step="0.1" data-trim-end placeholder="Video end"></label>
            <button class="mg-btn mg-btn-soft" type="button" data-set-trim-start>Set start at playhead</button>
            <button class="mg-btn mg-btn-soft" type="button" data-set-trim-end>Set end at playhead</button>
            <button class="mg-btn mg-btn-ghost" type="button" data-split-at-playhead>Add split marker</button>
            <div class="mg-editor-mini-list" data-segment-list></div>
          </section>

          <section class="mg-recording-tool-panel" data-tool-panel="text" hidden>
            <h2>Text overlay</h2>
            <p>Text can appear anywhere on screen during any time range. Drag the overlay in the video preview to position it.</p>
            <label>Text <textarea data-overlay-text maxlength="500" placeholder="Type overlay text"></textarea></label>
            <div class="mg-overlay-grid-fields">
              <label>Start <input type="number" min="0" step="0.1" data-overlay-start value="0"></label>
              <label>End <input type="number" min="0" step="0.1" data-overlay-end value="5"></label>
              <label>X % <input type="number" min="0" max="100" step="0.1" data-overlay-x value="50"></label>
              <label>Y % <input type="number" min="0" max="100" step="0.1" data-overlay-y value="50"></label>
              <label>Size <input type="number" min="10" max="120" step="1" data-overlay-size value="28"></label>
              <label>Color <input type="color" data-overlay-color value="#ffffff"></label>
              <label>Background <input type="color" data-overlay-background value="#111827"></label>
              <label>Align <select data-overlay-align><option value="center">Center</option><option value="left">Left</option><option value="right">Right</option></select></label>
            </div>
            <div class="mg-recording-editor-actions is-local">
              <button class="mg-btn mg-btn-primary" type="button" data-overlay-add>Add overlay</button>
              <button class="mg-btn mg-btn-soft" type="button" data-overlay-use-playhead>Use playhead time</button>
            </div>
            <div class="mg-editor-mini-list" data-overlay-list></div>
          </section>

          <section class="mg-recording-tool-panel" data-tool-panel="audio" hidden>
            <h2>Voiceover & audio mix</h2>
            <p>Record a voiceover in the browser or upload an audio file, then choose whether to mix it with the original recording audio or replace the original audio.</p>
            <div class="mg-recording-editor-actions is-local">
              <button class="mg-btn mg-btn-soft" type="button" data-voiceover-start <?= $canManageRecordings ? '' : 'disabled' ?>>Record voiceover</button>
              <button class="mg-btn mg-btn-ghost" type="button" data-voiceover-stop disabled>Stop & upload</button>
            </div>
            <label>Upload audio file <input type="file" accept="audio/*" data-audio-file <?= $canManageRecordings ? '' : 'disabled' ?>></label>
            <div class="mg-overlay-grid-fields">
              <label>Voiceover start <input type="number" min="0" step="0.1" data-audio-start value="0"></label>
              <label>Voiceover volume <input type="number" min="0" max="3" step="0.1" data-voiceover-volume value="1"></label>
              <label>Original volume <input type="number" min="0" max="3" step="0.1" data-original-volume value="1"></label>
              <label>Include audio <select data-include-audio><option value="1">Yes</option><option value="0">No / silent export</option></select></label>
            </div>
            <label class="mg-checkbox-line"><input type="checkbox" data-mute-original-audio> Replace original audio with voiceover</label>
            <div class="mg-editor-mini-list" data-audio-list></div>
          </section>

          <section class="mg-recording-tool-panel" data-tool-panel="export" hidden>
            <h2>Rendered export</h2>
            <label>Format <select data-export-format><option value="webm">WebM</option><option value="mp4">MP4</option></select></label>
            <label class="mg-checkbox-line"><input type="checkbox" data-export-burn-overlays checked> Burn text overlays into exported video</label>
            <div class="mg-recording-editor-actions is-local">
              <button class="mg-btn mg-btn-primary" type="button" data-editor-export-panel <?= $canManageRecordings ? '' : 'disabled' ?>>Render export</button>
              <button class="mg-btn mg-btn-soft" type="button" data-process-export <?= $canManageRecordings ? '' : 'disabled' ?>>Process latest job</button>
            </div>
            <div class="mg-editor-job-status" data-export-job-status>No export job yet.</div>
            <div class="mg-editor-mini-list" data-export-job-list></div>
            <p class="mg-editor-note">Server-side rendering requires FFmpeg. If production does not expose FFmpeg, the export job will fail safely and diagnostics will show why.</p>
          </section>

          <section class="mg-recording-tool-panel" data-tool-panel="tutorial" hidden>
            <h2>Public tutorial</h2>
            <p>Publish an exported recording to the public tutorial library after the rendered file is ready.</p>
            <label>Title <input type="text" data-tutorial-title maxlength="180" placeholder="Tutorial title"></label>
            <label>Slug <input type="text" data-tutorial-slug maxlength="180" placeholder="auto-generated-if-empty"></label>
            <label>Summary <textarea data-tutorial-summary maxlength="1000" placeholder="Short tutorial summary"></textarea></label>
            <div class="mg-overlay-grid-fields">
              <label>Category <input type="text" data-tutorial-category maxlength="120" placeholder="Admin training"></label>
              <label>Difficulty <select data-tutorial-difficulty><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></label>
              <label>Status <select data-tutorial-status><option value="draft">Draft</option><option value="published">Published</option><option value="unlisted">Unlisted</option></select></label>
              <label>Featured <select data-tutorial-featured><option value="0">No</option><option value="1">Yes</option></select></label>
            </div>
            <button class="mg-btn mg-btn-primary" type="button" data-publish-tutorial <?= $canManageRecordings ? '' : 'disabled' ?>>Save tutorial</button>
            <div class="mg-editor-job-status" data-tutorial-status-box>No tutorial saved yet.</div>
          </section>
        </aside>
      </main>

      <footer class="mg-recording-timeline">
        <div class="mg-recording-timeline-meta">
          <strong data-current-time>0:00</strong>
          <span data-total-time>0:00</span>
        </div>
        <div class="mg-recording-timeline-track" data-timeline-track>
          <div class="mg-recording-timeline-range" data-timeline-range></div>
          <div class="mg-recording-playhead" data-playhead></div>
          <div class="mg-recording-overlay-bars" data-overlay-bars></div>
          <div class="mg-recording-split-markers" data-split-markers></div>
        </div>
      </footer>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
