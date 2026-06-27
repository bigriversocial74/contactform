<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';
require_once __DIR__ . '/_redemption_finance.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.gifts.redeem');
mg_rate_limit('merchant.redemption_settlements.read', 'user:' . (int)$user['id'], 120, 60);
$pdo = mg_db();
$merchantUserId = (int)$user['id'];
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$stmt = $pdo->prepare('SELECT l.*, r.location_name, r.redeemed_at FROM redemption_settlement_ledger l LEFT JOIN scanner_redemption_receipts r ON r.public_id=l.receipt_public_id WHERE l.merchant_user_id=? ORDER BY l.created_at DESC, l.id DESC LIMIT ' . $limit);
$stmt->execute([$merchantUserId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$summaryStmt = $pdo->prepare("SELECT COUNT(*) total_count, COALESCE(SUM(amount_cents),0) total_amount_cents, COALESCE(SUM(platform_fee_cents),0) total_fee_cents, COALESCE(SUM(merchant_net_cents),0) total_net_cents, COALESCE(SUM(CASE WHEN settlement_status='pending' THEN merchant_net_cents ELSE 0 END),0) pending_net_cents, COALESCE(SUM(CASE WHEN settlement_status='held' THEN merchant_net_cents ELSE 0 END),0) held_net_cents FROM redemption_settlement_ledger WHERE merchant_user_id=?");
$summaryStmt->execute([$merchantUserId]);
mg_ok(['summary' => $summaryStmt->fetch(PDO::FETCH_ASSOC), 'settlements' => $rows], 'Redemption settlements loaded.');
