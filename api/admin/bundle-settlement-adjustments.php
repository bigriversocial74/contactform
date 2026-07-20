<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__) . '/bundles/_bundles.php';

$pdo = mg_db();
$user = mg_authenticated_user();
if (!$user || (int)($user['id'] ?? 0) < 1) mg_fail('Sign in to continue.', 401);
if (!mg_admin_permission_user_has($user, 'commerce.manage') && !mg_admin_permission_user_has($user, 'admin')) {
    mg_fail('Admin commerce access is required.', 403);
}
$actorId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST' ? mg_input() : [];
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? 'queue')));

function mg_bundle_adjustment_require_schema(PDO $pdo): void
{
    foreach (['gift_bundle_component_settlements', 'gift_bundle_settlement_transfers', 'gift_bundle_settlement_adjustments'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Product Bundle adjustment schema is not installed.');
    }
}

function mg_bundle_adjustment_queue(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT a.public_id,a.adjustment_type,a.adjustment_status,a.amount_cents,a.currency,a.reason,a.source_reference,a.provider_reversal_reference,a.created_at,a.approved_at,a.submitted_at,a.succeeded_at,a.failed_at,s.public_id settlement_public_id,s.payable_amount_cents,s.refunded_amount_cents,s.reversed_amount_cents,s.readiness_status,t.public_id transfer_public_id,t.provider_transfer_reference,t.transfer_status,o.public_id order_public_id,b.title bundle_title,COALESCE(ms.display_name,u.display_name,u.email) merchant_name FROM gift_bundle_settlement_adjustments a INNER JOIN gift_bundle_component_settlements s ON s.id=a.settlement_id LEFT JOIN gift_bundle_settlement_transfers t ON t.id=a.transfer_id INNER JOIN gift_bundle_orders o ON o.id=s.bundle_order_id INNER JOIN gift_bundles b ON b.id=o.bundle_id INNER JOIN users u ON u.id=a.merchant_user_id LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived' ORDER BY a.created_at DESC LIMIT 500");
    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'provider_reversal_execution_enabled' => filter_var((string)(getenv('MG_BUNDLE_REVERSAL_EXECUTION_ENABLED') ?: 'false'), FILTER_VALIDATE_BOOL),
    ];
}

