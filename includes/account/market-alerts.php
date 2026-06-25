<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/merchant-market-alerts.php';

$marketAlerts = mg_market_alerts_build($marketPayload ?? [], $movement ?? [], $marketSnapshots ?? []);
$marketHeaderAlerts = array_map(static function (array $alert): array {
    return [
        'level' => (string)$alert['level'],
        'title' => (string)$alert['title'],
        'body' => (string)$alert['body'],
        'href' => (string)$alert['href'],
    ];
}, $marketAlerts);
?>
<link rel="stylesheet" href="/assets/css/market-alerts.css">
<section class="mg-market-alerts-panel">
  <header>
    <div>
      <span class="mg-market-kicker">Market Alerts</span>
      <h3>Signals that need attention</h3>
      <p>Alerts are generated from snapshots, movement, campaign funnel quality, risk, distribution, and stamp activity.</p>
    </div>
  </header>
  <div class="mg-market-alert-grid">
    <?php foreach ($marketAlerts as $alert): ?>
      <article class="mg-market-alert-card is-<?= mg_e((string)$alert['level']) ?>">
        <span><?= mg_e(ucfirst((string)$alert['level'])) ?></span>
        <h4><?= mg_e((string)$alert['title']) ?></h4>
        <p><?= mg_e((string)$alert['body']) ?></p>
        <dl>
          <div><dt>Why it matters</dt><dd><?= mg_e((string)$alert['why']) ?></dd></div>
          <div><dt>Recommended action</dt><dd><?= mg_e((string)$alert['action']) ?></dd></div>
        </dl>
        <a class="mg-btn mg-btn-soft" href="<?= mg_e((string)$alert['href']) ?>">Open action</a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<script type="application/json" id="mg-market-alerts-json"><?= json_encode($marketHeaderAlerts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script src="/assets/js/market-alerts.js"></script>
