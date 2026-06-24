<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/api/db.php';

$user = mg_require_admin_page_permission('admin.sessions.view');
$canView = true;
$page_title = 'Sessions | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-sessions-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-table-pages.css'];
$adminActive = 'sessions';
$limit = max(10, min(100, (int)($_GET['limit'] ?? 75)));
$rows = [];
if ($canView) {
    $pdo = mg_db();
    $stmt = $pdo->query(
        'SELECT s.id, s.user_id, u.email, u.display_name, s.ip_address, s.user_agent, s.last_seen_at, s.expires_at, s.revoked_at, s.created_at
         FROM user_sessions s
         INNER JOIN users u ON u.id = s.user_id
         ORDER BY s.last_seen_at DESC
         LIMIT ' . $limit
    );
    $rows = $stmt->fetchAll() ?: [];
}

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-table-page">
      <header class="mg-admin-table-hero">
        <div>
          <a class="mg-admin-users-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Security operations</span>
          <h1>Sessions</h1>
          <p>Inspect active, expired, and revoked user sessions across the platform.</p>
        </div>
        <form class="mg-admin-filter-row" method="get"><label>Limit<input type="number" name="limit" min="10" max="100" value="<?= $limit ?>"></label><button class="mg-btn mg-btn-ghost" type="submit">Refresh</button></form>
      </header>
      <?php if (!$canView): ?>
        <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Access is not active.</h2><p>This page requires session-view permission.</p></div></section>
      <?php else: ?>
        <section class="mg-admin-table-card">
          <header><div><h2>Recent sessions</h2><p><?= count($rows) ?> session<?= count($rows) === 1 ? '' : 's' ?> loaded.</p></div></header>
          <?php if (!$rows): ?><div class="mg-admin-empty-card">No user sessions are available.</div><?php else: ?>
            <div class="mg-admin-table-wrap"><table class="mg-admin-table"><thead><tr><th>User</th><th>Status</th><th>IP</th><th>Last seen</th><th>Expires</th><th>Created</th></tr></thead><tbody>
              <?php foreach ($rows as $row): ?>
                <?php $revoked = !empty($row['revoked_at']); ?>
                <tr><td><strong><?= mg_e((string)($row['display_name'] ?: $row['email'])) ?></strong><br><small><?= mg_e((string)$row['email']) ?> · User <?= (int)$row['user_id'] ?></small></td><td><span class="mg-admin-inline-badge <?= $revoked ? 'is-error' : 'is-active' ?>"><?= $revoked ? 'revoked' : 'active' ?></span></td><td><?= mg_e((string)($row['ip_address'] ?? '')) ?></td><td><?= mg_e((string)($row['last_seen_at'] ?? '—')) ?></td><td><?= mg_e((string)($row['expires_at'] ?? '—')) ?></td><td><?= mg_e((string)($row['created_at'] ?? '—')) ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>