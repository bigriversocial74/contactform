<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$user = mg_require_auth();
$canViewModeration = mg_has_permission('social.moderate')
    || mg_has_permission('admin.profiles.moderation.view')
    || mg_has_permission('admin.profiles.moderation.manage');
$canManageModeration = mg_has_permission('social.moderate')
    || mg_has_permission('admin.profiles.moderation.manage');

$page_title = 'Moderation Center | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-moderation-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-moderation.css'];
$adminActive = 'moderation';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-moderation-shell" data-admin-moderation data-can-manage="<?= $canManageModeration ? '1' : '0' ?>">
      <header class="mg-admin-moderation-hero">
        <div>
          <a class="mg-admin-moderation-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Trust and safety</span>
          <h1>Moderation center</h1>
          <p>Review reports involving profiles, posts, comments, uploaded media, and direct messages from one protected workspace.</p>
        </div>
        <?php if ($canViewModeration): ?>
          <div class="mg-admin-moderation-hero-actions">
            <span>Last updated <strong data-moderation-updated>—</strong></span>
            <button class="mg-btn mg-btn-ghost" type="button" data-moderation-refresh disabled>Refresh</button>
          </div>
        <?php endif; ?>
      </header>

      <?php if (!$canViewModeration): ?>
        <section class="mg-admin-moderation-access mg-app-panel">
          <h2>Moderation access is not active.</h2>
          <p>This page requires social or profile moderation permission.</p>
          <a class="mg-btn mg-btn-soft" href="/account-admin.php">Back to admin</a>
        </section>
      <?php else: ?>
        <section class="mg-admin-moderation-summary" aria-label="Moderation queue summary">
          <?php foreach ([
            ['open','Open reports'],
            ['reviewing','In review'],
            ['urgent','Urgent'],
            ['unassigned','Unassigned'],
            ['appealed','Appealed'],
          ] as [$key,$label]): ?>
            <article><span><?= mg_e($label) ?></span><strong data-moderation-metric="<?= mg_e($key) ?>">—</strong><small>Waiting for queue data</small></article>
          <?php endforeach; ?>
        </section>

        <form class="mg-admin-moderation-filters" data-moderation-filters>
          <label class="is-search">Search
            <input type="search" name="q" maxlength="190" placeholder="Report, user, profile, post, or message">
          </label>
          <label>Status
            <select name="status">
              <option value="active">Active queue</option>
              <option value="open">Open</option>
              <option value="reviewing">In review</option>
              <option value="resolved">Resolved</option>
              <option value="dismissed">Dismissed</option>
              <option value="all">All</option>
            </select>
          </label>
          <label>Content type
            <select name="subject_type">
              <option value="all">All content</option>
              <option value="profile">Profiles</option>
              <option value="post">Posts</option>
              <option value="comment">Comments</option>
              <option value="media">Media</option>
              <option value="message">Messages</option>
              <option value="user">Users</option>
            </select>
          </label>
          <label>Severity
            <select name="severity">
              <option value="all">All severities</option>
              <option value="urgent">Urgent</option>
              <option value="high">High</option>
              <option value="normal">Normal</option>
              <option value="low">Low</option>
            </select>
          </label>
          <label>Assignment
            <select name="assignee">
              <option value="all">All reports</option>
              <option value="me">Assigned to me</option>
              <option value="unassigned">Unassigned</option>
            </select>
          </label>
          <button class="mg-btn mg-btn-soft" type="submit">Apply filters</button>
        </form>

        <div class="mg-admin-moderation-workspace">
          <aside class="mg-admin-moderation-queue">
            <header><div><h2>Report queue</h2><p><span data-moderation-total>0</span> matching reports</p></div></header>
            <div class="mg-admin-moderation-list" data-moderation-list>
              <div class="mg-admin-moderation-loading"><strong>Loading reports</strong><span>Preparing the moderation queue.</span></div>
            </div>
            <footer>
              <button class="mg-btn mg-btn-ghost" type="button" data-moderation-page="previous" disabled>Previous</button>
              <span data-moderation-page-label>Page 1 of 1</span>
              <button class="mg-btn mg-btn-ghost" type="button" data-moderation-page="next" disabled>Next</button>
            </footer>
          </aside>

          <main class="mg-admin-moderation-review">
            <div class="mg-admin-moderation-empty" data-moderation-empty>
              <span>Case review</span>
              <h2>Select a report</h2>
              <p>Choose a report from the queue to inspect the content, attached media, author, reporter, account history, and prior moderation actions.</p>
            </div>
            <div class="mg-admin-moderation-detail mg-hidden" data-moderation-detail>
              <header class="mg-admin-moderation-detail-head">
                <div><div data-report-badges></div><h2 data-report-title>Report</h2><p data-report-description></p></div>
                <dl data-report-meta></dl>
              </header>
              <section class="mg-admin-moderation-subject" data-report-subject></section>
              <section class="mg-admin-moderation-account" data-report-account></section>
              <section class="mg-admin-moderation-history" data-report-history></section>
            </div>
          </main>

          <aside class="mg-admin-moderation-actions">
            <header><div><h2>Review actions</h2><p>All actions are permission checked and audit logged.</p></div></header>
            <?php if ($canManageModeration): ?>
              <div class="mg-admin-moderation-action-empty" data-moderation-action-empty>Select a report to enable actions.</div>
              <form class="mg-admin-moderation-action-form mg-hidden" data-moderation-action-form>
                <input type="hidden" name="report_id">
                <label>Action
                  <select name="action" required>
                    <option value="claim">Claim report</option>
                    <option value="note">Add internal note</option>
                    <option value="dismiss">Dismiss report</option>
                    <option value="resolve">Resolve report</option>
                    <option value="hide_content">Hide content</option>
                    <option value="restore_content">Restore content</option>
                    <option value="quarantine_media">Quarantine media</option>
                    <option value="warn_user">Warn user</option>
                    <option value="restrict_posting">Restrict posting</option>
                    <option value="suspend_user">Suspend user</option>
                    <option value="reactivate_user">Reactivate user</option>
                  </select>
                </label>
                <label>Reason
                  <textarea name="reason" rows="6" maxlength="5000" placeholder="Document the evidence and decision."></textarea>
                </label>
                <button class="mg-btn mg-btn-primary" type="submit" disabled data-moderation-action-submit>Apply action</button>
                <div class="mg-admin-moderation-action-status" data-moderation-action-status role="status" aria-live="polite"></div>
              </form>
            <?php else: ?>
              <div class="mg-admin-moderation-readonly"><strong>Read-only access</strong><span>This session can review reports but cannot apply moderation actions.</span></div>
            <?php endif; ?>
          </aside>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
