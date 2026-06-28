<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';
require_once __DIR__ . '/_claim_voucher_token.php';

function mg_ac_voucher_claim_code(string $value): string
{
    $code = trim($value);
    if ($code === '' || mb_strlen($code) > 64) mg_fail('Merchant claim code is required.', 422);
    return $code;
}

function mg_ac_voucher_match_claim_code(PDO $pdo, string $merchantCode, array $merchantUserIds): array
{
    $merchantUserIds = array_values(array_unique(array_filter(array_map('intval', $merchantUserIds), static fn (int $id): bool => $id > 0)));
    if ($merchantUserIds === []) mg_fail('No authorized merchant is attached to this voucher.', 409);
    $placeholders = implode(',', array_fill(0, count($merchantUserIds), '?'));
    $candidateHash = hash_hmac('sha256', $merchantCode, mg_claim_code_pepper());
    $sql = "SELECT mcc.*, ml.id location_internal_id, ml.public_id location_public_id, ml.name location_name, ml.status location_status
        FROM merchant_claim_codes mcc
        INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
        WHERE mcc.code_hash=?
          AND mcc.merchant_user_id IN ({$placeholders})
          AND mcc.status='active'
          AND ml.status='active'
          AND (mcc.valid_from IS NULL OR mcc.valid_from<=NOW())
          AND (mcc.valid_until IS NULL OR mcc.valid_until>=NOW())
          AND (mcc.usage_limit IS NULL OR mcc.usage_count<mcc.usage_limit)
        ORDER BY mcc.id DESC
        LIMIT 1 FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$candidateHash], $merchantUserIds));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Invalid merchant claim code for this voucher.', 422);
    return $row;
}

function mg_ac_voucher_attempt_hashes(): array
{
    $pepper = mg_claim_code_pepper();
    return [
        'ip_hash' => hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper),
        'user_agent_hash' => hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper),
    ];
}

function mg_ac_voucher_mark_optional_token(PDO $pdo, string $token, string $actionItemId, int $userId, int $merchantUserId, int $locationId): void
{
    $token = trim($token);
    if ($token === '') return;
    try {
        $row = mg_claim_voucher_require_active($pdo, $token, true);
        if ((int)$row['user_id'] !== $userId) return;
        if ((string)$row['action_item_public_id'] !== $actionItemId) return;
        mg_claim_voucher_mark_scanned($pdo, (int)$row['id'], $merchantUserId, $locationId);
        mg_claim_voucher_mark_redeemed($pdo, (int)$row['id'], $merchantUserId, $locationId);
    } catch (Throwable) {
        return;
    }
}

