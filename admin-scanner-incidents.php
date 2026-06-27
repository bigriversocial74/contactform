<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';
$user = mg_require_permission('admin.audit.view');
$pdo = mg_db();
$stmt = $pdo->query('SELECT i.*, d.device_label FROM admin_scanner_incidents i LEFT JOIN scanner_device_sessions d ON d.id=i.scanner_device_session_id ORDER BY i.created_at DESC, i.id DESC LIMIT 100');
$items = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$page_title='Scanner Incidents | Microgifter Admin'; $page_section='admin'; $header_mode='admin';
require __DIR__ . '/includes/header.php';
?>
<main style="padding:34px 18px;background:#f8fafc;min-height:70vh"><section style="max-width:1100px;margin:0 auto"><h1>Scanner Incident Queue</h1><p>Review scanner operations events that need attention.</p><div style="background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px"><?php foreach($items as $item): ?><article style="border-bottom:1px solid #e2e8f0;padding:12px 0"><strong><?= mg_e((string)$item['incident_type']) ?></strong> · <?= mg_e((string)$item['severity']) ?> · <?= mg_e((string)$item['status']) ?><br><small><?= mg_e((string)$item['created_at']) ?> · <?= mg_e((string)($item['device_label'] ?? 'Scanner')) ?> · <?= mg_e((string)($item['gift_public_id'] ?? '')) ?></small><p><?= mg_e((string)$item['summary']) ?></p></article><?php endforeach; ?><?php if(!$items): ?><p>No scanner incidents yet.</p><?php endif; ?></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
