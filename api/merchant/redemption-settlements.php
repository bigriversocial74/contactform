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
$summaryStmt = $pdo->prepare("SELECT COUNT(*) total_count, COALESCE(SUM(face_value_cents),0) total_face_value_cents, COALESCE(SUM(customer_paid_cents),0) total_customer_paid_cents, COALESCE(SUM(merchant_collected_cents),0) total_merchant_collected_cents, COALESCE(SUM(microgifter_collected_cents),0) total_microgifter_collected_cents, COALESCE(SUM(platform_fee_cents),0) total_fee_cents, COALESCE(SUM(payout_due_cents),0) total_payout_due_cents, COALESCE(SUM(CASE WHEN cash_movement='none' THEN face_value_cents ELSE 0 END),0) promotional_face_value_cents, COALESCE(SUM(CASE WHEN cash_movement='stripe_connect' THEN customer_paid_cents ELSE 0 END),0) stripe_connect_paid_cents, COALESCE(SUM(CASE WHEN reconciliation_status='in_review' THEN face_value_cents ELSE 0 END),0) in_review_value_cents FROM redemption_settlement_ledger WHERE merchant_user_id=?");
$summaryStmt->execute([$merchantUserId]);
mg_ok(['summary' => $summaryStmt->fetch(PDO::FETCH_ASSOC), 'settlements' => $rows], 'Redemption value reconciliation loaded.');
