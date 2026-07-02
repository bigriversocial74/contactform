<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/admin-screen-recordings.php';

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
<section class="mg-recording-editor-shell" data-recording-editor data-recording-id="<?= (int)$recordingId ?>" data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManageRecordings ? '1' : '0' ?>">
  <header class="mg-recording-editor-topbar">
    <div>
      <a href="/admin/screen-recordings.php">← Recordings</a>
      <span class="mg-eyebrow">Full page editor</span>
      <h1 data-editor-title>Screen recording editor</h1>
    </div>
    <div class="mg-recording-editor-actions">
      <button class="mg-btn mg-btn-soft" type="button" data-editor-download-original>Download original</button>
      <button class="mg-btn mg-btn-ghost" type="button" data-editor-save-draft <?= $canManageRecordings ? '' : 'disabled' ?>>Save draft</button>
      <button class="mg-btn mg-btn-primary" type="button" data-editor-export <?= $canManageRecordings ? '' : 'disabled' ?>>Export edited video</button>
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
        <button type="button" data-tool-tab="text">Text overlays</button>
        <button type="button" data-tool-tab="export">Export</button>
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

      <section class="mg-recording-tool-panel" data-tool-panel="export" hidden>
        <h2>Export settings</h2>
        <label>Format <select data-export-format><option value="webm">WebM</option><option value="mp4">MP4 when FFmpeg is available</option></select></label>
        <label><input type="checkbox" data-export-burn-overlays checked> Burn text overlays into exported video</label>
        <p class="mg-editor-note">This stage saves a complete edit manifest and queues export metadata. Server-side rendered MP4/WebM export should be completed with FFmpeg in a follow-up pass if FFmpeg is available on production.</p>
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
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
