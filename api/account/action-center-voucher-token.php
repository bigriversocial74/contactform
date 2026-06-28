<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';
require_once __DIR__ . '/_claim_voucher_token.php';

function mg_ac_completed_microgift_redemption_exists(PDO $pdo, int $instanceId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM microgift_redemptions WHERE instance_id=? AND status='completed' LIMIT 1");
    $stmt->execute([$instanceId]);
    return (bool)$stmt->fetchColumn();
}

function mg_ac_completed_wallet_redemption_exists(PDO $pdo, int $walletItemId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM wallet_item_redemptions WHERE wallet_item_id=? AND status='completed' LIMIT 1");
    $stmt->execute([$walletItemId]);
    return (bool)$stmt->fetchColumn();
}

mg_require_method('GET');
$user = mg_require_api_user();
$userId = (int)($user['id'] ?? 0);
$userEmail = mg_ac_wallet_user_email($user);
$actionItemId = trim((string)($_GET['action_item_id'] ?? $_GET['id'] ?? ''));
if ($actionItemId === '' || strlen($actionItemId) > 120) {
    mg_fail('Action Center voucher ID is required.', 422);
}

$pdo = mg_db();
try {
    $walletId = mg_ac_wallet_action_id($actionItemId);
    if ($walletId !== null) {
        $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, $userId, $userEmail, false);
        if (!$wallet) mg_fail('Action Center wallet voucher not found.', 404);
        if (mg_ac_wallet_expired($wallet)) mg_fail('This wallet reward has expired.', 410);
        $walletStatus = (string)($wallet['status'] ?? 'issued');
        if (mg_ac_completed_wallet_redemption_exists($pdo, (int)$wallet['id'])) {
            mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
        }
        if (!in_array($walletStatus, ['issued','viewed','claimed','redeemed'], true)) {
            mg_fail('This wallet reward is not available for merchant scan.', 409);
        }
        $issued = mg_wallet_claim_voucher_issue_token(
            $pdo,
            (int)$wallet['id'],
            $walletId,
            $userId,
            (int)$wallet['merchant_user_id'],
            900
        );
        mg_ok([
            'action_item_id' => $actionItemId,
            'instance_id' => $walletId,
            'token_id' => $issued['public_id'],
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'scan_payload' => $issued['scan_payload'],
            'qr_image_url' => '/api/account/action-center-voucher-qr.php?wt=' . rawurlencode($issued['token']),
            'is_wallet_reward' => true,
            'voucher' => [
                'title' => trim((string)($wallet['title_snapshot'] ?? '')) ?: trim((string)($wallet['reward_template_title'] ?? '')) ?: 'Microgifter reward',
                'state' => mg_ac_wallet_state($wallet),
            ],
        ]);
    }

    $stmt = $pdo->prepare("SELECT ac.id action_item_internal_id, ac.public_id action_item_id, ac.folder, ac.state, ac.user_id, ac.archived_at,
            i.id instance_internal_id, i.public_id instance_id, i.status instance_status, i.expires_at,
            t.name template_name
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        INNER JOIN microgift_templates t ON t.id=i.template_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1");
    $stmt->execute([$actionItemId, $userId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voucher) mg_fail('Action Center voucher not found.', 404);
    if (!in_array((string)$voucher['folder'], ['inbox','claimed'], true)) mg_fail('Only customer-held vouchers can be prepared for merchant scan.', 409);
    if (mg_ac_completed_microgift_redemption_exists($pdo, (int)$voucher['instance_internal_id'])) mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    if (in_array((string)$voucher['instance_status'], ['cancelled','revoked','expired'], true)) mg_fail('This voucher is not available for merchant scan.', 409);
    if (!empty($voucher['expires_at']) && strtotime((string)$voucher['expires_at']) < time()) mg_fail('This voucher has expired.', 410);

    $issued = mg_claim_voucher_issue_token(
        $pdo,
        (int)$voucher['action_item_internal_id'],
        (string)$voucher['action_item_id'],
        (int)$voucher['instance_internal_id'],
        $userId,
        900
    );

    mg_ok([
        'action_item_id' => $actionItemId,
        'instance_id' => (string)$voucher['instance_id'],
        'token_id' => $issued['public_id'],
        'token' => $issued['token'],
        'expires_at' => $issued['expires_at'],
        'scan_payload' => $issued['scan_payload'],
        'qr_image_url' => '/api/account/action-center-voucher-qr.php?t=' . rawurlencode($issued['token']),
        'is_wallet_reward' => false,
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