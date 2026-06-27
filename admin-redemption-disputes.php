<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';
$user = mg_require_permission('admin.audit.view');
$pdo = mg_db();
$stmt = $pdo->query('SELECT d.*, l.settlement_status, l.merchant_net_cents FROM redemption_disputes d LEFT JOIN redemption_settlement_ledger l ON l.receipt_public_id=d.receipt_public_id ORDER BY d.created_at DESC, d.id DESC LIMIT 100');
$disputes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$page_title='Redemption Disputes | Microgifter Admin'; $page_section='admin'; $header_mode='admin';
require __DIR__ . '/includes/header.php';
?>
<main style="padding:34px 18px;background:#f8fafc;min-height:70vh"><section style="max-width:1180px;margin:0 auto"><p style="font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#2563eb;font-weight:900">Admin finance controls</p><h1>Redemption Disputes</h1><p>Review void, refund, reversal, duplicate scan, and customer dispute cases.</p><div style="background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr><th style="text-align:left;padding:10px">Created</th><th style="text-align:left;padding:10px">Type</th><th style="text-align:left;padding:10px">Status</th><th style="text-align:left;padding:10px">Receipt</th><th style="text-align:left;padding:10px">Settlement</th><th style="text-align:left;padding:10px">Reason</th></tr></thead><tbody><?php foreach($disputes as $d): ?><tr style="border-top:1px solid #e2e8f0"><td style="padding:10px"><?= mg_e((string)$d['created_at']) ?></td><td style="padding:10px"><?= mg_e((string)$d['dispute_type']) ?></td><td style="padding:10px"><?= mg_e((string)$d['status']) ?></td><td style="padding:10px"><a href="/claim-receipt-view.php?id=<?= rawurlencode((string)$d['receipt_public_id']) ?>"><?= mg_e((string)$d['receipt_public_id']) ?></a></td><td style="padding:10px"><?= mg_e((string)($d['settlement_status'] ?? '')) ?></td><td style="padding:10px"><?= mg_e((string)$d['reason']) ?></td></tr><?php endforeach; ?><?php if(!$disputes): ?><tr><td colspan="6" style="padding:14px">No redemption disputes yet.</td></tr><?php endif; ?></tbody></table></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
