<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_bundles.php';
require_once __DIR__ . '/_checkout.php';

$pdo = mg_db();
$user = mg_authenticated_user();
if (!$user || (int)($user['id'] ?? 0) < 1) mg_fail('Sign in to continue.', 401);
$actorId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST' ? mg_input() : [];
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? 'summary')));

function mg_bundle_settlement_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
}

function mg_bundle_settlement_require_schema(PDO $pdo): void
{
    foreach (['gift_bundle_component_settlements','gift_bundle_settlement_events'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Product Bundle settlement schema is not installed.');
    }
}

function mg_bundle_settlement_require_merchant(array $user): int
{
    if (function_exists('mg_user_has_merchant_access') && !mg_user_has_merchant_access($user)) {
        mg_fail('Merchant access is required.', 403);
    }
    return (int)$user['id'];
}

function mg_bundle_settlement_sync(PDO $pdo, int $merchantId, int $actorId): array
{
    $stmt = $pdo->prepare("SELECT c.id,c.public_id,c.bundle_order_id,c.merchant_user_id,c.gross_amount_cents,c.commission_amount_cents,c.merchant_net_amount_cents,c.settlement_policy,c.component_status,o.payment_status,o.order_status,o.currency,o.paid_at
        FROM gift_bundle_order_components c
        INNER JOIN gift_bundle_orders o ON o.id=c.bundle_order_id
        LEFT JOIN gift_bundle_component_settlements s ON s.component_id=c.id
        WHERE c.merchant_user_id=? AND s.id IS NULL AND o.payment_status='paid' AND c.component_status IN ('paid','issuance_pending','issued','claimed','redeemed')
        ORDER BY c.id ASC LIMIT 500");
    $stmt->execute([$merchantId]);
    $created = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = in_array((string)$row['component_status'], ['claimed','redeemed'], true) ? 'eligible' : 'pending';
        $eligibleAt = $status === 'eligible' ? date('Y-m-d H:i:s') : null;
        $publicId = mg_bundle_settlement_uuid();
        $snapshot = json_encode([
            'component_public_id'=>$row['public_id'],
            'payment_status'=>$row['payment_status'],
            'order_status'=>$row['order_status'],
            'component_status'=>$row['component_status'],
            'paid_at'=>$row['paid_at'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $insert = $pdo->prepare("INSERT IGNORE INTO gift_bundle_component_settlements
            (public_id,bundle_order_id,component_id,merchant_user_id,currency,gross_amount_cents,commission_amount_cents,merchant_net_amount_cents,payable_amount_cents,settlement_policy,readiness_status,eligible_at,source_snapshot_json,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $insert->execute([$publicId,(int)$row['bundle_order_id'],(int)$row['id'],$merchantId,(string)$row['currency'],(int)$row['gross_amount_cents'],(int)$row['commission_amount_cents'],(int)$row['merchant_net_amount_cents'],(int)$row['merchant_net_amount_cents'],(string)$row['settlement_policy'],$status,$eligibleAt,$snapshot]);
        if ($insert->rowCount() > 0) {
            $settlementId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO gift_bundle_settlement_events (public_id,settlement_id,actor_user_id,event_type,idempotency_key,event_data,created_at) VALUES (?,?,?,?,?,?,NOW())")
                ->execute([mg_bundle_settlement_uuid(),$settlementId,$actorId,'settlement_created','bundle-settlement-created-'.$row['id'],json_encode(['readiness_status'=>$status],JSON_THROW_ON_ERROR)]);
            $created++;
        }
    }
    return ['created'=>$created];
}

function mg_bundle_settlement_summary(PDO $pdo, int $merchantId): array
{
    $summary = $pdo->prepare("SELECT currency,
        COUNT(*) component_count,
        COALESCE(SUM(gross_amount_cents),0) gross_cents,
        COALESCE(SUM(commission_amount_cents),0) platform_fee_cents,
        COALESCE(SUM(merchant_net_amount_cents),0) merchant_net_cents,
        COALESCE(SUM(CASE WHEN readiness_status='eligible' THEN payable_amount_cents ELSE 0 END),0) eligible_cents,
        COALESCE(SUM(CASE WHEN readiness_status IN ('pending','held') THEN payable_amount_cents ELSE 0 END),0) pending_cents,
        COALESCE(SUM(CASE WHEN readiness_status='released' THEN payable_amount_cents ELSE 0 END),0) released_cents
        FROM gift_bundle_component_settlements WHERE merchant_user_id=? GROUP BY currency ORDER BY currency");
    $summary->execute([$merchantId]);
    $rows = $pdo->prepare("SELECT s.public_id,s.currency,s.gross_amount_cents,s.commission_amount_cents,s.merchant_net_amount_cents,s.refunded_amount_cents,s.reversed_amount_cents,s.payable_amount_cents,s.settlement_policy,s.readiness_status,s.hold_reason,s.eligible_at,s.released_at,s.created_at,c.public_id component_public_id,c.component_status,o.public_id order_public_id,b.title bundle_title
        FROM gift_bundle_component_settlements s
        INNER JOIN gift_bundle_order_components c ON c.id=s.component_id
        INNER JOIN gift_bundle_orders o ON o.id=s.bundle_order_id
        INNER JOIN gift_bundles b ON b.id=o.bundle_id
        WHERE s.merchant_user_id=? ORDER BY s.created_at DESC,s.id DESC LIMIT 200");
    $rows->execute([$merchantId]);
    return ['totals'=>$summary->fetchAll(PDO::FETCH_ASSOC),'settlements'=>$rows->fetchAll(PDO::FETCH_ASSOC),'transfer_execution_enabled'=>false];
}

try {
    mg_bundle_settlement_require_schema($pdo);
    $merchantId = mg_bundle_settlement_require_merchant($user);
    if ($method === 'POST' && $action === 'reconcile') {
        mg_require_csrf_for_write($input);
        $pdo->beginTransaction();
        $result = mg_bundle_settlement_sync($pdo,$merchantId,$actorId);
        $pdo->commit();
        mg_ok(['reconciliation'=>$result] + mg_bundle_settlement_summary($pdo,$merchantId));
    }
    if ($method === 'GET' && $action === 'summary') {
        mg_ok(mg_bundle_settlement_summary($pdo,$merchantId));
    }
    mg_fail('Unsupported settlement operation.',405);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(),422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($e,'bundle.settlement.failure','Unable to load bundle settlement information.',500,[],$actorId);
}
