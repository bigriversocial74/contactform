<?php
declare(strict_types=1);
$mgAgentDigestTitle = trim((string)($mgAgentDigestTitle ?? 'Agent Activity')) ?: 'Agent Activity';
$mgAgentDigestIntro = trim((string)($mgAgentDigestIntro ?? 'Track agent recommendations, review items, execution results, and failures.'));
$mgAgentDigestCompact = !empty($mgAgentDigestCompact);
?>
<section class="mg-app-panel mg-agent-digest-panel <?= $mgAgentDigestCompact ? 'is-compact' : '' ?>" data-agent-digest data-agent-digest-filter="all">
  <div class="mg-app-panel-head">
    <div>
      <h2><?= mg_e($mgAgentDigestTitle) ?></h2>
      <p><?= mg_e($mgAgentDigestIntro) ?></p>
    </div>
    <div class="mg-agent-digest-head-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-agent-execution.php">Execution Center</a>
      <button class="mg-btn mg-btn-secondary" type="button" data-agent-digest-refresh>Refresh</button>
    </div>
  </div>
  <div class="mg-agent-digest-kpis" data-agent-digest-kpis>
    <article><strong>—</strong><span>Pending reviews</span></article>
    <article><strong>—</strong><span>Results today</span></article>
    <article><strong>—</strong><span>Needs retry</span></article>
    <article><strong>—</strong><span>Unread</span></article>
  </div>
  <div class="mg-agent-digest-tabs" data-agent-digest-tabs>
    <button type="button" class="is-active" data-agent-digest-filter="all">All <span>0</span></button>
    <button type="button" data-agent-digest-filter="unread">Unread <span>0</span></button>
    <button type="button" data-agent-digest-filter="pending">Pending <span>0</span></button>
    <button type="button" data-agent-digest-filter="results">Results <span>0</span></button>
    <button type="button" data-agent-digest-filter="failed">Retry <span>0</span></button>
  </div>
  <div class="mg-agent-digest-feed" data-agent-digest-feed>
    <div class="mg-empty-state"><strong>Loading agent activity…</strong></div>
  </div>
  <p class="mg-form-status" data-agent-digest-status role="status"></p>
</section>
