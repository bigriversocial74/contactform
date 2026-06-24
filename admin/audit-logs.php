<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/db.php';

$user = mg_require_auth();
$canView = mg_has_permission('admin.audit.view');
$page_title = 'Audit Logs | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-audit-logs-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-table-pages.css'];
$adminActive = 'audit-logs';
$limit = max(10, min(200, (int)($_GET['limit'] ?? 100)));
$rows = [];
if ($canView) {
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id, user_id, action, entity_type, ip_address, user_agent, created_at FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
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
          <span class="mg-eyebrow">Administrative activity</span>
          <h1>Audit logs</h1>
          <p>Inspect recent administrative and platform audit activity without exposing raw metadata.</p>
        </div>
        <form class="mg-admin-filter-row" method="get"><label>Limit<input type="number" name="limit" min="10" max="200" value="<?= $limit ?>"></label><button class="mg-btn mg-btn-ghost" type="submit">Refresh</button></form>
      </header>
      <?php if (!$canView): ?>
        <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Access is not active.</h2><p>This page requires audit-log view permission.</p></div></section>
      <?php else: ?>
        <section class="mg-admin-table-card">
          <header><div><h2>Recent audit events</h2><p><?= count($rows) ?> event<?= count($rows) === 1 ? '' : 's' ?> loaded.</p></div></header>
          <?php if (!$rows): ?><div class="mg-admin-empty-card">No audit activity is available.</div><?php else: ?>
            <div class="mg-admin-table-wrap"><table class="mg-admin-table"><thead><tr><th>Action</th><th>Entity</th><th>User</th><th>IP</th><th>Created</th></tr></thead><tbody>
              <?php foreach ($rows as $row): ?>
                <tr><td><strong><?= mg_e((string)$row['action']) ?></strong></td><td><?= mg_e((string)$row['entity_type']) ?></td><td><?= $row['user_id'] !== null ? (int)$row['user_id'] : '—' ?></td><td><?= mg_e((string)($row['ip_address'] ?? '')) ?></td><td><?= mg_e((string)$row['created_at']) ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>