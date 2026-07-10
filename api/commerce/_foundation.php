<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

function mg_commerce_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_cart_empty_totals(): array
{
    return [
        'item_count'=>0,'unit_count'=>0,'currency'=>null,'subtotal_cents'=>0,
        'discount_cents'=>0,'tax_cents'=>0,'platform_fee_cents'=>0,'total_cents'=>0,
    ];
}

function mg_cart_active(PDO $pdo, int $userId, bool $lock = false, bool $create = true): array
{
    $sql = "SELECT * FROM carts WHERE user_id=? AND status='active' ORDER BY id DESC LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cart) {
        $expiresAt = strtotime((string)($cart['expires_at'] ?? ''));
        if ($expiresAt !== false && $expiresAt <= time()) {
            $pdo->prepare("UPDATE carts SET status='expired',updated_at=NOW() WHERE id=? AND status='active'")
                ->execute([(int)$cart['id']]);
            $cart = false;
        }
    }
    if ($cart) return $cart;
    if (!$create) return [];

    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO carts (public_id,user_id,status,expires_at,created_at,updated_at) VALUES (?,?,'active',DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW())")
        ->execute([$publicId,$userId]);
    $stmt = $pdo->prepare('SELECT * FROM carts WHERE id=?');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_cart_recalculate(PDO $pdo, int $cartId): array
{
    if ($cartId < 1) return mg_cart_empty_totals();
    $sum = $pdo->prepare('SELECT COUNT(*) item_count,COALESCE(SUM(quantity),0) unit_count,COALESCE(SUM(line_total_cents),0) subtotal_cents,MIN(currency) currency,COUNT(DISTINCT currency) currency_count,COUNT(DISTINCT merchant_user_id) merchant_count FROM cart_items WHERE cart_id=?');
    $sum->execute([$cartId]);
    $totals = $sum->fetch(PDO::FETCH_ASSOC) ?: [];
    if ((int)($totals['currency_count'] ?? 0) > 1) mg_fail('Cart items must use one currency.',409);
    if ((int)($totals['merchant_count'] ?? 0) > 1) mg_fail('Cart currently supports one merchant.',409);
    $subtotal = (int)($totals['subtotal_cents'] ?? 0);
    $currency = $subtotal > 0 ? (string)$totals['currency'] : null;
    $pdo->prepare('UPDATE carts SET currency=?,subtotal_cents=?,discount_cents=0,tax_cents=0,platform_fee_cents=0,total_cents=?,updated_at=NOW() WHERE id=?')
        ->execute([$currency,$subtotal,$subtotal,$cartId]);
    return [
        'item_count'=>(int)($totals['item_count']??0),
        'unit_count'=>(int)($totals['unit_count']??0),
        'currency'=>$currency,
        'subtotal_cents'=>$subtotal,
        'discount_cents'=>0,
        'tax_cents'=>0,
        'platform_fee_cents'=>0,
        'total_cents'=>$subtotal,
    ];
}

