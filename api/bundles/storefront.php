<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_checkout.php';

$pdo = mg_db();
mg_bundle_checkout_require_schema($pdo);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = in_array($method, ['POST','PUT','PATCH'], true) ? mg_input() : [];
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? 'list')));

function mg_bundle_storefront_user(): array
{
    $user = mg_authenticated_user();
    if (!$user || (int)($user['id'] ?? 0) < 1) {
        mg_fail('Sign in to continue.', 401);
    }
    return $user;
}

function mg_bundle_storefront_detail(PDO $pdo, string $publicId): array
{
    $stmt = $pdo->prepare("SELECT b.public_id,b.title,b.slug,b.short_statement,b.description,b.cover_asset_url,b.category,b.occasion,b.primary_location,b.service_area,b.estimated_duration,b.bundle_type,b.currency,b.sales_start_at,b.sales_end_at,b.redemption_expires_at,b.inventory_limit,b.published_at,
        COALESCE(ms.display_name,u.display_name,u.full_name,u.email) merchant_name
        FROM gift_bundles b
        INNER JOIN users u ON u.id=b.owner_merchant_user_id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
        WHERE b.public_id=? AND b.status='published' AND b.visibility IN ('public','unlisted')
          AND (b.sales_start_at IS NULL OR b.sales_start_at<=NOW())
          AND (b.sales_end_at IS NULL OR b.sales_end_at>=NOW())
        LIMIT 1");
    $stmt->execute([$publicId]);
    $bundle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bundle) throw new MgBundleOrderException('Published bundle not found.', 404);

    $components = $pdo->prepare("SELECT c.public_id,c.product_title_snapshot title,c.product_description_snapshot description,c.image_snapshot image_url,c.quantity,c.customer_amount_cents,c.claim_policy,c.expiration_rule,c.reservation_requirement,
        COALESCE(ms.display_name,u.display_name,u.full_name,u.email) merchant_name
        FROM gift_bundle_components c
        INNER JOIN users u ON u.id=c.merchant_user_id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
        WHERE c.bundle_id=(SELECT id FROM gift_bundles WHERE public_id=? LIMIT 1) AND c.status='accepted'
        ORDER BY c.display_order,c.id");
    $components->execute([$publicId]);
    $rows = $components->fetchAll(PDO::FETCH_ASSOC);
    $subtotal = 0;
    foreach ($rows as $row) $subtotal += (int)$row['customer_amount_cents'] * max(1, (int)$row['quantity']);
    return ['bundle'=>$bundle,'components'=>$rows,'subtotal_cents'=>$subtotal,'component_count'=>count($rows)];
}

try {
    if ($method === 'GET' && $action === 'list') {
        $q = trim((string)($_GET['q'] ?? ''));
        $params = [];
        $where = "b.status='published' AND b.visibility='public' AND (b.sales_start_at IS NULL OR b.sales_start_at<=NOW()) AND (b.sales_end_at IS NULL OR b.sales_end_at>=NOW())";
        if ($q !== '') {
            $where .= ' AND (LOWER(b.title) LIKE ? OR LOWER(b.short_statement) LIKE ? OR LOWER(b.category) LIKE ? OR LOWER(b.occasion) LIKE ?)';
            $like = '%' . mb_strtolower($q) . '%';
            $params = [$like,$like,$like,$like];
        }
        $stmt = $pdo->prepare("SELECT b.public_id,b.title,b.short_statement,b.cover_asset_url,b.category,b.occasion,b.primary_location,b.currency,b.published_at,
            COALESCE(ms.display_name,u.display_name,u.full_name,u.email) merchant_name,
            (SELECT COUNT(*) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status='accepted') component_count,
            (SELECT COALESCE(SUM(c.customer_amount_cents*c.quantity),0) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status='accepted') subtotal_cents
            FROM gift_bundles b INNER JOIN users u ON u.id=b.owner_merchant_user_id
            LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
            WHERE {$where} ORDER BY b.published_at DESC,b.id DESC LIMIT 60");
        $stmt->execute($params);
        mg_ok(['bundles'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        mg_ok(mg_bundle_storefront_detail($pdo, trim((string)($_GET['id'] ?? ''))));
    }

    if ($method === 'GET' && $action === 'order') {
        $user = mg_bundle_storefront_user();
        $stmt = $pdo->prepare("SELECT o.public_id,o.order_status,o.payment_status,o.fulfillment_status,o.currency,o.subtotal_cents,o.platform_fee_cents,o.total_cents,o.recipient_name,o.recipient_email,o.reserved_at,o.checkout_started_at,o.paid_at,o.fulfilled_at,b.title,b.cover_asset_url
            FROM gift_bundle_orders o INNER JOIN gift_bundles b ON b.id=o.bundle_id
            WHERE o.public_id=? AND o.buyer_user_id=? LIMIT 1");
        $stmt->execute([trim((string)($_GET['id'] ?? '')),(int)$user['id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new MgBundleOrderException('Bundle order not found.',404);
        $components = $pdo->prepare("SELECT c.public_id,c.product_snapshot_json,c.quantity,c.gross_amount_cents,c.component_status,c.microgift_instance_id,COALESCE(ms.display_name,u.display_name,u.email) merchant_name
            FROM gift_bundle_order_components c INNER JOIN users u ON u.id=c.merchant_user_id
            LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
            WHERE c.bundle_order_id=(SELECT id FROM gift_bundle_orders WHERE public_id=? LIMIT 1) ORDER BY c.id");
        $components->execute([(string)$order['public_id']]);
        mg_ok(['order'=>$order,'components'=>$components->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'POST' && $action === 'reserve') {
        $user = mg_bundle_storefront_user();
        mg_require_csrf_for_write($input);
        $bundleId = trim((string)($input['bundle_id'] ?? ''));
        $recipient = [
            'user_id'=>isset($input['recipient_user_id']) ? (int)$input['recipient_user_id'] : null,
            'name'=>trim((string)($input['recipient_name'] ?? '')),
            'email'=>trim((string)($input['recipient_email'] ?? '')),
        ];
        if ($recipient['email'] !== '' && !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid recipient email.');
        $key = trim((string)($input['idempotency_key'] ?? ''));
        $order = mg_bundle_order_reserve($pdo,$bundleId,(int)$user['id'],$recipient,$key,(int)$user['id']);
        mg_ok(['order_id'=>$order['public_id'],'order_status'=>$order['order_status'],'existing'=>(bool)($order['existing'] ?? false)],201);
    }

    if ($method === 'POST' && $action === 'checkout') {
        $user = mg_bundle_storefront_user();
        mg_require_csrf_for_write($input);
        $attempt = mg_bundle_checkout_start($pdo,trim((string)($input['order_id'] ?? '')),(int)$user['id'],trim((string)($input['provider_key'] ?? 'stripe')),trim((string)($input['idempotency_key'] ?? '')));
        mg_ok(['checkout'=>$attempt]);
    }

    throw new MgBundleOrderException('Unsupported storefront operation.',405);
} catch (Throwable $e) {
    $status = $e instanceof MgBundleOrderException ? $e->httpStatus : ($e instanceof InvalidArgumentException ? 422 : 500);
    if ($status >= 500) mg_fail_unexpected($e,'bundle.storefront.failure','Unable to complete the bundle request.',500);
    mg_fail($e->getMessage(),$status);
}