function mg_ac_voucher_claim_microgift(PDO $pdo, string $actionItemId, string $merchantCode, string $voucherToken, int $userId): void
{
    $stmt = $pdo->prepare("SELECT ac.id action_item_internal_id, ac.public_id action_item_id, ac.folder, ac.state, ac.user_id, ac.archived_at,
            i.id instance_internal_id, i.public_id instance_id, i.status instance_status, i.expires_at, i.owner_user_id, i.recipient_user_id, i.issuer_user_id, i.face_value_cents, i.currency, i.title_snapshot,
            t.name template_name, t.owner_user_id template_owner_user_id
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        INNER JOIN microgift_templates t ON t.id=i.template_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId, $userId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voucher) mg_fail('Action Center voucher not found.', 404);
    if (!in_array((string)$voucher['folder'], ['inbox','claimed'], true)) mg_fail('Only customer-held vouchers can be claimed by merchant code.', 409);
    if (in_array((string)$voucher['instance_status'], ['cancelled','revoked','expired','replaced'], true)) mg_fail('This voucher is not available for claim.', 409);
    if (!empty($voucher['expires_at']) && strtotime((string)$voucher['expires_at']) < time()) {
        $pdo->prepare("UPDATE microgift_instances SET status='expired',updated_at=NOW() WHERE id=? AND status NOT IN ('redeemed','cancelled','revoked')")->execute([(int)$voucher['instance_internal_id']]);
        mg_fail('This voucher has expired.', 410);
    }

    $redemptionStmt = $pdo->prepare("SELECT * FROM microgift_redemptions WHERE instance_id=? AND status='completed' LIMIT 1 FOR UPDATE");
    $redemptionStmt->execute([(int)$voucher['instance_internal_id']]);
    if ($redemptionStmt->fetch(PDO::FETCH_ASSOC)) {
        mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    }

    $claimCode = mg_ac_voucher_match_claim_code($pdo, $merchantCode, [
        (int)($voucher['template_owner_user_id'] ?? 0),
        (int)($voucher['issuer_user_id'] ?? 0),
    ]);

    $claimantUserId = (int)($voucher['owner_user_id'] ?: $voucher['recipient_user_id'] ?: $userId);
    if ($claimantUserId !== $userId) mg_fail('This voucher is not assigned to the active customer account.', 403);

    $redemptionPublicId = mg_public_uuid();
    $locationPublicId = (string)$claimCode['location_public_id'];
    $title = (string)($voucher['title_snapshot'] ?: $voucher['template_name'] ?: 'Microgift');
    $metadata = [
        'source' => 'action_center_manual_code',
        'action_item_id' => $actionItemId,
        'location_id' => $locationPublicId,
        'location_name' => (string)$claimCode['location_name'],
        'merchant_claim_code_id' => (string)$claimCode['public_id'],
        'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''),
    ];
    $idempotencyKey = 'account-manual:' . (string)$voucher['instance_id'] . ':' . $locationPublicId . ':' . $redemptionPublicId;
    $pdo->prepare("INSERT INTO microgift_redemptions (public_id,instance_id,claimant_user_id,merchant_user_id,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,'completed',?,?,NOW(),?,NOW())")->execute([
        $redemptionPublicId,
        (int)$voucher['instance_internal_id'],
        $claimantUserId,
        (int)$claimCode['merchant_user_id'],
        $locationPublicId,
        (int)($voucher['face_value_cents'] ?? 0),
        (string)($voucher['currency'] ?? 'USD'),
        $idempotencyKey,
        'action_center_manual:' . $locationPublicId,
        json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ]);
    $redemptionId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE microgift_instances SET status='redeemed',redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$voucher['instance_internal_id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    $pdo->prepare("UPDATE microgift_inbox_items SET folder='claimed',state='redeemed',merchant_user_id=?,location_id=?,redemption_id=?,redeemed_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=? AND archived_at IS NULL")->execute([(int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id'], $redemptionId, (int)$voucher['action_item_internal_id'], $userId]);
    mg_ac_voucher_mark_optional_token($pdo, $voucherToken, $actionItemId, $userId, (int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id']);
    $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([
        mg_public_uuid(),
        (int)$voucher['instance_internal_id'],
        'action_center_manual_redeemed',
        (int)$claimCode['merchant_user_id'],
        'action_center_manual_code',
        $locationPublicId,
        json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ]);
    mg_audit('microgift.action_center_manual_redeemed', 'microgift_instance', ['instance_id' => (string)$voucher['instance_id'], 'location_id' => $locationPublicId, 'redemption_id' => $redemptionPublicId], (int)$claimCode['merchant_user_id']);
    mg_event('microgift.action_center_manual_redeemed', ['instance_id' => (string)$voucher['instance_id'], 'location_id' => $locationPublicId], (int)$claimCode['merchant_user_id']);
    mg_ok([
        'action_item_id' => $actionItemId,
        'instance_id' => (string)$voucher['instance_id'],
        'redemption_id' => $redemptionPublicId,
        'location_id' => $locationPublicId,
        'location_name' => (string)$claimCode['location_name'],
        'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''),
        'verified' => true,
        'redeemed' => true,
        'gift' => [
            'title' => $title,
            'value_cents' => (int)($voucher['face_value_cents'] ?? 0),
            'currency' => (string)($voucher['currency'] ?? 'USD'),
        ],
    ], 'Gift claimed successfully.');
}

function mg_ac_voucher_claim_wallet(PDO $pdo, string $actionItemId, string $walletId, string $merchantCode, int $userId, string $userEmail): void
{
    $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, $userId, $userEmail, true);
    if (!$wallet) mg_fail('Action Center wallet voucher not found.', 404);
    if (mg_ac_wallet_expired($wallet)) mg_fail('This wallet reward has expired.', 410);
    $status = (string)($wallet['status'] ?? 'issued');
    if ($status === 'redeemed') mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    if (!in_array($status, ['issued','viewed','claimed'], true)) mg_fail('This wallet reward is not available for claim.', 409);

    $claimCode = mg_ac_voucher_match_claim_code($pdo, $merchantCode, [(int)($wallet['merchant_user_id'] ?? 0)]);
    $eventContext = [
        'action_item_id' => $actionItemId,
        'wallet_item_id' => $walletId,
        'location_id' => (string)$claimCode['location_public_id'],
        'location_name' => (string)$claimCode['location_name'],
        'merchant_claim_code_id' => (string)$claimCode['public_id'],
        'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''),
        'source' => 'action_center_manual_code',
    ];
    $pdo->prepare("UPDATE wallet_items SET status='redeemed',claimed_at=COALESCE(claimed_at,NOW()),redeemed_at=NOW(),updated_at=NOW() WHERE id=? AND status<>'redeemed'")->execute([(int)$wallet['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    mg_ac_wallet_event($pdo, $wallet, 'wallet_item.redeemed', $eventContext);
    mg_audit('wallet.action_center_manual_redeemed', 'wallet_item', ['wallet_item_id' => $walletId, 'location_id' => (string)$claimCode['location_public_id']], (int)$claimCode['merchant_user_id']);
    mg_event('wallet.action_center_manual_redeemed', ['wallet_item_id' => $walletId, 'location_id' => (string)$claimCode['location_public_id']], (int)$claimCode['merchant_user_id']);
    $title = trim((string)($wallet['title_snapshot'] ?? '')) ?: trim((string)($wallet['reward_template_title'] ?? '')) ?: 'Microgifter reward';
    mg_ok([
        'action_item_id' => $actionItemId,
        'instance_id' => $walletId,
        'location_id' => (string)$claimCode['location_public_id'],
        'location_name' => (string)$claimCode['location_name'],
        'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''),
        'verified' => true,
        'redeemed' => true,
        'is_wallet_reward' => true,
        'gift' => [
            'title' => $title,
            'value_cents' => (int)($wallet['value_cents_snapshot'] ?? 0),
            'currency' => (string)($wallet['currency_snapshot'] ?? 'USD'),
        ],
    ], 'Gift claimed successfully.');
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$userId = (int)($user['id'] ?? 0);
$userEmail = mg_ac_wallet_user_email($user);
$actionItemId = trim((string)($input['action_item_id'] ?? $input['id'] ?? ''));
if ($actionItemId === '' || strlen($actionItemId) > 120) mg_fail('Action Center voucher ID is required.', 422);
$merchantCode = mg_ac_voucher_claim_code((string)($input['merchant_claim_code'] ?? $input['claim_code'] ?? $input['code'] ?? ''));
$voucherToken = trim((string)($input['voucher_token'] ?? $input['token'] ?? ''));
$pdo = mg_db();

try {
    $pdo->beginTransaction();
    $walletId = mg_ac_wallet_action_id($actionItemId);
    if ($walletId !== null) {
        mg_ac_voucher_claim_wallet($pdo, $actionItemId, $walletId, $merchantCode, $userId, $userEmail);
    }
    mg_ac_voucher_claim_microgift($pdo, $actionItemId, $merchantCode, $voucherToken, $userId);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'action_center.voucher_manual_claim_failed', 'Unable to process manual merchant claim code.', [
        'action_item_id' => $actionItemId,
        'exception_class' => $error::class,
    ], $userId);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to claim this gift right now.', 500);
}