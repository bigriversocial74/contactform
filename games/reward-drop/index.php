<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$config = rd_config();
$user = rd_session_user();
$pdo = mg_db();
$readiness = rd_readiness($pdo, $config);
$link = null;
$cooldown = ['eligible' => false, 'available_at' => null, 'remaining_seconds' => 0];
$callbackMessage = '';
$callbackType = 'info';

if ($user !== null && isset($_GET['status'])) {
    $status = trim((string)$_GET['status']);
    $state = trim((string)($_GET['state'] ?? ''));
    $verifiedState = rd_state_verify($state, (int)$user['id'], $config);
    if ($verifiedState === null) {
        $callbackMessage = 'The account connection response could not be verified.';
        $callbackType = 'error';
    } elseif ($status === 'linked') {
        $callbackMessage = 'Your Microgifter account is connected. Rewards can now be delivered to your Inbox.';
        $callbackType = 'success';
    } elseif ($status === 'cancelled') {
        $callbackMessage = 'Account connection was cancelled.';
    } elseif ($status === 'expired') {
        $callbackMessage = 'The account connection expired. Start it again when you are ready.';
        $callbackType = 'error';
    }
}

if ($user !== null && !empty($readiness['app_id'])) {
    $link = rd_active_link($pdo, (int)$readiness['app_id'], (int)$user['id']);
    try {
        $cooldown = rd_cooldown($pdo, (int)$user['id'], $config);
    } catch (Throwable $error) {
        $readiness['schema_ready'] = false;
    }
}

$canPlay = $user !== null
    && $link !== null
    && !empty($readiness['configured'])
    && !empty($readiness['credential_found'])
    && !empty($readiness['app_live'])
    && !empty($readiness['key_live'])
    && !empty($readiness['scopes_ready'])
    && !empty($cooldown['eligible']);

