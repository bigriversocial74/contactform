<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_checkout.php';

$pdo = mg_db();
mg_bundle_checkout_require_schema($pdo);
$user = mg_authenticated_user();
if (!$user || (int)($user['id'] ?? 0) < 1) mg_fail('Sign in to continue.', 401);
$buyerId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'POST' ? mg_input() : [];
$action = strtolower(trim((string)($input['action'] ?? $_GET['action'] ?? 'status')));

function mg_bundle_delivery_context(PDO $pdo, string $componentPublicId, int $buyerId): array
{
    $stmt = $pdo->prepare("SELECT c.id,c.public_id,c.product_snapshot_json,c.component_status,c.microgift_instance_id,
        o.id order_id,o.public_id order_public_id,o.recipient_name,o.recipient_email,o.buyer_user_id,
        b.title bundle_title,mi.public_id microgift_public_id,mi.status microgift_status
        FROM gift_bundle_order_components c
        INNER JOIN gift_bundle_orders o ON o.id=c.bundle_order_id
        INNER JOIN gift_bundles b ON b.id=o.bundle_id
        LEFT JOIN microgift_instances mi ON mi.id=c.microgift_instance_id
        WHERE c.public_id=? AND o.buyer_user_id=? LIMIT 1");
    $stmt->execute([$componentPublicId,$buyerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgBundleOrderException('Bundle component not found.',404);
    return $row;
}

function mg_bundle_delivery_attempts(PDO $pdo, string $componentPublicId): array
{
    $needle = '%"component_id":"' . str_replace(['%','_'],['\\%','\\_'],$componentPublicId) . '"%';
    $count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action='bundle.component.delivery' AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR) AND metadata_json LIKE ?");
    $count->execute([$needle]);
    $last = $pdo->prepare("SELECT created_at,metadata_json FROM audit_logs WHERE action='bundle.component.delivery' AND metadata_json LIKE ? ORDER BY id DESC LIMIT 10");
    $last->execute([$needle]);
    $history = [];
    foreach ($last->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $meta = json_decode((string)($row['metadata_json'] ?? '{}'),true);
        $history[] = [
            'created_at'=>(string)$row['created_at'],
            'channel'=>(string)($meta['channel'] ?? 'email'),
            'status'=>(string)($meta['status'] ?? 'unknown'),
            'recipient'=>(string)($meta['recipient'] ?? ''),
        ];
    }
    return ['attempts_last_hour'=>(int)$count->fetchColumn(),'history'=>$history];
}

try {
    if ($method === 'GET' && $action === 'status') {
        $orderPublicId = trim((string)($_GET['order_id'] ?? ''));
        $stmt = $pdo->prepare("SELECT c.public_id FROM gift_bundle_order_components c INNER JOIN gift_bundle_orders o ON o.id=c.bundle_order_id WHERE o.public_id=? AND o.buyer_user_id=? ORDER BY c.id");
        $stmt->execute([$orderPublicId,$buyerId]);
        $delivery = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $componentId) $delivery[(string)$componentId] = mg_bundle_delivery_attempts($pdo,(string)$componentId);
        mg_ok(['delivery'=>$delivery]);
    }

    if ($method === 'POST' && $action === 'send') {
        mg_require_csrf_for_write($input);
        $componentId = trim((string)($input['component_id'] ?? ''));
        $context = mg_bundle_delivery_context($pdo,$componentId,$buyerId);
        if (empty($context['microgift_public_id'])) throw new MgBundleOrderException('This component is still being prepared.',409);
        if (in_array(strtolower((string)$context['microgift_status']),['claimed','redeemed','refunded','regifted'],true)) throw new MgBundleOrderException('This component no longer needs delivery.',409);
        $email = trim((string)$context['recipient_email']);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Add a valid recipient email before sending.');
        $attempts = mg_bundle_delivery_attempts($pdo,$componentId);
        if ((int)$attempts['attempts_last_hour'] >= 3) throw new MgBundleOrderException('Delivery limit reached. Try again later.',429);

        $snapshot = json_decode((string)($context['product_snapshot_json'] ?? '{}'),true);
        $itemTitle = (string)($snapshot['title'] ?? $snapshot['product_title'] ?? 'Microgift');
        $microgiftPath = '/inbox.php?microgift=' . rawurlencode((string)$context['microgift_public_id']);
        $url = mg_app_base_url() . '/signin.php?return=' . rawurlencode($microgiftPath);
        $recipientName = trim((string)$context['recipient_name']) ?: $email;
        $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($recipientName) . ', a Microgifter bundle item is ready for you.</p>'
            . '<p style="margin:0 0 8px;color:#071225;font-size:18px;font-weight:800;">' . mg_mail_escape($itemTitle) . '</p>'
            . '<p style="margin:0 0 16px;color:#64748b;line-height:1.6;">Part of ' . mg_mail_escape((string)$context['bundle_title']) . '.</p>'
            . mg_email_button($url,'Open your Microgift');
        $sent = mg_send_email($email,'A Microgift is ready for you',mg_email_layout('Your bundle gift is ready',$body,'Open your Microgift bundle item.'),"Your Microgift is ready: {$url}",[
            'bundle_order_id'=>(string)$context['order_public_id'],
            'component_id'=>$componentId,
            'microgift_id'=>(string)$context['microgift_public_id'],
        ]);
        mg_audit('bundle.component.delivery','bundle_component',[
            'component_id'=>$componentId,
            'order_id'=>(string)$context['order_public_id'],
            'microgift_id'=>(string)$context['microgift_public_id'],
            'channel'=>'email',
            'status'=>$sent?'sent':'failed',
            'recipient'=>$email,
        ],$buyerId);
        if (!$sent) throw new MgBundleOrderException('The delivery email could not be sent.',503);
        mg_ok(['status'=>'sent','recipient'=>$email,'sent_at'=>date(DATE_ATOM)]);
    }

    throw new MgBundleOrderException('Unsupported delivery operation.',405);
} catch (MgBundleOrderException $e) {
    mg_fail($e->getMessage(),$e->httpStatus);
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(),422);
} catch (Throwable $e) {
    mg_fail_unexpected($e,'bundle.delivery.failure','Unable to complete bundle delivery.',500,[],$buyerId);
}
