<?php
declare(strict_types=1);

require_once __DIR__ . '/_foundation.php';

final class MgCheckoutWorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_checkout_order_items_have_merchant(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) return $hasColumn;
    $stmt=$pdo->prepare("SHOW COLUMNS FROM commerce_order_items LIKE 'merchant_user_id'");
    $stmt->execute();
    $hasColumn=(bool)$stmt->fetch();
    return $hasColumn;
}

function mg_checkout_order_payload(PDO $pdo,int $orderId): array
{
    $stmt=$pdo->prepare('SELECT id,public_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,paid_at,cancelled_at,created_at,updated_at FROM commerce_orders WHERE id=? LIMIT 1');
    $stmt->execute([$orderId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new MgCheckoutWorkflowException('Order not found.',404);
    return mg_order_payload($pdo,$order);
}

function mg_checkout_assert_order_replay(array $order,string $draftPublicId): void
{
    if((string)$order['source_type']!=='checkout_draft'||!hash_equals((string)$order['source_reference'],$draftPublicId)){
        throw new MgCheckoutWorkflowException('Order idempotency key is already bound to a different checkout draft.',409);
    }
}

function mg_checkout_create_order(PDO $pdo,int $buyerUserId,string $draftPublicId,string $idempotencyKey): array
{
    $draftPublicId=trim($draftPublicId);$idempotencyKey=trim($idempotencyKey);
    if($draftPublicId===''||$idempotencyKey===''||mb_strlen($idempotencyKey)>190)throw new MgCheckoutWorkflowException('Checkout draft and idempotency key are required.',422);

    $existing=$pdo->prepare('SELECT * FROM commerce_orders WHERE buyer_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$buyerUserId,$idempotencyKey]);
    if($order=$existing->fetch(PDO::FETCH_ASSOC)){
        mg_checkout_assert_order_replay($order,$draftPublicId);
        return ['order'=>mg_checkout_order_payload($pdo,(int)$order['id']),'duplicate'=>true];
    }

    $draftStmt=$pdo->prepare('SELECT * FROM checkout_drafts WHERE public_id=? AND buyer_user_id=? LIMIT 1 FOR UPDATE');
    $draftStmt->execute([$draftPublicId,$buyerUserId]);$draft=$draftStmt->fetch(PDO::FETCH_ASSOC);
    if(!$draft)throw new MgCheckoutWorkflowException('Checkout draft not found.',404);
    if((string)$draft['status']!=='open')throw new MgCheckoutWorkflowException('Checkout draft is not open.',409);
    if(strtotime((string)$draft['expires_at'])<time()){
        $pdo->prepare("UPDATE checkout_drafts SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$draft['id']]);
        throw new MgCheckoutWorkflowException('Checkout draft has expired.',409);
    }
    $items=json_decode((string)$draft['items_json'],true,512,JSON_THROW_ON_ERROR);
    if(!is_array($items)||$items===[])throw new MgCheckoutWorkflowException('Checkout draft has no items.',409);

    $orderPublicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO commerce_orders (public_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'unpaid','pending','checkout_draft',?,?,?,NOW(),NOW())")
        ->execute([$orderPublicId,$buyerUserId,(int)$draft['merchant_user_id'],$draft['currency'],(int)$draft['subtotal_cents'],(int)$draft['discount_cents'],(int)$draft['tax_cents'],(int)$draft['platform_fee_cents'],(int)$draft['total_cents'],$draftPublicId,$idempotencyKey,mg_commerce_json(['checkout_draft_id'=>$draftPublicId,'cart_id'=>(int)$draft['cart_id']])]);
    $orderId=(int)$pdo->lastInsertId();

    if(mg_checkout_order_items_have_merchant($pdo)){
        $line=$pdo->prepare('INSERT INTO commerce_order_items (public_id,order_id,product_id,product_version_id,merchant_user_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,created_at) VALUES (?,?,?,?,?,?,?,?,0,0,?,?,NOW())');
        foreach($items as $item){
            $line->execute([mg_public_uuid(),$orderId,(int)$item['product_id'],(int)$item['product_version_id'],(int)$draft['merchant_user_id'],(string)$item['title_snapshot'],(int)$item['quantity'],(int)$item['unit_amount_cents'],(int)$item['line_total_cents'],(string)$item['currency']]);
        }
    }else{
        $line=$pdo->prepare('INSERT INTO commerce_order_items (public_id,order_id,product_id,product_version_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,created_at) VALUES (?,?,?,?,?,?,?,0,0,?,?,NOW())');
        foreach($items as $item){
            $line->execute([mg_public_uuid(),$orderId,(int)$item['product_id'],(int)$item['product_version_id'],(string)$item['title_snapshot'],(int)$item['quantity'],(int)$item['unit_amount_cents'],(int)$item['line_total_cents'],(string)$item['currency']]);
        }
    }

    $pdo->prepare('INSERT INTO order_fee_snapshots (public_id,order_id,rule_version,calculated_fee_cents,inputs_json,created_at) VALUES (?,?,?,0,?,NOW())')
        ->execute([mg_public_uuid(),$orderId,'stage5j-zero-fee-v1',mg_commerce_json(['subtotal_cents'=>(int)$draft['subtotal_cents'],'currency'=>$draft['currency']])]);
    mg_order_history($pdo,$orderId,'order',null,'pending','user',$buyerUserId,'checkout_draft_converted',['checkout_draft_id'=>$draftPublicId]);
    mg_order_history($pdo,$orderId,'payment',null,'unpaid','system',$buyerUserId,'order_created');
    mg_order_history($pdo,$orderId,'fulfillment',null,'pending','system',$buyerUserId,'order_created');
    mg_order_event($pdo,$orderId,'order.created',$buyerUserId,['checkout_draft_id'=>$draftPublicId]);

    $buyerStmt=$pdo->prepare('SELECT id,email,full_name,display_name FROM users WHERE id=?');$buyerStmt->execute([$buyerUserId]);$buyer=$buyerStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $merchantStmt=$pdo->prepare('SELECT id,email,full_name,display_name FROM users WHERE id=?');$merchantStmt->execute([(int)$draft['merchant_user_id']]);$merchant=$merchantStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $receiptNumber='MG-'.gmdate('Ymd').'-'.strtoupper(substr(str_replace('-','',$orderPublicId),0,12));
    $pdo->prepare("INSERT INTO receipts (public_id,order_id,receipt_number,status,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,buyer_snapshot_json,merchant_snapshot_json,items_snapshot_json,created_at,updated_at) VALUES (?,?,?,'pending',?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([mg_public_uuid(),$orderId,$receiptNumber,$draft['currency'],(int)$draft['subtotal_cents'],(int)$draft['discount_cents'],(int)$draft['tax_cents'],(int)$draft['platform_fee_cents'],(int)$draft['total_cents'],mg_commerce_json($buyer),mg_commerce_json($merchant),mg_commerce_json($items)]);

    $pdo->prepare("UPDATE checkout_drafts SET status='converted',converted_order_id=?,converted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$orderId,(int)$draft['id']]);
    $pdo->prepare("UPDATE carts SET status='converted',converted_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=?")->execute([(int)$draft['cart_id'],$buyerUserId]);

    return ['order'=>mg_checkout_order_payload($pdo,$orderId),'duplicate'=>false];
}
