<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';
require_once __DIR__ . '/_claim_voucher_token.php';
require_once __DIR__ . '/_action_center_claim_projection.php';

final class MgAcVoucherClaimError extends RuntimeException
{
    public int $status;
    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function mg_ac_voucher_fail(string $message, int $status = 400): never
{
    throw new MgAcVoucherClaimError($message, $status);
}

function mg_ac_voucher_claim_code(string $value): string
{
    $code = trim($value);
    if ($code === '' || mb_strlen($code) > 64) mg_fail('Merchant claim code is required.', 422);
    return $code;
}

function mg_ac_voucher_hashes(): array
{
    $pepper = mg_claim_code_pepper();
    return [
        'ip_hash' => hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper),
        'user_agent_hash' => hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper),
    ];
}

function mg_ac_voucher_log_attempt(PDO $pdo, string $actionItemId, int $userId, bool $success, ?string $failureReason = null, ?int $merchantUserId = null, ?int $walletItemId = null, ?int $microgiftInstanceId = null, array $metadata = []): void
{
    $hashes = mg_ac_voucher_hashes();
    $pdo->prepare('INSERT INTO action_center_voucher_claim_attempts (public_id,action_item_id,user_id,merchant_user_id,wallet_item_id,microgift_instance_id,successful,failure_reason,ip_hash,user_agent_hash,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')->execute([
        mg_public_uuid(),
        $actionItemId,
        $userId > 0 ? $userId : null,
        $merchantUserId && $merchantUserId > 0 ? $merchantUserId : null,
        $walletItemId && $walletItemId > 0 ? $walletItemId : null,
        $microgiftInstanceId && $microgiftInstanceId > 0 ? $microgiftInstanceId : null,
        $success ? 1 : 0,
        $failureReason,
        $hashes['ip_hash'],
        $hashes['user_agent_hash'],
        $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function mg_ac_voucher_recent_failed_attempts(PDO $pdo, string $actionItemId, int $userId): int
{
    $hashes = mg_ac_voucher_hashes();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM action_center_voucher_claim_attempts WHERE action_item_id=? AND user_id=? AND successful=0 AND created_at>=DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$actionItemId, $userId]);
    $userFailures = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM action_center_voucher_claim_attempts WHERE action_item_id=? AND ip_hash=? AND successful=0 AND created_at>=DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$actionItemId, $hashes['ip_hash']]);
    return max($userFailures, (int)$stmt->fetchColumn());
}

function mg_ac_voucher_assert_not_locked(PDO $pdo, string $actionItemId, int $userId): void
{
    if (mg_ac_voucher_recent_failed_attempts($pdo, $actionItemId, $userId) >= 5) {
        mg_ac_voucher_fail('Too many invalid merchant claim-code attempts. Try again later or use the merchant scanner.', 423);
    }
}

function mg_ac_voucher_record_failed_code(PDO $pdo, string $actionItemId, int $userId, ?int $walletItemId, ?int $microgiftInstanceId): never
{
    mg_ac_voucher_log_attempt($pdo, $actionItemId, $userId, false, 'invalid_merchant_claim_code', null, $walletItemId, $microgiftInstanceId);
    if (mg_ac_voucher_recent_failed_attempts($pdo, $actionItemId, $userId) >= 5) {
        mg_ac_voucher_fail('This manual claim entry is temporarily locked after too many invalid attempts.', 423);
    }
    mg_ac_voucher_fail('Invalid merchant claim code for this voucher.', 422);
}

function mg_ac_voucher_find_claim_code(PDO $pdo, string $merchantCode, array $merchantUserIds): ?array
{
    $merchantUserIds = array_values(array_unique(array_filter(array_map('intval', $merchantUserIds), static fn (int $id): bool => $id > 0)));
    if ($merchantUserIds === []) mg_ac_voucher_fail('No authorized merchant is attached to this voucher.', 409);
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
    return $row ?: null;
}

