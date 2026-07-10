<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/admin-permission-matrix.php';

$user = mg_require_admin_page_key('admin.system_health');
$canManageDelivery = mg_admin_permission_user_has($user, 'delivery.operations.manage')
    || mg_admin_permission_user_has($user, 'admin.users.manage')
    || in_array('super_admin', (array)($user['roles'] ?? []), true);

$page_title = 'Delivery Operations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-delivery-page';
$page_styles = [
    '/assets/css/admin-shell.css',
    '/assets/css/modal-foundation.css',
    '/assets/css/delivery-operations.css',
];
$page_scripts = ['/assets/js/delivery-operations.js'];
$adminActive = 'delivery-operations';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-delivery-ops" data-delivery-operations data-can-manage="<?= $canManageDelivery ? 'true' : 'false' ?>">
      <header class="mg-delivery-hero">
        <div>
          <a class="mg-delivery-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Delivery infrastructure</span>
          <h1>Delivery operations</h1>
          <p>Inbox and in-app delivery truth, communication jobs, queue capacity, retries, provider health, dead letters, and worker safety controls.</p>
        </div>
        <div class="mg-delivery-hero-actions">
          <span class="mg-delivery-score" data-delivery-score>—</span>
          <button class="mg-btn mg-btn-ghost" type="button" data-delivery-refresh>Refresh</button>
        </div>
      </header>

      <section class="mg-delivery-banner is-loading" data-delivery-banner aria-live="polite">
        <span aria-hidden="true"></span>
        <div><strong>Loading delivery health</strong><p>Reading the current queue and worker state.</p></div>
      </section>

      <section class="mg-delivery-kpis" aria-label="Delivery operations totals">
        <?php foreach ([
          ['Due now','due','Jobs ready for processing'],
          ['Processing','processing','Currently leased jobs'],
          ['Dead letter','dead_letter','Requires operator review'],
          ['Oldest pending','oldest','Queue latency'],
          ['Batch capacity','batch_size','Jobs per worker run'],
          ['Worker','worker','Current execution state'],
        ] as [$label,$key,$detail]): ?>
          <article><span><?= mg_e($label) ?></span><strong data-delivery-kpi="<?= mg_e($key) ?>">—</strong><small><?= mg_e($detail) ?></small></article>
        <?php endforeach; ?>
      </section>

      <section class="mg-delivery-channel-grid" data-delivery-channels>
        <?php foreach (['in_app'=>'Inbox + in-app','email'=>'Email jobs','sms'=>'SMS jobs','push'=>'Push jobs'] as $channel=>$label): ?>
          <article data-delivery-channel="<?= mg_e($channel) ?>">
            <header><strong><?= mg_e($label) ?></strong><span>Checking</span></header>
            <dl><div><dt>Queued</dt><dd data-channel-stat="queued">—</dd></div><div><dt>Accepted</dt><dd data-channel-stat="accepted">—</dd></div><div><dt>Delivered</dt><dd data-channel-stat="delivered">—</dd></div><div><dt>Failed</dt><dd data-channel-stat="failed">—</dd></div></dl>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="mg-delivery-panel">
        <header class="mg-delivery-panel-head">
          <div><span class="mg-eyebrow">Worker controls</span><h2>Capacity and safety</h2><p>The worker is CLI-only. Browser actions can review, retry, cancel, or clear a guarded pause; they cannot execute the queue.</p></div>
          <div class="mg-delivery-cli"><code>php /path/to/microgifter/bin/delivery-worker.php --observe</code><code>php /path/to/microgifter/bin/delivery-worker.php --process</code></div>
        </header>
        <div class="mg-delivery-worker-grid" data-delivery-worker-details></div>
        <?php if ($canManageDelivery): ?>
          <form class="mg-delivery-pause-form" data-delivery-clear-pause hidden>
            <label><span>Clear safety pause</span><input type="text" name="acknowledgement" autocomplete="off" placeholder="Exact acknowledgement phrase"></label>
            <button class="mg-btn mg-btn-primary" type="submit">Clear pause</button>
          </form>
        <?php endif; ?>
      </section>

      <section class="mg-delivery-panel">
        <header class="mg-delivery-panel-head">
          <div><span class="mg-eyebrow">Communication outbox</span><h2>Delivery jobs</h2><p>One durable job per notification and channel. External communication never recreates the reward.</p></div>
          <form class="mg-delivery-filters" data-delivery-filters>
            <select name="status" aria-label="Filter by status">
              <option value="">All statuses</option>
              <?php foreach (['queued','processing','retry_scheduled','provider_accepted','delivered','dead_letter','suppressed','cancelled'] as $status): ?><option value="<?= mg_e($status) ?>"><?= mg_e(ucwords(str_replace('_',' ',$status))) ?></option><?php endforeach; ?>
            </select>
            <select name="channel" aria-label="Filter by channel"><option value="">All channels</option><option value="in_app">In-app</option><option value="email">Email</option><option value="sms">SMS</option><option value="push">Push</option></select>
            <button class="mg-btn mg-btn-soft" type="submit">Apply</button>
          </form>
        </header>
        <div class="mg-delivery-table-wrap">
          <table class="mg-delivery-table">
            <thead><tr><th>Status</th><th>Channel</th><th>Notification</th><th>Recipient</th><th>Attempts</th><th>Updated</th><th></th></tr></thead>
            <tbody data-delivery-jobs><tr><td colspan="7" class="mg-delivery-empty">Loading delivery jobs…</td></tr></tbody>
          </table>
        </div>
      </section>

      <section class="mg-delivery-panel">
        <header class="mg-delivery-panel-head"><div><span class="mg-eyebrow">Automation evidence</span><h2>Recent worker runs</h2><p>Bounded execution history with delivery, retry, suppression, and dead-letter totals.</p></div></header>
        <div class="mg-delivery-runs" data-delivery-runs><p class="mg-delivery-empty">Loading worker history…</p></div>
      </section>
    </section>
  </main>
</section>

<div class="mg-modal-v1" data-mg-modal-root data-delivery-job-modal hidden>
  <button class="mg-modal-v1-backdrop" type="button" data-modal-close aria-label="Close delivery job details"></button>
  <section class="mg-modal-v1-dialog" role="dialog" aria-modal="true" aria-labelledby="delivery-job-modal-title">
    <header class="mg-modal-v1-header"><div><span class="mg-eyebrow">Delivery job</span><h2 id="delivery-job-modal-title" data-delivery-modal-title>Job details</h2></div><button type="button" class="mg-modal-v1-close" data-modal-close aria-label="Close">×</button></header>
    <div class="mg-modal-v1-body" data-delivery-modal-body></div>
    <?php if ($canManageDelivery): ?><footer class="mg-modal-v1-footer" data-delivery-modal-actions></footer><?php endif; ?>
  </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