function mg_cart_payload(PDO $pdo, array $cart): array
{
    if (empty($cart['id'])) {
        return ['cart_id'=>'','status'=>'empty','expires_at'=>null,'items'=>[],'totals'=>mg_cart_empty_totals(),'revision'=>'empty'];
    }

    $items = $pdo->prepare(
        "SELECT ci.public_id item_id,ci.product_id,ci.product_version_id,ci.merchant_user_id,
                ci.title_snapshot,ci.quantity,ci.unit_amount_cents,ci.line_total_cents,ci.currency,
                ci.created_at,ci.updated_at,cp.public_id product_public_id,cp.slug product_slug,
                cpv.public_id product_version_public_id,
                (SELECT ca.public_id FROM catalog_product_version_assets cpva
                 INNER JOIN catalog_assets ca ON ca.id=cpva.asset_id AND ca.status='ready'
                 WHERE cpva.product_version_id=ci.product_version_id AND cpva.role IN ('cover','thumbnail')
                 ORDER BY CASE cpva.role WHEN 'cover' THEN 0 ELSE 1 END,cpva.sort_order,cpva.id LIMIT 1) cover_asset_id
         FROM cart_items ci
         INNER JOIN catalog_products cp ON cp.id=ci.product_id
         INNER JOIN catalog_product_versions cpv ON cpv.id=ci.product_version_id
         WHERE ci.cart_id=? ORDER BY ci.id"
    );
    $items->execute([(int)$cart['id']]);
    $rows = array_map(static function(array $row): array {
        $row['quantity'] = (int)$row['quantity'];
        $row['unit_amount_cents'] = (int)$row['unit_amount_cents'];
        $row['line_total_cents'] = (int)$row['line_total_cents'];
        $row['product_url'] = '/product.php?id=' . rawurlencode((string)$row['product_public_id']) . '&p=' . rawurlencode((string)$row['product_slug']);
        $row['cover_url'] = !empty($row['cover_asset_id'])
            ? '/api/public/media.php?asset=' . rawurlencode((string)$row['cover_asset_id'])
            : null;
        return $row;
    }, $items->fetchAll(PDO::FETCH_ASSOC));

    $totals = mg_cart_recalculate($pdo,(int)$cart['id']);
    $revision = hash('sha256', mg_commerce_json([
        'cart_id'=>(string)$cart['public_id'],
        'updated_at'=>(string)($cart['updated_at']??''),
        'items'=>array_map(static fn(array $row): array => [
            'id'=>$row['item_id'],'quantity'=>$row['quantity'],'total'=>$row['line_total_cents'],'updated_at'=>$row['updated_at'],
        ],$rows),
        'totals'=>$totals,
    ]));
    return [
        'cart_id'=>(string)$cart['public_id'],
        'status'=>(string)$cart['status'],
        'expires_at'=>$cart['expires_at'],
        'items'=>$rows,
        'totals'=>$totals,
        'revision'=>$revision,
    ];
}

function mg_resolve_published_product_version(PDO $pdo, string $publicId): array
{
    $stmt=$pdo->prepare("SELECT v.id version_db_id,v.public_id version_id,v.title,v.unit_value_cents,v.currency,p.id product_db_id,p.public_id product_id,p.merchant_user_id FROM catalog_product_versions v INNER JOIN catalog_products p ON p.id=v.product_id WHERE v.public_id=? AND v.version_status='published' AND p.status='published' AND p.current_version_id=v.id LIMIT 1");
    $stmt->execute([$publicId]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$item) mg_fail('Product version is unavailable.',409);
    return $item;
}

function mg_order_history(PDO $pdo,int $orderId,string $domain,?string $from,string $to,string $actorType,?int $actorUserId,?string $reason=null,array $metadata=[]): void
{
    $pdo->prepare('INSERT INTO order_status_history (public_id,order_id,status_domain,from_status,to_status,actor_type,actor_user_id,reason_code,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$orderId,$domain,$from,$to,$actorType,$actorUserId,$reason,mg_commerce_json($metadata)]);
}

function mg_order_event(PDO $pdo,int $orderId,string $eventType,?int $actorUserId,array $payload=[]): void
{
    $pdo->prepare('INSERT INTO order_audit_events (public_id,order_id,event_type,actor_user_id,payload_json,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$orderId,$eventType,$actorUserId,mg_commerce_json($payload)]);
}

function mg_order_payload(PDO $pdo,array $order): array
{
    $items=$pdo->prepare('SELECT public_id item_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,pppm_issuance_request_id FROM commerce_order_items WHERE order_id=? ORDER BY id');
    $items->execute([(int)$order['id']]);
    $history=$pdo->prepare('SELECT public_id history_id,status_domain,from_status,to_status,actor_type,reason_code,metadata_json,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at,id');
    $history->execute([(int)$order['id']]);
    unset($order['id']);
    $order['order_id']=$order['public_id']; unset($order['public_id']);
    $order['items']=$items->fetchAll(PDO::FETCH_ASSOC);
    $order['history']=$history->fetchAll(PDO::FETCH_ASSOC);
    return $order;
}
