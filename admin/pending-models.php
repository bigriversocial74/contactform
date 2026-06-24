<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/db.php';

$user = mg_require_auth();
$canView = mg_has_permission('admin.users.view');
$page_title = 'Pending Models | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-pending-models-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-table-pages.css'];
$adminActive = 'pending-models';

$rows = [];
if ($canView) {
    $pdo = mg_db();
    $stmt = $pdo->query(
        'SELECT uma.id, uma.public_id, uma.user_id, u.email, u.display_name, um.code, um.name, uma.status, uma.requested_at, uma.reason
         FROM user_model_assignments uma
         INNER JOIN users u ON u.id = uma.user_id
         INNER JOIN user_models um ON um.id = uma.user_model_id
         WHERE uma.status = "pending"
         ORDER BY uma.requested_at DESC, uma.created_at DESC
         LIMIT 100'
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
          <span class="mg-eyebrow">Identity operations</span>
          <h1>Pending models</h1>
          <p>Review requested user model approvals from one protected workspace.</p>
        </div>
        <div class="mg-admin-page-actions"><a class="mg-btn mg-btn-ghost" href="/admin/users.php">User center</a></div>
      </header>
      <?php if (!$canView): ?>
        <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Access is not active.</h2><p>This page requires admin user-view permission.</p></div></section>
      <?php else: ?>
        <section class="mg-admin-table-card">
          <header><div><h2>Model approval queue</h2><p><?= count($rows) ?> pending request<?= count($rows) === 1 ? '' : 's' ?>.</p></div></header>
          <?php if (!$rows): ?>
            <div class="mg-admin-empty-card">No pending model requests.</div>
          <?php else: ?>
            <div class="mg-admin-table-wrap"><table class="mg-admin-table"><thead><tr><th>User</th><th>Model</th><th>Status</th><th>Requested</th><th>Reason</th></tr></thead><tbody>
              <?php foreach ($rows as $row): ?>
                <tr><td><strong><?= mg_e((string)($row['display_name'] ?: $row['email'])) ?></strong><br><small><?= mg_e((string)$row['email']) ?> · User <?= (int)$row['user_id'] ?></small></td><td><strong><?= mg_e((string)$row['name']) ?></strong><br><code><?= mg_e((string)$row['code']) ?></code></td><td><span class="mg-admin-inline-badge is-warning"><?= mg_e((string)$row['status']) ?></span></td><td><?= mg_e((string)($row['requested_at'] ?? '—')) ?></td><td><?= mg_e((string)($row['reason'] ?? '')) ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>