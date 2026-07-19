<?php
declare(strict_types=1);

require_once __DIR__ . '/_bundles.php';

final class MgBundleOrderException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_bundle_order_schema_ready(PDO $pdo): bool
{
    foreach (['gift_bundle_orders','gift_bundle_order_components','gift_bundle_inventory_reservations','gift_bundle_order_events'] as $table) {
        if (!mg_commission_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_bundle_order_require_schema(PDO $pdo): void
{
    if (!mg_bundle_order_schema_ready($pdo)) {
        throw new MgBundleOrderException('Bundle order setup is incomplete. Import database/20260719_product_bundle_orders_components_v2.sql, then retry.', 409);
    }
}

function mg_bundle_order_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_bundle_order_event(PDO $pdo, int $orderId, ?int $componentId, ?int $actorUserId, string $eventType, array $data = [], ?string $idempotencyKey = null): void
{
    $pdo->prepare('INSERT INTO gift_bundle_order_events (public_id,bundle_order_id,component_id,actor_user_id,event_type,idempotency_key,event_data,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$orderId,$componentId,$actorUserId,$eventType,$idempotencyKey,$data ? mg_bundle_order_json($data) : null]);
}

function mg_bundle_order_reserve(PDO $pdo, string $bundlePublicId, int $buyerUserId, array $recipient, string $idempotencyKey, ?int $actorUserId = null): array
{
    mg_bundle_order_require_schema($pdo);
    if ($buyerUserId < 1) throw new InvalidArgumentException('Buyer is required.');
    $idempotencyKey = trim($idempotencyKey);
    if ($idempotencyKey === '') throw new InvalidArgumentException('Idempotency key is required.');

    $existing=$pdo->prepare('SELECT * FROM gift_bundle_orders WHERE idempotency_key=? LIMIT 1');
    $existing->execute([$idempotencyKey]);
    $row=$existing->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row + ['existing'=>true];

    $pdo->beginTransaction();
    try {
        $bundleStmt=$pdo->prepare("SELECT * FROM gift_bundles WHERE public_id=? AND status='published' AND visibility IN ('public','unlisted') LIMIT 1 FOR UPDATE");
        $bundleStmt->execute([$bundlePublicId]);
        $bundle=$bundleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$bundle) throw new MgBundleOrderException('Published bundle not found.',404);

        $componentsStmt=$pdo->prepare("SELECT c.*,p.status product_status,v.version_status FROM gift_bundle_components c INNER JOIN catalog_products p ON p.id=c.product_id INNER JOIN catalog_product_versions v ON v.id=c.product_version_id WHERE c.bundle_id=? AND c.status='accepted' ORDER BY c.display_order,c.id FOR UPDATE");
        $componentsStmt->execute([(int)$bundle['id']]);
        $components=$componentsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$components) throw new MgBundleOrderException('Bundle has no accepted components.',422);

        $subtotal=0; $platformFee=0; $componentSnapshots=[];
        foreach ($components as $component) {
            if ($component['product_status'] !== 'published' || $component['version_status'] !== 'published') {
                throw new MgBundleOrderException('A bundle component is no longer sellable.',422);
            }
            $quantity=max(1,(int)$component['quantity']);
            $gross=(int)$component['customer_amount_cents']*$quantity;
            $commission=(int)$component['commission_amount_cents']*$quantity;
            $subtotal += $gross;
            $platformFee += $commission;
            $componentSnapshots[]=[
                'component_public_id'=>(string)$component['public_id'],
                'merchant_user_id'=>(int)$component['merchant_user_id'],
                'product_id'=>(int)$component['product_id'],
                'product_version_id'=>(int)$component['product_version_id'],
                'title'=>(string)$component['product_title_snapshot'],
                'quantity'=>$quantity,
                'gross_amount_cents'=>$gross,
                'commission_rate_bps'=>(int)$component['commission_rate_bps'],
                'commission_amount_cents'=>$commission,
                'merchant_net_amount_cents'=>((int)$component['merchant_net_amount_cents']*$quantity),
                'commission_source'=>(string)$component['commission_source'],
                'commission_rule_version'=>(string)$component['commission_rule_version'],
            ];
        }

        $fixedFee=0;
        $total=$subtotal+$fixedFee;
        $publicId=mg_public_uuid();
        $bundleSnapshot=['bundle_public_id'=>(string)$bundle['public_id'],'title'=>(string)$bundle['title'],'terms_version'=>(int)$bundle['terms_version'],'components'=>$componentSnapshots];
        $commissionSnapshot=['rule_version'=>MG_COMMISSION_RULE_VERSION,'component_fee_cents'=>$platformFee,'fixed_platform_fee_cents'=>$fixedFee,'total_platform_fee_cents'=>$platformFee+$fixedFee];
        $pdo->prepare("INSERT INTO gift_bundle_orders (public_id,bundle_id,bundle_terms_version,buyer_user_id,recipient_user_id,recipient_name,recipient_email,order_status,payment_status,fulfillment_status,currency,subtotal_cents,platform_fee_cents,total_cents,fixed_platform_fee_cents,component_count,bundle_snapshot_json,commission_snapshot_json,idempotency_key,reserved_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'reserved','unpaid','pending',?,?,?,?,?,?,?, ?,?,NOW(),NOW(),NOW())")
            ->execute([$publicId,(int)$bundle['id'],(int)$bundle['terms_version'],$buyerUserId,$recipient['user_id']??null,trim((string)($recipient['name']??''))?:null,trim((string)($recipient['email']??''))?:null,(string)$bundle['currency'],$subtotal,$platformFee,$total,$fixedFee,count($components),mg_bundle_order_json($bundleSnapshot),mg_bundle_order_json($commissionSnapshot),$idempotencyKey]);
        $orderId=(int)$pdo->lastInsertId();

        $componentInsert=$pdo->prepare("INSERT INTO gift_bundle_order_components (public_id,bundle_order_id,bundle_component_id,merchant_user_id,product_id,product_version_id,quantity,unit_amount_cents,gross_amount_cents,commissionable_amount_cents,commission_rate_bps,commission_amount_cents,merchant_net_amount_cents,commission_source,commission_rule_version,settlement_policy,claim_policy,product_snapshot_json,terms_snapshot_json,inventory_snapshot_json,component_status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'reserved',NOW(),NOW())");
        $reservationInsert=$pdo->prepare("INSERT INTO gift_bundle_inventory_reservations (public_id,bundle_order_id,bundle_component_id,quantity,reservation_status,idempotency_key,expires_at,created_at,updated_at) VALUES (?,?,?,?,'active',?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),NOW(),NOW())");
        foreach ($components as $component) {
            $quantity=max(1,(int)$component['quantity']);
            $gross=(int)$component['customer_amount_cents']*$quantity;
            $commission=(int)$component['commission_amount_cents']*$quantity;
            $net=(int)$component['merchant_net_amount_cents']*$quantity;
            $productSnapshot=['title'=>(string)$component['product_title_snapshot'],'description'=>$component['product_description_snapshot'],'image'=>$component['image_snapshot'],'product_version_id'=>(int)$component['product_version_id']];
            $termsSnapshot=['terms_version'=>(int)$component['terms_version'],'settlement_policy'=>(string)$component['settlement_policy'],'claim_policy'=>(string)$component['claim_policy'],'expiration_rule'=>$component['expiration_rule'],'reservation_requirement'=>$component['reservation_requirement']];
            $inventorySnapshot=['inventory_commitment'=>$component['inventory_commitment'],'reserved_quantity'=>$quantity];
            $componentInsert->execute([mg_public_uuid(),$orderId,(int)$component['id'],(int)$component['merchant_user_id'],(int)$component['product_id'],(int)$component['product_version_id'],$quantity,(int)$component['customer_amount_cents'],$gross,$gross,(int)$component['commission_rate_bps'],$commission,$net,(string)$component['commission_source'],(string)$component['commission_rule_version'],(string)$component['settlement_policy'],(string)$component['claim_policy'],mg_bundle_order_json($productSnapshot),mg_bundle_order_json($termsSnapshot),mg_bundle_order_json($inventorySnapshot)]);
            $reservationInsert->execute([mg_public_uuid(),$orderId,(int)$component['id'],$quantity,$idempotencyKey.':component:'.$component['public_id']]);
        }
        mg_bundle_order_event($pdo,$orderId,null,$actorUserId ?: $buyerUserId,'bundle_order.reserved',['component_count'=>count($components),'total_cents'=>$total],$idempotencyKey.':reserved');
        $pdo->commit();
        $stmt=$pdo->prepare('SELECT * FROM gift_bundle_orders WHERE id=?'); $stmt->execute([$orderId]);
        return ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) + ['existing'=>false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_bundle_order_link_commerce(PDO $pdo, int $bundleOrderId, int $commerceOrderId, array $componentCommerceItemMap, ?int $actorUserId = null): void
{
    mg_bundle_order_require_schema($pdo);
    $pdo->beginTransaction();
    try {
        $order=$pdo->prepare('SELECT * FROM gift_bundle_orders WHERE id=? LIMIT 1 FOR UPDATE');
        $order->execute([$bundleOrderId]);
        $row=$order->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new MgBundleOrderException('Bundle order not found.',404);
        $pdo->prepare("UPDATE gift_bundle_orders SET commerce_order_id=?,order_status='awaiting_payment',payment_status='pending',updated_at=NOW() WHERE id=?")
            ->execute([$commerceOrderId,$bundleOrderId]);
        foreach ($componentCommerceItemMap as $componentPublicId=>$commerceOrderItemId) {
            $pdo->prepare("UPDATE gift_bundle_order_components SET commerce_order_item_id=?,component_status='payment_pending',updated_at=NOW() WHERE bundle_order_id=? AND public_id=?")
                ->execute([(int)$commerceOrderItemId,$bundleOrderId,(string)$componentPublicId]);
        }
        mg_bundle_order_event($pdo,$bundleOrderId,null,$actorUserId,'bundle_order.commerce_linked',['commerce_order_id'=>$commerceOrderId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
