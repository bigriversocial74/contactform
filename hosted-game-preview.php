<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/hosted-game-preview.php';

$user=mg_current_user();
$gamePublicId=trim((string)($_GET['game']??''));$releasePublicId=trim((string)($_GET['release']??''));
$return='/signin.php?return='.rawurlencode('/hosted-game-preview.php?game='.$gamePublicId.'&release='.$releasePublicId);
if(!$user){header('Location: '.$return);exit;}
$pdo=mg_db();$errorMessage='';$access=null;$session=null;$manifest=null;
try{
    $access=mg_hosted_game_preview_access($pdo,$user,$gamePublicId,$releasePublicId);
    $session=mg_hosted_game_preview_session($pdo,$access,true);
    $session=mg_hosted_game_preview_session_by_public_id($pdo,$user,(string)$session['public_id']);
    $manifest=mg_hosted_game_preview_manifest($session);
}catch(Throwable $error){$errorMessage=$error->getMessage();}
$bridgeToken=$session?mg_hosted_game_standard_bridge_token():'';
$sandbox=$manifest?mg_hosted_game_standard_iframe_sandbox($manifest):'allow-scripts';
$allow=$manifest?mg_hosted_game_standard_iframe_allow($manifest):'';
$back=$access&&$access['scope']==='admin'?'/admin/hosted-game-releases.php?game='.rawurlencode($gamePublicId):'/merchant-game-releases.php?game='.rawurlencode($gamePublicId);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header("Content-Security-Policy: default-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#f7f9fc">
<title>Hosted Game Preview | Microgifter</title><link rel="stylesheet" href="/assets/css/hosted-game-preview.css?v=1.0.0">
</head>
<body>
<?php if(!$session||!$manifest): ?>
<main class="hgp-unavailable"><article><a href="<?= mg_e($back) ?>">← Release history</a><span>Protected QA</span><h1>Preview unavailable</h1><p><?= mg_e($errorMessage!==''?$errorMessage:'The selected release could not be opened.') ?></p></article></main>
<?php else: ?>
<div class="hgp-app" data-preview-app>
<header class="hgp-topbar">
  <div class="hgp-identity"><a href="<?= mg_e($back) ?>">← Releases</a><div><span>Protected QA · Test mode</span><strong><?= mg_e((string)$session['game_name']) ?> <small>v<?= (int)$session['version_number'] ?></small></strong></div></div>
  <div class="hgp-status"><span class="hgp-test-badge">No live inventory</span><span data-preview-status>Starting preview…</span></div>
</header>
<div class="hgp-toolbar">
  <div class="hgp-viewports" role="group" aria-label="Preview viewport"><button type="button" data-viewport="desktop" class="is-active">Desktop</button><button type="button" data-viewport="tablet">Tablet</button><button type="button" data-viewport="mobile">Mobile</button></div>
  <div class="hgp-actions"><button type="button" data-preview-reload>Reload game</button><button type="button" data-preview-clear>Clear console</button><button type="button" class="is-danger" data-preview-reset>Reset test data</button></div>
</div>
<main class="hgp-layout">
  <section class="hgp-stage" data-viewport-stage="desktop">
    <div class="hgp-device" data-preview-device>
      <iframe id="hosted-game-preview-frame" title="<?= mg_e((string)$session['game_name']) ?> release preview" src="/api/hosted-games/preview-document.php?session=<?= rawurlencode((string)$session['public_id']) ?>&amp;bridge=<?= rawurlencode($bridgeToken) ?>" sandbox="<?= mg_e($sandbox) ?>" <?= $allow!==''?'allow="'.mg_e($allow).'"':'' ?> referrerpolicy="no-referrer"></iframe>
    </div>
  </section>
  <aside class="hgp-console">
    <nav><button type="button" data-console-tab="events" class="is-active">Events <span data-count="events">0</span></button><button type="button" data-console-tab="requests">SDK requests <span data-count="requests">0</span></button><button type="button" data-console-tab="errors">Errors <span data-count="errors">0</span></button></nav>
    <div class="hgp-session-card"><span>Session</span><code><?= mg_e((string)$session['public_id']) ?></code><small>Expires <?= mg_e((string)$session['expires_at']) ?></small></div>
    <div class="hgp-console-list" data-console-list></div>
  </aside>
</main>
</div>
<script>
window.MicrogifterHostedGamePreview=<?= json_encode([
  'sessionId'=>(string)$session['public_id'],'gameId'=>(string)$session['game_public_id'],'releaseId'=>(string)$session['release_public_id'],'slug'=>(string)$session['game_slug'],'iframeId'=>'hosted-game-preview-frame','bridgeToken'=>$bridgeToken,'sdkVersion'=>MG_HOSTED_GAME_STANDARD_SDK_VERSION,'manifest'=>mg_hosted_game_standard_public_manifest($manifest),'runtimeUrl'=>'/api/hosted-games/preview-runtime.php','eventsUrl'=>'/api/hosted-games/preview-events.php','resetUrl'=>'/api/hosted-games/preview-reset.php','csrfToken'=>mg_csrf_token()
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
</script><script src="/assets/js/hosted-game-preview.js?v=1.0.0" defer></script>
<?php endif; ?>
</body></html>
