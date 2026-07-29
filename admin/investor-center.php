<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/investment/investor-center-dashboard.php';

$user = mg_require_admin_page_permission('admin.investment.view');
$snapshot = mg_investor_center_snapshot(mg_db());
$page_title = 'Investor Center | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-investor-center-page';
$page_styles = ['/assets/css/admin-shell.css', '/assets/css/investment-system-v1.css?v=1.0.0', '/assets/css/investor-center-v6.css?v=6.0.0'];
$adminActive = 'investor-center';

$money = static fn(int $cents): string => '$' . number_format($cents / 100, 0);
$metric = static function (string $label, int|string $value): void {
    ?><div class="mg-investor-metric"><span><?= mg_e($label) ?></span><strong><?= mg_e((string)$value) ?></strong></div><?php
};

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-investor-center" data-investor-center>
      <header class="mg-investor-center-hero">
        <div>
          <a href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-investor-center-kicker">Private capital operations</span>
          <h1>Investor Center</h1>
          <p>One command view for investor access, pipeline activity, governed diligence, closing readiness, funded-investor relations, and post-close governance.</p>
        </div>
        <div class="mg-investor-center-actions">
          <div class="mg-investor-score"><span>Certification target</span><strong>10/10</strong><small>End-to-end Investor lifecycle</small></div>
          <a class="mg-btn mg-btn-primary" href="/admin/investor-access-requests.php">Review access</a>
          <a class="mg-btn mg-btn-soft" href="/admin/investor-pipeline.php">Open pipeline</a>
        </div>
      </header>

      <div class="mg-investor-center-grid">
        <article class="mg-investor-center-card">
          <header><div><span class="mg-investor-center-kicker">Identity and approval</span><h2>Investor Access</h2></div><a href="/admin/investor-access-requests.php">Manage →</a></header>
          <div class="mg-investor-metrics">
            <?php $metric('Pending review', $snapshot['access']['pending']); ?>
            <?php $metric('More information', $snapshot['access']['more_information']); ?>
            <?php $metric('Active profiles', $snapshot['access']['active']); ?>
            <?php $metric('Role/profile repairs', $snapshot['access']['inconsistent']); ?>
          </div>
        </article>

        <article class="mg-investor-center-card">
          <header><div><span class="mg-investor-center-kicker">Relationship management</span><h2>Investor Pipeline</h2></div><a href="/admin/investor-pipeline.php">Manage →</a></header>
          <div class="mg-investor-metrics">
            <?php $metric('Active investors', $snapshot['pipeline']['active']); ?>
            <?php $metric('Overdue follow-ups', $snapshot['pipeline']['overdue_followups']); ?>
            <?php $metric('Meetings scheduled', $snapshot['pipeline']['meetings']); ?>
            <?php $metric('In diligence', $snapshot['pipeline']['diligence']); ?>
          </div>
        </article>

        <article class="mg-investor-center-card">
          <header><div><span class="mg-investor-center-kicker">Controlled disclosure</span><h2>Due Diligence</h2></div><a href="/admin/investor-diligence.php">Manage →</a></header>
          <div class="mg-investor-metrics">
            <?php $metric('Open requests', $snapshot['diligence']['open_requests']); ?>
            <?php $metric('Urgent requests', $snapshot['diligence']['urgent_requests']); ?>
            <?php $metric('Published documents', $snapshot['diligence']['published_documents']); ?>
            <?php $metric('Expiring in 30 days', $snapshot['diligence']['expiring_documents']); ?>
          </div>
        </article>

        <article class="mg-investor-center-card">
          <header><div><span class="mg-investor-center-kicker">Signed and funded authority</span><h2>Closing</h2></div><a href="/admin/investment-closing.php">Manage →</a></header>
          <div class="mg-investor-metrics">
            <?php $metric('Active closing records', $snapshot['closing']['active_records']); ?>
            <?php $metric('Pending verifications', $snapshot['closing']['pending_verifications']); ?>
            <?php $metric('Funded investors', $snapshot['closing']['funded_investors']); ?>
            <?php $metric('Verified funded', $money($snapshot['closing']['verified_funded_cents'])); ?>
          </div>
        </article>

        <article class="mg-investor-center-card">
          <header><div><span class="mg-investor-center-kicker">Post-close obligations</span><h2>Governance</h2></div><a href="/admin/investor-governance.php">Manage →</a></header>
          <div class="mg-investor-metrics">
            <?php $metric('Upcoming meetings', $snapshot['governance']['upcoming_meetings']); ?>
            <?php $metric('Due obligations', $snapshot['governance']['due_obligations']); ?>
            <?php $metric('Overdue obligations', $snapshot['governance']['overdue_obligations']); ?>
            <?php $metric('Unacknowledged notices', $snapshot['governance']['unacknowledged_notices']); ?>
          </div>
        </article>

        <article class="mg-investor-center-card">
          <header><div><span class="mg-investor-center-kicker">Round publication</span><h2>Funding Rounds</h2></div><a href="/admin/investment-wizard.php">Manage →</a></header>
          <div class="mg-investor-metrics">
            <?php $metric('Official rounds', $snapshot['rounds']['official']); ?>
            <?php $metric('Published/private preview', $snapshot['rounds']['published']); ?>
            <?php $metric('Open or closing', $snapshot['rounds']['open']); ?>
            <?php $metric('Soft committed', $snapshot['pipeline']['soft_committed']); ?>
          </div>
        </article>
      </div>

      <section class="mg-investor-center-section">
        <header><div><span class="mg-investor-center-kicker">Prioritized operations</span><h2>Investor work queue</h2><p>Items requiring administrative attention across the full investment lifecycle.</p></div></header>
        <div class="mg-investor-work-list">
          <?php if ($snapshot['work'] === []): ?>
            <div class="mg-investor-empty"><strong>No priority Investor Center exceptions are currently detected.</strong></div>
          <?php else: ?>
            <?php foreach ($snapshot['work'] as $item): ?>
              <a class="mg-investor-work-item is-<?= mg_e((string)$item['severity']) ?>" href="<?= mg_e((string)$item['href']) ?>">
                <span class="mg-investor-work-count"><?= (int)$item['count'] ?></span>
                <span class="mg-investor-work-label"><?= mg_e((string)$item['label']) ?></span>
                <span class="mg-investor-work-arrow">›</span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="mg-investor-center-section">
        <header><div><span class="mg-investor-center-kicker">Specialist workspaces</span><h2>Investor operations modules</h2><p>Each module remains authoritative for its own controlled records and actions.</p></div></header>
        <div class="mg-investor-module-grid">
          <a class="mg-investor-module" href="/admin/investor-access-requests.php"><strong>Access Requests</strong><span>Review identity, approve, request information, deny, revoke, and repair access.</span></a>
          <a class="mg-investor-module" href="/admin/investment-wizard.php"><strong>Investment Wizard</strong><span>Model scenarios, dilution, runway, official terms, evidence, and round publication.</span></a>
          <a class="mg-investor-module" href="/admin/investor-pipeline.php"><strong>Investor Pipeline</strong><span>Manage stage, priority, follow-ups, selected-round access, metrics, and publication.</span></a>
          <a class="mg-investor-module" href="/admin/investor-diligence.php"><strong>Due Diligence &amp; Data Room</strong><span>Govern folders, documents, Q&amp;A, requests, meetings, communications, and engagement.</span></a>
          <a class="mg-investor-module" href="/admin/investment-closing.php"><strong>Closing Command Center</strong><span>Coordinate packets, compliance, maker/checker verification, reconciliation, and reporting.</span></a>
          <a class="mg-investor-module" href="/admin/investor-governance.php"><strong>Governance Command Center</strong><span>Track rights, obligations, meetings, consents, holdings, tax documents, and notices.</span></a>
        </div>
      </section>
    </section>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