$safeClientConfig = [
    'csrfToken' => mg_csrf_token(),
    'signedIn' => $user !== null,
    'linked' => $link !== null,
    'canPlay' => $canPlay,
    'targetScore' => (int)$config['target_score'],
    'durationSeconds' => (int)$config['duration_seconds'],
    'endpoints' => [
        'link' => '/games/reward-drop/api/link.php',
        'start' => '/games/reward-drop/api/start.php',
        'complete' => '/games/reward-drop/api/complete.php',
        'status' => '/games/reward-drop/api/status.php',
    ],
    'inboxUrl' => '/inbox.php',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#f5f8ff">
<title>Reward Drop | Microgifter</title>
<link rel="stylesheet" href="/games/reward-drop/assets/game.css?v=1.0.0">
</head>
<body>
<header class="rd-topbar">
  <a class="rd-brand" href="/"><img src="/images/logo_main_drk.png" alt="Microgifter"><span>Reward Drop</span></a>
  <div class="rd-user">
    <?php if ($user): ?><span><?= mg_e(mg_user_display_name()) ?></span><a href="/inbox.php">Inbox</a><?php else: ?><a href="/signin.php?return=<?= rawurlencode('/games/reward-drop/') ?>">Sign in</a><?php endif; ?>
  </div>
</header>

<main class="rd-page">
  <section class="rd-hero">
    <div>
      <span class="rd-eyebrow">Microgifter API game</span>
      <h1>Catch gifts.<br>Unlock a reward.</h1>
      <p>Collect <?= number_format((int)$config['target_score']) ?> gift drops before time runs out. Qualifying rewards are issued through the Microgifter Developer API and delivered to your Inbox.</p>
    </div>
    <div class="rd-flow" aria-label="Reward flow">
      <span>Play</span><i>→</i><span>API reward</span><i>→</i><span>Inbox</span>
    </div>
  </section>

  <?php if ($callbackMessage !== ''): ?><div class="rd-notice is-<?= mg_e($callbackType) ?>" role="status"><?= mg_e($callbackMessage) ?></div><?php endif; ?>

  <div class="rd-layout">
    <section class="rd-game-card" data-reward-drop>
      <div class="rd-game-head">
        <div><span>Score</span><strong data-rd-score>0</strong></div>
        <div><span>Target</span><strong><?= number_format((int)$config['target_score']) ?></strong></div>
        <div><span>Time</span><strong data-rd-time><?= number_format((int)$config['duration_seconds']) ?>s</strong></div>
      </div>

      <div class="rd-arena" data-rd-arena aria-label="Reward Drop game area">
        <div class="rd-grid" aria-hidden="true"></div>
        <div class="rd-intro" data-rd-overlay>
          <?php if (!$user): ?>
            <span class="rd-gift-mark" aria-hidden="true">✦</span>
            <h2>Sign in to play</h2>
            <p>Reward Drop automatically recognizes your Microgifter account.</p>
            <a class="rd-primary" href="/signin.php?return=<?= rawurlencode('/games/reward-drop/') ?>">Sign in with Microgifter</a>
          <?php elseif (!$readiness['configured'] || !$readiness['credential_found']): ?>
            <span class="rd-gift-mark" aria-hidden="true">⚙</span>
            <h2>Game setup required</h2>
            <p>The server-side API credential, program, reward template, and webhook secret must be configured first.</p>
          <?php elseif (!$readiness['app_live'] || !$readiness['key_live'] || !$readiness['scopes_ready']): ?>
            <span class="rd-gift-mark" aria-hidden="true">API</span>
            <h2>Live API access required</h2>
            <p>Activate a live developer app and credential with reward issue and status scopes.</p>
          <?php elseif (!$link): ?>
            <span class="rd-gift-mark" aria-hidden="true">↗</span>
            <h2>Connect your Inbox</h2>
            <p>This one-time approval allows Reward Drop to send earned rewards to your signed-in Microgifter account.</p>
            <button class="rd-primary" type="button" data-rd-link>Connect Microgifter Inbox</button>
          <?php elseif (!$cooldown['eligible']): ?>
            <span class="rd-gift-mark" aria-hidden="true">✓</span>
            <h2>Reward already earned</h2>
            <p>Your next play becomes available <time data-rd-available datetime="<?= mg_e((string)$cooldown['available_at']) ?>"><?= mg_e((string)$cooldown['available_at']) ?></time>.</p>
            <a class="rd-primary" href="/inbox.php">Open Inbox</a>
          <?php else: ?>
            <span class="rd-gift-mark" aria-hidden="true">✦</span>
            <h2>Ready, <?= mg_e(mg_user_display_name()) ?>?</h2>
            <p>Tap each gift as it appears. Reach the target before the clock reaches zero.</p>
            <button class="rd-primary" type="button" data-rd-start>Start Reward Drop</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="rd-game-status" data-rd-status aria-live="polite">Ready.</div>
      <div class="rd-result-actions" data-rd-result-actions hidden>
        <a class="rd-primary" href="/inbox.php">Open Microgifter Inbox</a>
        <button class="rd-secondary" type="button" data-rd-reset>Play screen</button>
      </div>
    </section>

    <aside class="rd-side">
      <section class="rd-panel">
        <span class="rd-eyebrow">Account status</span>
        <h2><?= $user ? 'Signed in and recognized' : 'Microgifter sign-in required' ?></h2>
        <div class="rd-status-list">
          <p><i class="<?= $user ? 'is-ready' : '' ?>"></i><span>Microgifter session</span><strong><?= $user ? 'Ready' : 'Sign in' ?></strong></p>
          <p><i class="<?= $link ? 'is-ready' : '' ?>"></i><span>Inbox connection</span><strong><?= $link ? 'Connected' : 'Required once' ?></strong></p>
          <p><i class="<?= $readiness['app_live'] && $readiness['key_live'] ? 'is-ready' : '' ?>"></i><span>Live API</span><strong><?= $readiness['app_live'] && $readiness['key_live'] ? 'Ready' : 'Setup' ?></strong></p>
          <p><i class="<?= $readiness['webhook_url_ready'] && $readiness['webhook_secret_ready'] ? 'is-ready' : '' ?>"></i><span>Webhook</span><strong><?= $readiness['webhook_url_ready'] && $readiness['webhook_secret_ready'] ? 'Ready' : 'Setup' ?></strong></p>
        </div>
      </section>
      <section class="rd-panel rd-how">
        <span class="rd-eyebrow">How delivery works</span>
        <ol><li><b>1</b><span>The game verifies your signed-in session and one-time run token.</span></li><li><b>2</b><span>The server calls the Public Distribution API with an idempotency key.</span></li><li><b>3</b><span>A signed webhook confirms the reward lifecycle and your Inbox receives the item.</span></li></ol>
      </section>
    </aside>
  </div>
</main>

<script>window.RewardDropConfig=<?= json_encode($safeClientConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/games/reward-drop/assets/game.js?v=1.0.0" defer></script>
</body>
</html>
