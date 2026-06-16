<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/demand/_prepaid.php';

mg_require_method('GET');
$user = mg_require_permission('demand.commitments.view_own');
$userId = (int)$user['id'];
$pdo = mg_db();
mg_rate_limit('demand.commitments.read', 'user:' . $userId, 120, 60);

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$cursor = isset($_GET['cursor']) ? trim((string)$_GET['cursor']) : null;
$limit = max(1, min((int)($_GET['limit'] ?? 20), 50));

try {
    $pdo->beginTransaction();
    $purchased = mg_prepaid_demand_reconcile_batch($pdo, ['purchaser_user_id'=>$userId], 250);
    $received = mg_prepaid_demand_reconcile_batch($pdo, ['recipient_user_id'=>$userId], 250);
    $pdo->commit();
    $commitments = mg_prepaid_demand_user_commitments($pdo, $userId, $status, $cursor, $limit);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'demand.commitments_read_failed', 'Prepaid demand commitment read failed.', ['exception_class'=>$error::class], $userId);
    mg_fail('Unable to load prepaid commitments.', 500);
}

$summaryStmt = $pdo->prepare("SELECT
    COUNT(*) total_count,
    SUM(p.status='outstanding') outstanding_count,
    SUM(p.status='redeemed') redeemed_count,
    SUM(p.status='expired') expired_count,
    SUM(p.status='canceled') canceled_count,
    COALESCE(SUM(CASE WHEN p.status='outstanding' THEN p.estimated_value_cents ELSE 0 END),0) committed_value_cents,
    COALESCE(SUM(CASE WHEN p.status='redeemed' THEN p.estimated_value_cents ELSE 0 END),0) realized_value_cents
  FROM microgift_demand_commitment_links l
  INNER JOIN purchase_signal_records p ON p.id=l.purchase_signal_id
  INNER JOIN microgift_instances i ON i.id=l.microgift_instance_id
  INNER JOIN commerce_order_items oi ON oi.id=i.commerce_order_item_id
  INNER JOIN commerce_orders o ON o.id=oi.order_id
  WHERE o.buyer_user_id=? OR i.recipient_user_id=?");
$summaryStmt->execute([$userId,$userId]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

mg_event('demand.commitments_read', [
    'result_count'=>count($commitments['items']),
    'status'=>$commitments['status'],
    'reconciled'=>$purchased['processed'] + $received['processed'],
], $userId);
header('Cache-Control: private, no-store, max-age=0');
mg_ok([
    'commitments'=>$commitments,
    'summary'=>[
        'total'=>(int)($summary['total_count'] ?? 0),
        'outstanding'=>(int)($summary['outstanding_count'] ?? 0),
        'redeemed'=>(int)($summary['redeemed_count'] ?? 0),
        'expired'=>(int)($summary['expired_count'] ?? 0),
        'canceled'=>(int)($summary['canceled_count'] ?? 0),
        'committed_value_cents'=>(int)($summary['committed_value_cents'] ?? 0),
        'realized_value_cents'=>(int)($summary['realized_value_cents'] ?? 0),
        'currency'=>'USD',
    ],
    'policy'=>[
        'source'=>'paid_microgift_lifecycle',
        'manual_intent_enabled'=>false,
        'commitment_requires_purchase'=>true,
    ],
]);
