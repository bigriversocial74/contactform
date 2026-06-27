<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';
$user = mg_require_permission('admin.audit.view');
$page_title = 'Scanner Risk Audit | Microgifter Admin';
$page_section = 'admin';
$header_mode = 'admin';
$pdo = mg_db();
$stmt = $pdo->query('SELECT event_type,severity,risk_score,gift_public_id,receipt_public_id,location_public_id,created_at FROM scanner_risk_events ORDER BY created_at DESC, id DESC LIMIT 100');
$events = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
require __DIR__ . '/includes/header.php';
?>
<main style="padding:34px 18px;background:#f8fafc;min-height:70vh">
  <section style="max-width:1100px;margin:0 auto">
    <p style="font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#2563eb;font-weight:900">Admin trust operations</p>
    <h1>Scanner Risk Audit</h1>
    <p>Review scanner verification, redemption, duplicate scan, and exception events.</p>
    <div style="overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr><th style="text-align:left;padding:12px">Time</th><th style="text-align:left;padding:12px">Event</th><th style="text-align:left;padding:12px">Severity</th><th style="text-align:left;padding:12px">Score</th><th style="text-align:left;padding:12px">Gift</th><th style="text-align:left;padding:12px">Receipt</th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
          <tr style="border-top:1px solid #e2e8f0"><td style="padding:12px"><?= mg_e((string)$event['created_at']) ?></td><td style="padding:12px"><?= mg_e((string)$event['event_type']) ?></td><td style="padding:12px"><?= mg_e((string)$event['severity']) ?></td><td style="padding:12px"><?= mg_e((string)$event['risk_score']) ?></td><td style="padding:12px"><?= mg_e((string)$event['gift_public_id']) ?></td><td style="padding:12px"><?php if (!empty($event['receipt_public_id'])): ?><a href="/claim-receipt-view.php?id=<?= rawurlencode((string)$event['receipt_public_id']) ?>"><?= mg_e((string)$event['receipt_public_id']) ?></a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$events): ?><tr><td style="padding:18px" colspan="6">No scanner risk events yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
