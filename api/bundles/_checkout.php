<?php
declare(strict_types=1);

require_once __DIR__ . '/_orders.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

function mg_bundle_checkout_schema_ready(PDO $pdo): bool
{
    foreach (['gift_bundle_checkout_attempts','gift_bundle_fulfillment_dispatches'] as $table) {
        if (!mg_commission_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_bundle_checkout_require_schema(PDO $pdo): void
{
    mg_bundle_order_require_schema($pdo);
    if (!mg_bundle_checkout_schema_ready($pdo)) {
        throw new MgBundleOrderException('Bundle checkout setup is incomplete. Import database/20260719_product_bundle_checkout_fulfillment_v3.sql, then retry.', 409);
    }
}

function mg_bundle_checkout_start(PDO $pdo, string $bundleOrderPublicId, int $buyerUserId, string $providerKey, string $idempotencyKey): array
{
    mg_bundle_checkout_require_schema($pdo);
    $providerKey = mg_payment_checkout_provider_key($pdo, $providerKey);
    $idempotencyKey = trim($idempotencyKey);
    if ($buyerUserId < 1 || $idempotencyKey === '') throw new InvalidArgumentException('Buyer and idempotency key are required.');

    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM gift_bundle_orders WHERE public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$bundleOrderPublicId]);
        $order=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new MgBundleOrderException('Bundle order not found.',404);
        if ((int)$order['buyer_user_id'] !== $buyerUserId) throw new MgBundleOrderException('Bundle order ownership mismatch.',403);
        if (!in_array((string)$order['order_status'],['reserved','awaiting_payment'],true)) throw new MgBundleOrderException('Bundle order is not available for checkout.',409);
        if ((string)$order['payment_status'] === 'paid') throw new MgBundleOrderException('Bundle order is already paid.',409);

        $existing=$pdo->prepare('SELECT * FROM gift_bundle_checkout_attempts WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
        $existing->execute([$idempotencyKey]);
        if ($attempt=$existing->fetch(PDO::FETCH_ASSOC)) {
            if ((int)$attempt['bundle_order_id'] !== (int)$order['id'] || (int)$attempt['amount_cents'] !== (int)$order['total_cents'] || (string)$attempt['currency'] !== (string)$order['currency']) {
                throw new MgBundleOrderException('Checkout idempotency key is already bound to a different request.',409);
            }
            $intent=$pdo->prepare('SELECT * FROM payment_intents WHERE id=? LIMIT 1');
            $intent->execute([(int)$attempt['payment_intent_id']]);
            $intentRow=$intent->fetch(PDO::FETCH_ASSOC) ?: [];
            $pdo->commit();
            return $attempt + ['payment_intent'=>$intentRow,'duplicate'=>true];
        }

        $intent=mg_payment_create_source_intent($pdo,[
            'provider_key'=>$providerKey,
            'source_type'=>'gift_bundle_order',
            'source_reference'=>(string)$order['public_id'],
            'idempotency_key'=>$idempotencyKey.':intent',
            'amount_cents'=>(int)$order['total_cents'],
            'currency'=>(string)$order['currency'],
            'metadata'=>['gift_bundle_order_id'=>(string)$order['public_id'],'buyer_user_id'=>$buyerUserId],
        ]);

        $status=match((string)$intent['status']){
            'requires_action','requires_payment_method'=>'requires_action',
            'processing'=>'processing',
            'succeeded'=>'succeeded',
            'cancelled'=>'cancelled',
            'failed'=>'failed',
            default=>'created',
        };
        $publicId=mg_public_uuid();
        $pdo->prepare('INSERT INTO gift_bundle_checkout_attempts (public_id,bundle_order_id,buyer_user_id,payment_intent_id,provider_key,provider_intent_reference,amount_cents,currency,checkout_status,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([$publicId,(int)$order['id'],$buyerUserId,(int)$intent['id'],$providerKey,(string)$intent['provider_intent_reference'],(int)$order['total_cents'],(string)$order['currency'],$status,$idempotencyKey,mg_bundle_order_json(['client_secret_present'=>!empty($intent['client_secret'])])]);
        $attemptId=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE gift_bundle_orders SET payment_intent_id=?,order_status='awaiting_payment',payment_status='pending',checkout_started_at=COALESCE(checkout_started_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([(int)$intent['id'],(int)$order['id']]);
        mg_bundle_order_event($pdo,(int)$order['id'],null,$buyerUserId,'bundle_order.checkout_started',['provider_key'=>$providerKey,'payment_intent_id'=>(int)$intent['id']],$idempotencyKey.':started');
        $pdo->commit();
        return ['id'=>$attemptId,'public_id'=>$publicId,'checkout_status'=>$status,'payment_intent'=>$intent,'duplicate'=>false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_bundle_checkout_mark_paid(PDO $pdo, string $providerIntentReference, ?int $actorUserId = null): array
{
    mg_bundle_checkout_require_schema($pdo);
    $providerIntentReference=trim($providerIntentReference);
    if ($providerIntentReference==='') throw new InvalidArgumentException('Provider payment reference is required.');

    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT a.*,o.public_id order_public_id,o.payment_status,o.order_status FROM gift_bundle_checkout_attempts a INNER JOIN gift_bundle_orders o ON o.id=a.bundle_order_id WHERE a.provider_intent_reference=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$providerIntentReference]);
        $attempt=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attempt) throw new MgBundleOrderException('Bundle checkout attempt not found.',404);
        $orderId=(int)$attempt['bundle_order_id'];
        $transitioned=(string)$attempt['payment_status']!=='paid';
        if ($transitioned) {
            $pdo->prepare("UPDATE payment_intents SET status='succeeded',authorized_at=COALESCE(authorized_at,NOW()),captured_at=COALESCE(captured_at,NOW()),updated_at=NOW() WHERE id=?")
                ->execute([(int)$attempt['payment_intent_id']]);
            $pdo->prepare("UPDATE gift_bundle_checkout_attempts SET checkout_status='succeeded',completed_at=COALESCE(completed_at,NOW()),updated_at=NOW() WHERE id=?")
                ->execute([(int)$attempt['id']]);
            $pdo->prepare("UPDATE gift_bundle_orders SET order_status='paid',payment_status='paid',fulfillment_status='pending',paid_at=COALESCE(paid_at,NOW()),updated_at=NOW() WHERE id=?")
                ->execute([$orderId]);
            $pdo->prepare("UPDATE gift_bundle_inventory_reservations SET reservation_status='committed',committed_at=COALESCE(committed_at,NOW()),updated_at=NOW() WHERE bundle_order_id=? AND reservation_status='active'")
                ->execute([$orderId]);
            $components=$pdo->prepare('SELECT id,public_id FROM gift_bundle_order_components WHERE bundle_order_id=? ORDER BY id FOR UPDATE');
            $components->execute([$orderId]);
            $insert=$pdo->prepare("INSERT IGNORE INTO gift_bundle_fulfillment_dispatches (public_id,bundle_order_id,component_id,dispatch_type,dispatch_status,idempotency_key,created_at,updated_at) VALUES (?,?,?,'microgift','pending',?,NOW(),NOW())");
            foreach ($components->fetchAll(PDO::FETCH_ASSOC) as $component) {
                $insert->execute([mg_public_uuid(),$orderId,(int)$component['id'],'bundle-order:'.$attempt['order_public_id'].':component:'.$component['public_id'].':microgift']);
            }
            mg_bundle_order_event($pdo,$orderId,null,$actorUserId,'bundle_order.payment_succeeded',['provider_intent_reference'=>$providerIntentReference],$attempt['idempotency_key'].':paid');
        }
        $pdo->commit();
        return ['bundle_order_id'=>(string)$attempt['order_public_id'],'payment_transitioned'=>$transitioned,'fulfillment_queued'=>true];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_bundle_fulfillment_dispatch(PDO $pdo, int $dispatchId, callable $microgiftIssuer, ?int $actorUserId = null): array
{
    mg_bundle_checkout_require_schema($pdo);
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT d.*,c.*,o.public_id order_public_id,o.buyer_user_id,o.recipient_user_id,o.payment_status FROM gift_bundle_fulfillment_dispatches d INNER JOIN gift_bundle_order_components c ON c.id=d.component_id INNER JOIN gift_bundle_orders o ON o.id=d.bundle_order_id WHERE d.id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$dispatchId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new MgBundleOrderException('Bundle fulfillment dispatch not found.',404);
        if ((string)$row['payment_status']!=='paid') throw new MgBundleOrderException('Bundle order must be paid before fulfillment.',409);
        if ((string)$row['dispatch_status']==='completed') { $pdo->commit(); return ['completed'=>true,'duplicate'=>true]; }
        $pdo->prepare("UPDATE gift_bundle_fulfillment_dispatches SET dispatch_status='processing',attempt_count=attempt_count+1,started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$dispatchId]);
        $result=$microgiftIssuer($pdo,$row,[
            'idempotency_key'=>(string)$row['idempotency_key'],
            'owner_user_id'=>(int)($row['recipient_user_id'] ?: $row['buyer_user_id']),
            'quantity'=>(int)$row['quantity'],
            'merchant_user_id'=>(int)$row['merchant_user_id'],
            'product_id'=>(int)$row['product_id'],
            'product_version_id'=>(int)$row['product_version_id'],
        ]);
        $instanceId=(int)($result['microgift_instance_id']??0);
        $pdo->prepare("UPDATE gift_bundle_fulfillment_dispatches SET dispatch_status='completed',external_reference=?,result_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(string)($result['external_reference']??''),mg_bundle_order_json($result),$dispatchId]);
        $pdo->prepare("UPDATE gift_bundle_order_components SET microgift_instance_id=COALESCE(?,microgift_instance_id),component_status='issued',issued_at=COALESCE(issued_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$instanceId ?: null,(int)$row['component_id']]);
        $remaining=$pdo->prepare("SELECT COUNT(*) FROM gift_bundle_fulfillment_dispatches WHERE bundle_order_id=? AND dispatch_status<>'completed'");
        $remaining->execute([(int)$row['bundle_order_id']]);
        $complete=(int)$remaining->fetchColumn()===0;
        $pdo->prepare("UPDATE gift_bundle_orders SET fulfillment_status=?,order_status=?,fulfillment_started_at=COALESCE(fulfillment_started_at,NOW()),fulfilled_at=IF(?,COALESCE(fulfilled_at,NOW()),fulfilled_at),updated_at=NOW() WHERE id=?")
            ->execute([$complete?'fulfilled':'processing',$complete?'fulfilled':'paid',$complete?1:0,(int)$row['bundle_order_id']]);
        mg_bundle_order_event($pdo,(int)$row['bundle_order_id'],(int)$row['component_id'],$actorUserId,'bundle_order.component_issued',['dispatch_id'=>$dispatchId,'complete'=>$complete],(string)$row['idempotency_key'].':completed');
        $pdo->commit();
        return ['completed'=>true,'duplicate'=>false,'bundle_complete'=>$complete,'result'=>$result];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
