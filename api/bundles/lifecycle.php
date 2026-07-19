<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_checkout.php';

mg_require_method('GET');
$pdo = mg_db();
mg_bundle_checkout_require_schema($pdo);
$user = mg_authenticated_user();
if (!$user || (int)($user['id'] ?? 0) < 1) {
    mg_fail('Sign in to continue.', 401);
}
$buyerId = (int)$user['id'];
$action = strtolower(trim((string)($_GET['action'] ?? 'list')));

function mg_bundle_lifecycle_component_status(array $row): array
{
    $status = strtolower((string)($row['component_status'] ?? 'pending'));
    $instanceStatus = strtolower((string)($row['microgift_status'] ?? ''));
    $label = match ($instanceStatus !== '' ? $instanceStatus : $status) {
        'claimed' => 'Claimed',
        'redeemed' => 'Redeemed',
        'refunded' => 'Refunded',
        'regifted' => 'Regifted',
        'sent', 'issued', 'active' => 'Delivered',
        'failed' => 'Needs attention',
        'processing' => 'Preparing',
        default => 'Pending',
    };
    return [
        'status' => $instanceStatus !== '' ? $instanceStatus : $status,
        'label' => $label,
        'is_complete' => in_array($instanceStatus, ['claimed','redeemed','refunded','regifted'], true),
        'action_center_url' => !empty($row['microgift_public_id'])
            ? '/inbox.php?microgift=' . rawurlencode((string)$row['microgift_public_id'])
            : null,
    ];
}

function mg_bundle_lifecycle_order(PDO $pdo, string $publicId, int $buyerId): array
{
    $stmt = $pdo->prepare("SELECT o.id,o.public_id,o.order_status,o.payment_status,o.fulfillment_status,o.currency,o.subtotal_cents,o.platform_fee_cents,o.total_cents,o.recipient_name,o.recipient_email,o.reserved_at,o.checkout_started_at,o.paid_at,o.fulfilled_at,o.created_at,b.title,b.cover_asset_url,b.public_id bundle_public_id
        FROM gift_bundle_orders o INNER JOIN gift_bundles b ON b.id=o.bundle_id
        WHERE o.public_id=? AND o.buyer_user_id=? LIMIT 1");
    $stmt->execute([$publicId,$buyerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new MgBundleOrderException('Bundle order not found.',404);

    $components = $pdo->prepare("SELECT c.public_id,c.product_snapshot_json,c.quantity,c.gross_amount_cents,c.component_status,c.microgift_instance_id,
        COALESCE(ms.display_name,u.display_name,u.email) merchant_name,
        mi.public_id microgift_public_id,mi.status microgift_status
        FROM gift_bundle_order_components c
        INNER JOIN users u ON u.id=c.merchant_user_id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived'
        LEFT JOIN microgift_instances mi ON mi.id=c.microgift_instance_id
        WHERE c.bundle_order_id=? ORDER BY c.id");
    $components->execute([(int)$order['id']]);
    $rows = [];
    $complete = 0;
    foreach ($components->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $snapshot = json_decode((string)($row['product_snapshot_json'] ?? '{}'), true);
        $state = mg_bundle_lifecycle_component_status($row);
        if ($state['is_complete']) $complete++;
        $rows[] = [
            'id'=>(string)$row['public_id'],
            'title'=>(string)($snapshot['title'] ?? $snapshot['product_title'] ?? 'Bundle item'),
            'description'=>(string)($snapshot['description'] ?? ''),
            'image_url'=>$snapshot['image_url'] ?? $snapshot['image_snapshot'] ?? null,
            'merchant_name'=>(string)$row['merchant_name'],
            'quantity'=>(int)$row['quantity'],
            'amount_cents'=>(int)$row['gross_amount_cents'],
            'lifecycle'=>$state,
        ];
    }
    unset($order['id']);
    $order['component_count'] = count($rows);
    $order['completed_component_count'] = $complete;
    $order['progress_percent'] = count($rows) > 0 ? (int)floor(($complete / count($rows)) * 100) : 0;
    $order['bundle_url'] = '/bundle.php?id=' . rawurlencode((string)$order['bundle_public_id']);
    return ['order'=>$order,'components'=>$rows];
}

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT o.public_id,o.order_status,o.payment_status,o.fulfillment_status,o.currency,o.total_cents,o.recipient_name,o.recipient_email,o.created_at,o.paid_at,o.fulfilled_at,b.title,b.cover_asset_url,
            (SELECT COUNT(*) FROM gift_bundle_order_components c WHERE c.bundle_order_id=o.id) component_count,
            (SELECT COUNT(*) FROM gift_bundle_order_components c INNER JOIN microgift_instances mi ON mi.id=c.microgift_instance_id WHERE c.bundle_order_id=o.id AND mi.status IN ('claimed','redeemed','refunded','regifted')) completed_component_count
            FROM gift_bundle_orders o INNER JOIN gift_bundles b ON b.id=o.bundle_id
            WHERE o.buyer_user_id=? ORDER BY o.created_at DESC,o.id DESC LIMIT 100");
        $stmt->execute([$buyerId]);
        $orders = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count = (int)$row['component_count'];
            $done = (int)$row['completed_component_count'];
            $row['component_count'] = $count;
            $row['completed_component_count'] = $done;
            $row['progress_percent'] = $count > 0 ? (int)floor(($done / $count) * 100) : 0;
            $row['url'] = '/bundle-order.php?id=' . rawurlencode((string)$row['public_id']);
            $orders[] = $row;
        }
        mg_ok(['orders'=>$orders]);
    }
    if ($action === 'detail') {
        mg_ok(mg_bundle_lifecycle_order($pdo, trim((string)($_GET['id'] ?? '')), $buyerId));
    }
    throw new MgBundleOrderException('Unsupported lifecycle operation.',405);
} catch (MgBundleOrderException $e) {
    mg_fail($e->getMessage(), $e->httpStatus);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_fail_unexpected($e, 'bundle.lifecycle.failure', 'Unable to load bundle lifecycle information.', 500, [], $buyerId);
}