function mg_ac_voucher_completed_microgift_redemption(PDO $pdo, int $instanceId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM microgift_redemptions WHERE instance_id=? AND status='completed' LIMIT 1 FOR UPDATE");
    $stmt->execute([$instanceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_ac_voucher_completed_wallet_redemption(PDO $pdo, int $walletItemId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM wallet_item_redemptions WHERE wallet_item_id=? AND status='completed' LIMIT 1 FOR UPDATE");
    $stmt->execute([$walletItemId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_ac_voucher_microgift_redemption_cycle(PDO $pdo, int $instanceId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM microgift_redemptions WHERE instance_id=?');
    $stmt->execute([$instanceId]);
    return (int)$stmt->fetchColumn() + 1;
}

function mg_ac_voucher_wallet_redemption_cycle(PDO $pdo, int $walletItemId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM wallet_item_redemptions WHERE wallet_item_id=?');
    $stmt->execute([$walletItemId]);
    return (int)$stmt->fetchColumn() + 1;
}

function mg_ac_voucher_mark_optional_micro_token(PDO $pdo, string $token, string $actionItemId, int $userId, int $merchantUserId, int $locationId): void
{
    $token = trim($token);
    if ($token === '' || !str_starts_with($token, 'mgv1_')) return;
    try {
        $row = mg_claim_voucher_require_active($pdo, $token, true);
        if ((int)$row['user_id'] !== $userId) return;
        if ((string)$row['action_item_public_id'] !== $actionItemId) return;
        mg_claim_voucher_mark_scanned($pdo, (int)$row['id'], $merchantUserId, $locationId);
        mg_claim_voucher_mark_redeemed($pdo, (int)$row['id'], $merchantUserId, $locationId);
    } catch (Throwable) {}
}

function mg_ac_voucher_mark_optional_wallet_token(PDO $pdo, string $token, string $walletId, int $userId, int $merchantUserId, int $locationId): void
{
    $token = trim($token);
    if ($token === '' || !str_starts_with($token, 'mgwv1_')) return;
    try {
        $row = mg_wallet_claim_voucher_require_active($pdo, $token, true);
        if ((int)$row['user_id'] !== $userId) return;
        if ((string)$row['wallet_item_public_id'] !== $walletId) return;
        mg_wallet_claim_voucher_mark_scanned($pdo, (int)$row['id'], $merchantUserId, $locationId);
        mg_wallet_claim_voucher_mark_redeemed($pdo, (int)$row['id'], $merchantUserId, $locationId);
    } catch (Throwable) {}
}

function mg_ac_voucher_claim_microgift(PDO $pdo, string $actionItemId, string $merchantCode, string $voucherToken, int $userId): void
{
    $stmt = $pdo->prepare("SELECT ac.id action_item_internal_id, ac.public_id action_item_id, ac.folder, ac.state, ac.user_id, ac.archived_at,
            i.id instance_internal_id, i.public_id instance_id, i.status instance_status, i.expires_at, i.owner_user_id, i.recipient_user_id, i.issuer_user_id, i.face_value_cents, i.currency, i.title_snapshot, i.pppm_item_id,
            t.name template_name, t.owner_user_id template_owner_user_id
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        INNER JOIN microgift_templates t ON t.id=i.template_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId, $userId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voucher) mg_ac_voucher_fail('Action Center voucher not found.', 404);
    if (!in_array((string)$voucher['folder'], ['inbox','claimed'], true)) mg_ac_voucher_fail('Only customer-held vouchers can be claimed by merchant code.', 409);
    if (in_array((string)$voucher['instance_status'], ['cancelled','revoked','expired','replaced'], true)) mg_ac_voucher_fail('This voucher is not available for claim.', 409);
    if (!empty($voucher['expires_at']) && strtotime((string)$voucher['expires_at']) < time()) {
        $pdo->prepare("UPDATE microgift_instances SET status='expired',updated_at=NOW() WHERE id=? AND status NOT IN ('redeemed','cancelled','revoked')")->execute([(int)$voucher['instance_internal_id']]);
        mg_ac_voucher_fail('This voucher has expired.', 410);
    }
    if (mg_ac_voucher_completed_microgift_redemption($pdo, (int)$voucher['instance_internal_id'])) mg_ac_voucher_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    $claimantUserId = (int)($voucher['owner_user_id'] ?: $voucher['recipient_user_id'] ?: $userId);
    if ($claimantUserId !== $userId) mg_ac_voucher_fail('This voucher is not assigned to the active customer account.', 403);
    mg_ac_voucher_assert_not_locked($pdo, $actionItemId, $userId);
    $claimCode = mg_ac_voucher_find_claim_code($pdo, $merchantCode, [(int)($voucher['template_owner_user_id'] ?? 0), (int)($voucher['issuer_user_id'] ?? 0)]);
    if (!$claimCode) mg_ac_voucher_record_failed_code($pdo, $actionItemId, $userId, null, (int)$voucher['instance_internal_id']);

    $redemptionPublicId = mg_public_uuid();
    $locationPublicId = (string)$claimCode['location_public_id'];
    $title = (string)($voucher['title_snapshot'] ?: $voucher['template_name'] ?: 'Microgift');
    $cycle = mg_ac_voucher_microgift_redemption_cycle($pdo, (int)$voucher['instance_internal_id']);
    $idempotencyKey = 'account-manual:' . (string)$voucher['instance_id'] . ':' . $locationPublicId . ':' . $cycle;
    $correlationId = mg_public_uuid();
    $metadata = ['source'=>'action_center_manual_code','action_item_id'=>$actionItemId,'location_id'=>$locationPublicId,'location_name'=>(string)$claimCode['location_name'],'merchant_claim_code_id'=>(string)$claimCode['public_id'],'claim_code_last4'=>(string)($claimCode['code_last4'] ?? ''),'correlation_id'=>$correlationId];
    mg_ac_voucher_log_attempt($pdo, $actionItemId, $userId, true, null, (int)$claimCode['merchant_user_id'], null, (int)$voucher['instance_internal_id'], ['location_id'=>$locationPublicId]);
    $attempt = mg_ac_projection_record_attempt($pdo, (int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id'], (int)$claimCode['id'], $userId, (int)$voucher['instance_internal_id'], $idempotencyKey, $correlationId);
    $pdo->prepare("INSERT INTO microgift_redemptions (public_id,instance_id,claimant_user_id,merchant_user_id,location_id,merchant_claim_code_id,claim_attempt_id,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,'completed',?,?,NOW(),?,NOW())")->execute([
        $redemptionPublicId,(int)$voucher['instance_internal_id'],$claimantUserId,(int)$claimCode['merchant_user_id'],(int)$claimCode['location_internal_id'],(int)$claimCode['id'],(int)$attempt['id'],$locationPublicId,(int)($voucher['face_value_cents'] ?? 0),(string)($voucher['currency'] ?? 'USD'),$idempotencyKey,'action_center_manual:' . $locationPublicId,json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ]);
    $redemptionId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE microgift_instances SET status='redeemed',claimed_at=COALESCE(claimed_at,NOW()),redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$voucher['instance_internal_id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    $pdo->prepare("UPDATE microgift_inbox_items SET folder='claimed',state='redeemed',merchant_user_id=?,location_id=?,redemption_id=?,claimed_at=COALESCE(claimed_at,NOW()),redeemed_at=NOW(),updated_at=NOW() WHERE id=? AND user_id=? AND archived_at IS NULL")->execute([(int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id'], $redemptionId, (int)$voucher['action_item_internal_id'], $userId]);
    mg_ac_voucher_mark_optional_micro_token($pdo, $voucherToken, $actionItemId, $userId, (int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id']);
    $notify = mg_ac_projection_notify($pdo, $userId, (int)$claimCode['merchant_user_id'], $userId, $title, (int)($voucher['face_value_cents'] ?? 0), (string)($voucher['currency'] ?? 'USD'), $locationPublicId, (string)$claimCode['location_name'], $redemptionPublicId, $actionItemId, (string)$voucher['instance_id'], null, !empty($voucher['pppm_item_id']) ? (int)$voucher['pppm_item_id'] : null);
    $eventPayload = $metadata + ['redemption_id'=>$redemptionPublicId,'attempt_id'=>$attempt['public_id'],'customer_notification_id'=>$notify['customer_notification_id'],'merchant_notification_id'=>$notify['merchant_notification_id'],'merchant_alert_id'=>$notify['merchant_alert_id']];
    $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([mg_public_uuid(),(int)$voucher['instance_internal_id'],'action_center_manual_redeemed',(int)$claimCode['merchant_user_id'],'action_center_manual_code',$locationPublicId,json_encode($eventPayload, JSON_UNESCAPED_SLASHES)]);
    mg_audit('microgift.action_center_manual_redeemed', 'microgift_instance', ['instance_id'=>(string)$voucher['instance_id'],'location_id'=>$locationPublicId,'redemption_id'=>$redemptionPublicId], (int)$claimCode['merchant_user_id']);
    mg_event('microgift.action_center_manual_redeemed', ['instance_id'=>(string)$voucher['instance_id'],'location_id'=>$locationPublicId,'redemption_id'=>$redemptionPublicId], (int)$claimCode['merchant_user_id']);
    $pdo->commit();
    mg_ok([
        'action_item_id'=>$actionItemId,'instance_id'=>(string)$voucher['instance_id'],'attempt_id'=>$attempt['public_id'],'redemption_id'=>$redemptionPublicId,'location_id'=>$locationPublicId,'location_name'=>(string)$claimCode['location_name'],'location'=>['id'=>$locationPublicId,'name'=>(string)$claimCode['location_name'],'claim_code_last4'=>(string)($claimCode['code_last4'] ?? '')],'claim_code_last4'=>(string)($claimCode['code_last4'] ?? ''),'verified'=>true,'redeemed'=>true,'customer_notification_id'=>$notify['customer_notification_id'],'merchant_notification_id'=>$notify['merchant_notification_id'],'merchant_alert_id'=>$notify['merchant_alert_id'],'gift'=>['title'=>$title,'value_cents'=>(int)($voucher['face_value_cents'] ?? 0),'currency'=>(string)($voucher['currency'] ?? 'USD')],
    ], 'Gift claimed successfully.');
}

function mg_ac_voucher_claim_wallet(PDO $pdo, string $actionItemId, string $walletId, string $merchantCode, string $voucherToken, int $userId, string $userEmail): void
{
    $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, $userId, $userEmail, true);
    if (!$wallet) mg_ac_voucher_fail('Action Center wallet voucher not found.', 404);
    if (mg_ac_wallet_expired($wallet)) mg_ac_voucher_fail('This wallet reward has expired.', 410);
    $status = (string)($wallet['status'] ?? 'issued');
    if (mg_ac_voucher_completed_wallet_redemption($pdo, (int)$wallet['id'])) mg_ac_voucher_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    if (!in_array($status, ['issued','viewed','claimed','redeemed'], true)) mg_ac_voucher_fail('This wallet reward is not available for claim.', 409);
    mg_ac_voucher_assert_not_locked($pdo, $actionItemId, $userId);
    $claimCode = mg_ac_voucher_find_claim_code($pdo, $merchantCode, [(int)($wallet['merchant_user_id'] ?? 0)]);
    if (!$claimCode) mg_ac_voucher_record_failed_code($pdo, $actionItemId, $userId, (int)$wallet['id'], null);
    $redemptionPublicId = mg_public_uuid();
    $locationPublicId = (string)$claimCode['location_public_id'];
    $title = trim((string)($wallet['title_snapshot'] ?? '')) ?: trim((string)($wallet['reward_template_title'] ?? '')) ?: 'Microgifter reward';
    $cycle = mg_ac_voucher_wallet_redemption_cycle($pdo, (int)$wallet['id']);
    $idempotencyKey = 'wallet-account-manual:' . $walletId . ':' . $locationPublicId . ':' . $cycle;
    $correlationId = mg_public_uuid();
    $eventContext = ['action_item_id'=>$actionItemId,'wallet_item_id'=>$walletId,'redemption_id'=>$redemptionPublicId,'location_id'=>$locationPublicId,'location_name'=>(string)$claimCode['location_name'],'merchant_claim_code_id'=>(string)$claimCode['public_id'],'claim_code_last4'=>(string)($claimCode['code_last4'] ?? ''),'source'=>'action_center_manual_code','correlation_id'=>$correlationId];
    mg_ac_voucher_log_attempt($pdo, $actionItemId, $userId, true, null, (int)$claimCode['merchant_user_id'], (int)$wallet['id'], null, ['location_id'=>$locationPublicId]);
    $attempt = mg_ac_projection_record_attempt($pdo, (int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id'], (int)$claimCode['id'], $userId, null, $idempotencyKey, $correlationId);
    $pdo->prepare("INSERT INTO wallet_item_redemptions (public_id,wallet_item_id,user_id,merchant_user_id,location_id,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'completed',?,?,NOW(),?,NOW(),NOW())")->execute([$redemptionPublicId,(int)$wallet['id'],$userId,(int)$claimCode['merchant_user_id'],(int)$claimCode['location_internal_id'],$locationPublicId,(int)($wallet['value_cents_snapshot'] ?? 0),(string)($wallet['currency_snapshot'] ?? 'USD'),$idempotencyKey,'action_center_manual:' . $locationPublicId,json_encode($eventContext + ['attempt_id'=>$attempt['public_id']], JSON_UNESCAPED_SLASHES)]);
    $pdo->prepare("UPDATE wallet_items SET status='redeemed',claimed_at=COALESCE(claimed_at,NOW()),redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$wallet['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    mg_ac_voucher_mark_optional_wallet_token($pdo, $voucherToken, $walletId, $userId, (int)$claimCode['merchant_user_id'], (int)$claimCode['location_internal_id']);
    $notify = mg_ac_projection_notify($pdo, $userId, (int)$claimCode['merchant_user_id'], $userId, $title, (int)($wallet['value_cents_snapshot'] ?? 0), (string)($wallet['currency_snapshot'] ?? 'USD'), $locationPublicId, (string)$claimCode['location_name'], $redemptionPublicId, $actionItemId, null, $walletId, null);
    mg_ac_wallet_event($pdo, $wallet, 'wallet_item.redeemed', $eventContext + ['attempt_id'=>$attempt['public_id'],'customer_notification_id'=>$notify['customer_notification_id'],'merchant_notification_id'=>$notify['merchant_notification_id'],'merchant_alert_id'=>$notify['merchant_alert_id']]);
    mg_audit('wallet.action_center_manual_redeemed', 'wallet_item', ['wallet_item_id'=>$walletId,'location_id'=>$locationPublicId,'redemption_id'=>$redemptionPublicId], (int)$claimCode['merchant_user_id']);
    mg_event('wallet.action_center_manual_redeemed', ['wallet_item_id'=>$walletId,'location_id'=>$locationPublicId,'redemption_id'=>$redemptionPublicId], (int)$claimCode['merchant_user_id']);
    $pdo->commit();
    mg_ok([
        'action_item_id'=>$actionItemId,'instance_id'=>$walletId,'attempt_id'=>$attempt['public_id'],'redemption_id'=>$redemptionPublicId,'location_id'=>$locationPublicId,'location_name'=>(string)$claimCode['location_name'],'location'=>['id'=>$locationPublicId,'name'=>(string)$claimCode['location_name'],'claim_code_last4'=>(string)($claimCode['code_last4'] ?? '')],'claim_code_last4'=>(string)($claimCode['code_last4'] ?? ''),'verified'=>true,'redeemed'=>true,'is_wallet_reward'=>true,'customer_notification_id'=>$notify['customer_notification_id'],'merchant_notification_id'=>$notify['merchant_notification_id'],'merchant_alert_id'=>$notify['merchant_alert_id'],'gift'=>['title'=>$title,'value_cents'=>(int)($wallet['value_cents_snapshot'] ?? 0),'currency'=>(string)($wallet['currency_snapshot'] ?? 'USD')],
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
    if ($walletId !== null) mg_ac_voucher_claim_wallet($pdo, $actionItemId, $walletId, $merchantCode, $voucherToken, $userId, $userEmail);
    mg_ac_voucher_claim_microgift($pdo, $actionItemId, $merchantCode, $voucherToken, $userId);
} catch (MgAcVoucherClaimError $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning', 'action_center.voucher_manual_claim_rejected', $error->getMessage(), ['action_item_id'=>$actionItemId,'status'=>$error->status], $userId);
    mg_fail($error->getMessage(), $error->status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'action_center.voucher_manual_claim_failed', 'Unable to process manual merchant claim code.', ['action_item_id'=>$actionItemId,'exception_class'=>$error::class], $userId);
    mg_fail('Unable to claim this gift right now.', 500);
}
