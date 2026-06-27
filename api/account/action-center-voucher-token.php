<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';
require_once __DIR__ . '/_claim_voucher_token.php';

mg_require_method('GET');
$user = mg_require_api_user();
$userId = (int)($user['id'] ?? 0);
$actionItemId = trim((string)($_GET['action_item_id'] ?? $_GET['id'] ?? ''));
if ($actionItemId === '' || strlen($actionItemId) > 120) {
    mg_fail('Action Center voucher ID is required.', 422);
}

$pdo = mg_db();
try {
    $stmt = $pdo->prepare("SELECT ac.public_id action_item_id, ac.folder, ac.state, ac.user_id, ac.archived_at,
            i.public_id instance_id, i.status instance_status, i.expires_at,
            t.name template_name
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        INNER JOIN microgift_templates t ON t.id=i.template_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1");
    $stmt->execute([$actionItemId, $userId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voucher) {
        mg_fail('Action Center voucher not found.', 404);
    }
    if (!in_array((string)$voucher['folder'], ['inbox','claimed'], true)) {
        mg_fail('Only customer-held vouchers can be prepared for merchant scan.', 409);
    }
    if (in_array((string)$voucher['instance_status'], ['cancelled','revoked','expired'], true)) {
        mg_fail('This voucher is not available for merchant scan.', 409);
    }
    if (!empty($voucher['expires_at']) && strtotime((string)$voucher['expires_at']) < time()) {
        mg_fail('This voucher has expired.', 410);
    }

    $token = mg_claim_voucher_issue_token($actionItemId, $userId, 900);
    $base = rtrim((string)((require dirname(__DIR__) . '/config.php')['app']['base_url'] ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'microgifter.com');
        $base = $scheme . '://' . $host;
    }
    $payloadUrl = $base . '/claim-voucher.php?t=' . rawurlencode($token);

    mg_ok([
        'action_item_id' => $actionItemId,
        'instance_id' => (string)$voucher['instance_id'],
        'token' => $token,
        'expires_at' => date(DATE_ATOM, time() + 900),
        'scan_payload' => $payloadUrl,
        'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=12&format=svg&data=' . rawurlencode($payloadUrl),
        'voucher' => [
            'title' => (string)($voucher['template_name'] ?? 'Microgift voucher'),
            'state' => (string)($voucher['state'] ?? ''),
        ],
    ]);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    mg_security_log('error', 'action_center.voucher_token_failed', 'Unable to issue claim voucher token.', [
        'action_item_id' => $actionItemId,
        'exception_class' => $error::class,
    ], $userId);
    mg_fail('Unable to prepare voucher QR.', 500);
}
