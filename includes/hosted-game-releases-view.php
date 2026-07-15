<?php
declare(strict_types=1);

$hgrGame=is_array($hgrGame??null)?$hgrGame:null;
$hgrScope=(string)($hgrScope??'merchant');
$hgrCanManage=(bool)($hgrCanManage??false);
$hgrApi=(string)($hgrApi??'');
$hgrUploadApi=(string)($hgrUploadApi??'');
$hgrDownloadApi=(string)($hgrDownloadApi??'');
$hgrBack=(string)($hgrBack??'/merchant-games.php');
?>
<div class="hgr-page" data-hgr-app data-game-id="<?= mg_e((string)($hgrGame['public_id']??'')) ?>" data-api="<?= mg_e($hgrApi) ?>" data-upload-api="<?= mg_e($hgrUploadApi) ?>" data-download-api="<?= mg_e($hgrDownloadApi) ?>" data-csrf="<?= mg_e(mg_csrf_token()) ?>" data-scope="<?= mg_e($hgrScope) ?>" data-can-manage="<?= $hgrCanManage?'1':'0' ?>">
  <?php if(!$hgrGame): ?>
    <section class="hgr-empty"><h2>Release history unavailable</h2><p>The hosted game could not be found or this account cannot access it.</p><a href="<?= mg_e($hgrBack) ?>">Return to Hosted Games</a></section>
  <?php else: ?>
  <section class="hgr-hero">
    <div><a class="hgr-back" href="<?= mg_e($hgrBack) ?>">← Hosted Games</a><span class="hgr-eyebrow">Release & QA foundation</span><h1><?= mg_e((string)$hgrGame['name']) ?><br><em>release history</em></h1><p>Upload draft packages, validate and test unpublished releases, compare manifests, activate safely, and roll back without interrupting the current live game.</p></div>
    <div class="hgr-hero-meta"><span>Public game</span><a href="/games/<?= rawurlencode((string)$hgrGame['slug']) ?>/" target="_blank" rel="noopener">/games/<?= mg_e((string)$hgrGame['slug']) ?>/</a><strong data-hgr-current>Loading current release…</strong></div>
  </section>
  <div class="hgr-notice" data-hgr-notice hidden></div>
  <section class="hgr-stats"><article><span>Total releases</span><strong data-hgr-stat="total">0</strong></article><article><span>Draft/testing</span><strong data-hgr-stat="testing">0</strong></article><article><span>Active</span><strong data-hgr-stat="active">0</strong></article><article><span>Needs attention</span><strong data-hgr-stat="attention">0</strong></article></section>
  <?php if($hgrCanManage): ?>
  <section class="hgr-panel hgr-upload-panel">
    <div class="hgr-panel-head"><div><span class="hgr-eyebrow">New release</span><h2>Upload a draft package</h2><p>The current live release stays active. The original ZIP is preserved privately and the new release must pass QA before activation.</p></div></div>
    <form data-hgr-upload-form>
      <label class="hgr-file"><strong>Select game ZIP</strong><small>Maximum 100 MB · static browser package · Standard v1 recommended</small><input type="file" name="game_zip" accept=".zip,application/zip" required></label>
      <label>Release notes<textarea name="release_notes" rows="4" maxlength="10000" placeholder="What changed in this release? Include fixes, new levels, scoring changes, or migration notes."></textarea></label>
      <div class="hgr-form-actions"><button class="hgr-btn is-primary" type="submit">Upload draft release</button><span data-hgr-upload-status></span></div>
    </form>
  </section>
  <?php endif; ?>
  <section class="hgr-panel hgr-compare-panel">
    <div class="hgr-panel-head"><div><span class="hgr-eyebrow">Manifest comparison</span><h2>Compare two releases</h2><p>Review Standard schema, game version, capabilities, events, scoring, qualification, assets, viewport, and session-policy changes.</p></div></div>
    <div class="hgr-compare-controls"><select data-hgr-compare-left aria-label="First release"><option value="">Select first release</option></select><span>versus</span><select data-hgr-compare-right aria-label="Second release"><option value="">Select second release</option></select><button class="hgr-btn" type="button" data-hgr-compare>Compare manifests</button></div>
    <div class="hgr-compare-result" data-hgr-compare-result hidden></div>
  </section>
  <div class="hgr-toolbar"><div><span class="hgr-eyebrow">Complete history</span><h2>Game releases</h2></div><button class="hgr-btn" type="button" data-hgr-refresh>Refresh</button></div>
  <section class="hgr-release-list" data-hgr-list></section>
  <section class="hgr-empty" data-hgr-empty hidden><h2>No releases yet</h2><p>Upload the first draft package to start release history.</p></section>
  <?php endif; ?>
</div>
