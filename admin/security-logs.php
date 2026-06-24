<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/db.php';

$user = mg_require_auth();
$canView = mg_has_permission('security.logs.view') || mg_has_permission('admin.security_logs.view');
$page_title = 'Security Logs | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-security-logs-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-table-pages.css'];
$adminActive = 'security-logs';
$limit = max(10, min(200, (int)($_GET['limit'] ?? 100)));
$severity = trim((string)($_GET['severity'] ?? ''));
$rows = [];
if ($canView) {
    $pdo = mg_db();
    $where = [];
    $params = [];
    if ($severity !== '') {
        $where[] = 'severity = ?';
        $params[] = $severity;
    }
    $sql = 'SELECT id, request_id, user_id, severity, event_type, message, ip_address, created_at FROM security_logs';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
          <span class="mg-eyebrow">Security activity</span>
          <h1>Security logs</h1>
          <p>Review warnings, errors, critical events, permission denials, and runtime security signals.</p>
        </div>
        <form class="mg-admin-filter-row" method="get"><label>Severity<select name="severity"><option value="">All</option><?php foreach(['info','warning','error','critical'] as $option): ?><option value="<?= mg_e($option) ?>" <?= $severity===$option?'selected':'' ?>><?= mg_e(ucfirst($option)) ?></option><?php endforeach; ?></select></label><label>Limit<input type="number" name="limit" min="10" max="200" value="<?= $limit ?>"></label><button class="mg-btn mg-btn-ghost" type="submit">Refresh</button></form>
      </header>
      <?php if (!$canView): ?>
        <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Access is not active.</h2><p>This page requires security-log view permission.</p></div></section>
      <?php else: ?>
        <section class="mg-admin-table-card">
          <header><div><h2>Recent security events</h2><p><?= count($rows) ?> event<?= count($rows) === 1 ? '' : 's' ?> loaded.</p></div></header>
          <?php if (!$rows): ?><div class="mg-admin-empty-card">No security events are available for this filter.</div><?php else: ?>
            <div class="mg-admin-table-wrap"><table class="mg-admin-table"><thead><tr><th>Event</th><th>Severity</th><th>Message</th><th>User</th><th>IP</th><th>Created</th></tr></thead><tbody>
              <?php foreach ($rows as $row): ?>
                <?php $tone = strtolower((string)$row['severity']); ?>
                <tr><td><strong><?= mg_e((string)$row['event_type']) ?></strong><br><small><?= mg_e((string)($row['request_id'] ?? '')) ?></small></td><td><span class="mg-admin-inline-badge is-<?= mg_e($tone) ?>"><?= mg_e((string)$row['severity']) ?></span></td><td><?= mg_e((string)$row['message']) ?></td><td><?= $row['user_id'] !== null ? (int)$row['user_id'] : '—' ?></td><td><?= mg_e((string)($row['ip_address'] ?? '')) ?></td><td><?= mg_e((string)$row['created_at']) ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>