function mg_bundle_adjustment_create(PDO $pdo, array $input, int $actorId): array
{
    $settlementPublicId = trim((string)($input['settlement_id'] ?? ''));
    $type = strtolower(trim((string)($input['adjustment_type'] ?? '')));
    $reason = trim((string)($input['reason'] ?? ''));
    $sourceReference = trim((string)($input['source_reference'] ?? ''));
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    $amount = (int)($input['amount_cents'] ?? 0);
    $allowed = ['refund', 'partial_refund', 'dispute', 'reversal_request', 'recovery'];
    if ($settlementPublicId === '' || !in_array($type, $allowed, true) || $reason === '' || $idempotencyKey === '' || $amount < 1) {
        throw new InvalidArgumentException('Settlement, adjustment type, amount, reason, and idempotency key are required.');
    }

    $existing = $pdo->prepare('SELECT public_id,adjustment_status FROM gift_bundle_settlement_adjustments WHERE idempotency_key=? LIMIT 1');
    $existing->execute([$idempotencyKey]);
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) return $row + ['idempotent' => true];

    $stmt = $pdo->prepare("SELECT s.*,t.id transfer_id,t.public_id transfer_public_id,t.provider_transfer_reference,t.transfer_status FROM gift_bundle_component_settlements s LEFT JOIN gift_bundle_settlement_transfers t ON t.settlement_id=s.id WHERE s.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$settlementPublicId]);
    $settlement = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settlement) throw new InvalidArgumentException('Settlement was not found.');
    if ($amount > (int)$settlement['merchant_net_amount_cents']) throw new InvalidArgumentException('Adjustment exceeds the merchant net amount.');

    $status = in_array($type, ['dispute', 'reversal_request'], true) ? 'review_required' : 'created';
    $snapshot = json_encode([
        'settlement_public_id' => $settlementPublicId,
        'readiness_status' => $settlement['readiness_status'],
        'transfer_public_id' => $settlement['transfer_public_id'] ?? null,
        'transfer_status' => $settlement['transfer_status'] ?? null,
        'provider_transfer_reference' => $settlement['provider_transfer_reference'] ?? null,
        'merchant_net_amount_cents' => (int)$settlement['merchant_net_amount_cents'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $publicId = mg_microgift_uuid();
    $pdo->prepare("INSERT INTO gift_bundle_settlement_adjustments (public_id,settlement_id,transfer_id,merchant_user_id,adjustment_type,adjustment_status,amount_cents,currency,reason,source_reference,idempotency_key,request_snapshot_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$publicId,(int)$settlement['id'],$settlement['transfer_id'] ? (int)$settlement['transfer_id'] : null,(int)$settlement['merchant_user_id'],$type,$status,$amount,(string)$settlement['currency'],$reason,$sourceReference !== '' ? $sourceReference : null,$idempotencyKey,$snapshot,$actorId]);

    if (in_array($type, ['refund', 'partial_refund'], true)) {
        $pdo->prepare("UPDATE gift_bundle_component_settlements SET refunded_amount_cents=LEAST(merchant_net_amount_cents,refunded_amount_cents+?),payable_amount_cents=GREATEST(0,payable_amount_cents-?),readiness_status=CASE WHEN ? >= payable_amount_cents THEN 'refunded' ELSE readiness_status END,updated_at=NOW() WHERE id=?")
            ->execute([$amount,$amount,$amount,(int)$settlement['id']]);
    } elseif ($type === 'dispute') {
        $pdo->prepare("UPDATE gift_bundle_component_settlements SET readiness_status='held',hold_reason=?,updated_at=NOW() WHERE id=?")
            ->execute(['Dispute: '.$reason,(int)$settlement['id']]);
    }

    $pdo->prepare("INSERT INTO gift_bundle_settlement_events (public_id,settlement_id,actor_user_id,event_type,idempotency_key,event_data,created_at) VALUES (?,?,?,?,?,?,NOW())")
        ->execute([mg_microgift_uuid(),(int)$settlement['id'],$actorId,'adjustment_'.$type,'adjustment-event-'.$idempotencyKey,json_encode(['adjustment_public_id'=>$publicId,'amount_cents'=>$amount,'status'=>$status,'reason'=>$reason],JSON_THROW_ON_ERROR)]);

    return ['public_id'=>$publicId,'adjustment_status'=>$status,'idempotent'=>false];
}

function mg_bundle_adjustment_approve(PDO $pdo, array $input, int $actorId): array
{
    $publicId = trim((string)($input['adjustment_id'] ?? ''));
    $decision = strtolower(trim((string)($input['decision'] ?? '')));
    $confirmation = strtoupper(trim((string)($input['confirmation'] ?? '')));
    if ($publicId === '' || !in_array($decision, ['approve', 'cancel'], true)) throw new InvalidArgumentException('Adjustment and decision are required.');
    $stmt = $pdo->prepare('SELECT * FROM gift_bundle_settlement_adjustments WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Adjustment was not found.');
    if (!in_array((string)$row['adjustment_status'], ['created','review_required','failed'], true)) throw new InvalidArgumentException('Adjustment is not reviewable.');

    if ($decision === 'cancel') {
        $pdo->prepare("UPDATE gift_bundle_settlement_adjustments SET adjustment_status='cancelled',approved_by_user_id=?,approved_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$row['id']]);
        return ['status'=>'cancelled'];
    }
    if ($confirmation !== 'APPROVE') throw new InvalidArgumentException('Type APPROVE to authorize this adjustment.');

    $dispatchNeeded = in_array((string)$row['adjustment_type'], ['reversal_request','reversal'], true) && !empty($row['transfer_id']);
    $next = $dispatchNeeded ? 'dispatch_pending' : 'approved';
    $pdo->prepare('UPDATE gift_bundle_settlement_adjustments SET adjustment_status=?,approved_by_user_id=?,approved_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute([$next,$actorId,(int)$row['id']]);
    return ['status'=>$next,'provider_dispatch_required'=>$dispatchNeeded,'provider_reversal_execution_enabled'=>false];
}

try {
    mg_bundle_adjustment_require_schema($pdo);
    if ($method === 'GET' && $action === 'queue') mg_ok(mg_bundle_adjustment_queue($pdo));
    if ($method === 'POST' && $action === 'create') {
        mg_require_csrf_for_write($input);
        $pdo->beginTransaction();
        $result = mg_bundle_adjustment_create($pdo,$input,$actorId);
        $pdo->commit();
        mg_ok($result);
    }
    if ($method === 'POST' && $action === 'review') {
        mg_require_csrf_for_write($input);
        $pdo->beginTransaction();
        $result = mg_bundle_adjustment_approve($pdo,$input,$actorId);
        $pdo->commit();
        mg_ok($result);
    }
    mg_fail('Unsupported settlement adjustment operation.',405);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(),422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($e,'bundle.settlement.adjustment.failure','Unable to process the settlement adjustment.',500,[],$actorId);
}
