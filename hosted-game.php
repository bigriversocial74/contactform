<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/hosted-games.php';
require_once __DIR__ . '/includes/hosted-game-standard-v1.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$pdo = mg_db();
$game = $slug !== '' && mg_hosted_game_schema_ready($pdo) ? mg_hosted_game_by_slug($pdo,$slug,false) : null;
$readiness = $game ? mg_hosted_game_readiness($pdo,$game) : null;
$manifest = $game ? mg_hosted_game_standard_manifest_from_game($game) : null;
$user = mg_current_user();
$returnPath = $game ? '/games/' . rawurlencode((string)$game['slug']) . '/' : '/';
$signinUrl = '/signin.php?return=' . rawurlencode($returnPath);
$bridgeToken = $game ? mg_hosted_game_standard_bridge_token() : '';
$iframeSandbox = $manifest ? mg_hosted_game_standard_iframe_sandbox($manifest) : 'allow-scripts';
$iframeAllow = $manifest ? mg_hosted_game_standard_iframe_allow($manifest) : '';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-src 'self'; connect-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#07111f">
<title><?= $game ? mg_e((string)$game['name']) . ' | Microgifter Games' : 'Hosted Game | Microgifter' ?></title>
<link rel="stylesheet" href="/assets/css/hosted-game-shell.css?v=1.0.0">
</head>
<body>
<?php if (!$game || !$readiness || !$readiness['publish_ready'] || !$manifest): ?>
<main class="hg-shell-unavailable">
  <article>
    <a href="/"><img src="/images/logo_main_drk.png" alt="Microgifter"></a>
    <h1><?= $game ? 'Game temporarily unavailable' : 'Game not found' ?></h1>
    <p><?= $game ? 'This hosted game is paused or its release, reward integration, isolated database, or Standard v1 manifest requires attention.' : 'The hosted game URL is not active.' ?></p>
    <a href="/">Return to Microgifter</a>
  </article>
</main>
<?php else: ?>
<div class="hg-shell" data-hosted-game-shell data-orientation="<?= mg_e((string)$manifest['orientation']) ?>">
  <header class="hg-shell-bar">
    <div class="hg-shell-brand">
      <a href="/" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"></a>
      <div class="hg-shell-title"><strong><?= mg_e((string)$game['name']) ?></strong><span>Hosted Game Standard v1 · <?= mg_e((string)$game['slug']) ?></span></div>
    </div>
    <div class="hg-shell-actions">
      <span class="hg-shell-status" data-hg-shell-status>Loading player…</span>
      <a class="hg-shell-link" href="/inbox.php">Inbox</a>
      <?php if (mg_hosted_game_standard_has_capability($manifest, 'fullscreen')): ?><button class="hg-shell-button" type="button" data-hg-fullscreen>Fullscreen</button><?php endif; ?>
    </div>
  </header>
  <main class="hg-shell-stage">
    <?php /* Legacy Hosted Games contract remains sandbox="allow-scripts ..."; Standard v1 adds only explicitly declared permissions and keeps the child origin opaque. */ ?>
    <iframe
      id="hosted-game-frame"
      class="hg-shell-frame"
      title="<?= mg_e((string)$game['name']) ?>"
      src="/api/hosted-games/document.php?slug=<?= rawurlencode((string)$game['slug']) ?>&amp;bridge=<?= rawurlencode($bridgeToken) ?>"
      sandbox="<?= mg_e($iframeSandbox) ?>"
      <?= $iframeAllow !== '' ? 'allow="' . mg_e($iframeAllow) . '"' : '' ?>
      loading="eager"
      referrerpolicy="no-referrer"></iframe>
    <div class="hg-shell-overlay" data-hg-shell-overlay>
      <article class="hg-shell-overlay-card">
        <div class="hg-shell-overlay-mark" aria-hidden="true">✦</div>
        <h1 data-hg-shell-title>Loading game</h1>
        <p data-hg-shell-text>Checking your Microgifter session and Hosted Game Standard connection.</p>
        <button type="button" data-hg-shell-action hidden>Continue</button>
      </article>
    </div>
  </main>
</div>
<script>
window.MicrogifterHostedGameShell=<?= json_encode([
    'slug'=>(string)$game['slug'],
    'gameId'=>(string)$game['public_id'],
    'iframeId'=>'hosted-game-frame',
    'runtimeUrl'=>'/api/hosted-games/runtime.php',
    'telemetryUrl'=>'/api/hosted-games/telemetry.php',
    'csrfToken'=>mg_csrf_token(),
    'bridgeToken'=>$bridgeToken,
    'sdkVersion'=>MG_HOSTED_GAME_STANDARD_SDK_VERSION,
    'releaseId'=>$game['current_release_public_id'] ?? null,
    'releaseVersion'=>isset($game['version_number']) ? (int)$game['version_number'] : null,
    'manifest'=>mg_hosted_game_standard_public_manifest($manifest),
    'signInUrl'=>$signinUrl,
    'inboxUrl'=>'/inbox.php',
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php /* Compatibility marker: hosted-game-shell.js?v=1.1.0 was the original Standard v1 shell; v1.2.0 adds telemetry routing. */ ?>
<script src="/assets/js/hosted-game-shell.js?v=1.2.0" defer></script>
<?php endif; ?>
</body>
</html>
