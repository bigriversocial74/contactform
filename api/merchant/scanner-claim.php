<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once __DIR__ . '/_claims.php';
require_once dirname(__DIR__) . '/account/_claim_voucher_token.php';
require_once dirname(__DIR__) . '/account/_action_center_wallet.php';

function mg_scanner_claim_context(PDO $pdo, mixed $value): array
{
    $raw = trim((string)$value);
    if ($raw === '' || mb_strlen($raw) > 1400) mg_fail('Scan a Microgifter gift QR code or enter a gift identifier.', 422);
    $decoded = trim(rawurldecode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $tokenKeys = ['t','token','voucher_token'];
    $queryCandidates = [];

    $parts = @parse_url($decoded);
    if (is_array($parts)) {
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            foreach ($tokenKeys as $tokenKey) {
                if (isset($query[$tokenKey]) && !is_array($query[$tokenKey])) {
                    try {
                        $row = mg_claim_voucher_require_active($pdo, (string)$query[$tokenKey], true);
                        return ['identifier' => (string)$row['action_item_public_id'], 'voucher_token' => $row, 'wallet_id' => null];
                    } catch (Throwable) {
                        mg_fail('The scanned voucher QR is invalid or expired. Refresh the customer voucher and scan again.', 422);
                    }
                }
            }
            foreach (['gift','gift_id','id','item','action_item','action_item_id','voucher','voucher_id','instance','instance_id','wallet','wallet_id','g','claim','code'] as $key) {
                if (isset($query[$key]) && !is_array($query[$key])) $queryCandidates[] = trim((string)$query[$key]);
            }
        }
        if (!empty($parts['path'])) $queryCandidates[] = trim((string)$parts['path']);
    }

    if (str_starts_with(strtoupper($decoded), 'MGFT-CLAIM-TOKEN|')) {
        try {
            $row = mg_claim_voucher_require_active($pdo, substr($decoded, 17), true);
            return ['identifier' => (string)$row['action_item_public_id'], 'voucher_token' => $row, 'wallet_id' => null];
        } catch (Throwable) {
            mg_fail('The scanned voucher QR is invalid or expired. Refresh the customer voucher and scan again.', 422);
        }
    }
    if (str_starts_with(strtoupper($decoded), 'MGFT-WALLET-CLAIM|')) {
        $walletId = strtolower(trim(substr($decoded, 18)));
        if (preg_match('/^[a-f0-9-]{36}$/', $walletId) === 1) return ['identifier' => 'wallet-' . $walletId, 'voucher_token' => null, 'wallet_id' => $walletId];
        mg_fail('The scanned wallet reward QR is invalid.', 422);
    }
    if (str_starts_with(strtoupper($decoded), 'MGFT-CLAIM|')) $queryCandidates[] = substr($decoded, 11);
    if (preg_match('/^mgv1_[0-9a-f-]{36}_[a-f0-9]{32}$/i', $decoded) === 1) {
        try {
            $row = mg_claim_voucher_require_active($pdo, $decoded, true);
            return ['identifier' => (string)$row['action_item_public_id'], 'voucher_token' => $row, 'wallet_id' => null];
        } catch (Throwable) {
            mg_fail('The scanned voucher QR is invalid or expired. Refresh the customer voucher and scan again.', 422);
        }
    }

    $queryCandidates[] = $decoded;
    foreach ($queryCandidates as $candidate) {
        if ($candidate === '') continue;
        if (preg_match('/wallet-([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $candidate, $match)) return ['identifier' => 'wallet-' . strtolower($match[1]), 'voucher_token' => null, 'wallet_id' => strtolower($match[1])];
        if (preg_match('/GFT-[A-Z0-9-]{4,32}/i', $candidate, $match)) return ['identifier' => strtoupper($match[0]), 'voucher_token' => null, 'wallet_id' => null];
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $candidate, $match)) return ['identifier' => strtolower($match[0]), 'voucher_token' => null, 'wallet_id' => null];
    }
    if (preg_match('/^GFT-[A-Z0-9-]{4,32}$/', strtoupper($decoded))) return ['identifier' => strtoupper($decoded), 'voucher_token' => null, 'wallet_id' => null];
    mg_fail('This scan does not look like a Microgifter gift or claim QR code.', 422);
}

function mg_scanner_claim_notify(PDO $pdo, int $userId, int $actorUserId, string $type, string $title, string $body, string $actionUrl, ?int $giftDbId = null): void
{
    if ($userId < 1) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,gift_id,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([mg_public_uuid(), $userId, $type, $title, $body, $actionUrl, $giftDbId]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'scanner_claim.notification_failed', 'Scanner claim notification failed.', ['recipient_user_id' => $userId, 'type' => $type, 'exception_class' => $error::class], $actorUserId);
    }
}

function mg_scanner_claim_notify_many(PDO $pdo, array $userIds, int $actorUserId, string $type, string $title, string $body, string $actionUrl, ?int $giftDbId = null): void
{
    $sent = [];
    foreach ($userIds as $userId) {
        $id = (int)$userId;
        if ($id < 1 || isset($sent[$id])) continue;
        $sent[$id] = true;
        mg_scanner_claim_notify($pdo, $id, $actorUserId, $type, $title, $body, $actionUrl, $giftDbId);
    }
}

function mg_scanner_claim_assert_location_binding(array $claim, int $locationId): void
{
    $status = (string)($claim['status'] ?? '');
    $claimLocationId = (int)($claim['location_id'] ?? 0);
    if ($claimLocationId > 0 && $claimLocationId !== $locationId && in_array($status, ['verified','redeemed'], true)) mg_fail('This gift was already verified for another merchant location.', 409);
}

function mg_scanner_claim_legacy_lookup(PDO $pdo, int $merchantUserId, string $identifier): ?array
{
    $stmt = $pdo->prepare("SELECT g.id gift_db_id,g.public_id gift_id,g.status gift_status,g.expires_at,g.sender_user_id,g.recipient_user_id,g.title,g.value_cents,g.currency,
            gc.id claim_db_id,gc.public_id claim_id,gc.status claim_status,gc.failed_attempts,gc.locked_at,gc.verified_at,gc.redeemed_at,gc.expires_at claim_expires_at,gc.location_id,gc.merchant_claim_code_id,
            m.pppm_item_id,pi.public_id pppm_id,pi.status pppm_status
        FROM gifts g
        LEFT JOIN gift_claims gc ON gc.gift_id=g.id
        LEFT JOIN pppm_legacy_gift_map m ON m.gift_id=g.id
        LEFT JOIN pppm_items pi ON pi.id=m.pppm_item_id
        WHERE (g.public_id=? OR pi.public_id=? OR gc.public_id=?)
          AND EXISTS (SELECT 1 FROM gift_merchant_eligibility e WHERE e.gift_id=g.id AND e.merchant_user_id=?)
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$identifier, $identifier, $identifier, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_scanner_claim_microgift_lookup(PDO $pdo, int $merchantUserId, string $identifier): ?array
{
    $stmt = $pdo->prepare("SELECT mi.*, mt.name template_name, mt.owner_user_id template_owner_user_id, ac.public_id action_item_id
        FROM microgift_instances mi
        INNER JOIN microgift_templates mt ON mt.id=mi.template_id
        LEFT JOIN microgift_inbox_items ac ON ac.instance_id=mi.id AND ac.public_id=?
        WHERE (mi.public_id=? OR ac.public_id=?)
          AND (mt.owner_user_id=? OR mi.issuer_user_id=?)
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$identifier, $identifier, $identifier, $merchantUserId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_scanner_claim_wallet_lookup(PDO $pdo, int $merchantUserId, string $walletId): ?array
{
    $sql = mg_ac_wallet_select_sql() . ' WHERE wi.public_id=? AND wi.merchant_user_id=? AND wi.status<>\'cancelled\' LIMIT 1 FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$walletId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_scanner_claim_claim_code(PDO $pdo, int $merchantUserId, int $locationId): array
{
    $stmt = $pdo->prepare("SELECT * FROM merchant_claim_codes WHERE merchant_user_id=? AND location_id=? AND status='active' AND (valid_from IS NULL OR valid_from<=NOW()) AND (valid_until IS NULL OR valid_until>=NOW()) AND (usage_limit IS NULL OR usage_count<usage_limit) ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId, $locationId]);
    $claimCode = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$claimCode) mg_fail('This scanner location does not have an active claim code assigned.', 409);
    return $claimCode;
}

function mg_scanner_claim_process_wallet(PDO $pdo, array $wallet, array $location, array $claimCode, int $merchantUserId, string $locationPublicId, string $action, bool $requireConfirm, bool $confirmed): void
{
    $walletPublicId = (string)$wallet['public_id'];
    if (mg_ac_wallet_expired($wallet)) {
        mg_ac_wallet_mark_expired($pdo, $wallet, 'wallet-' . $walletPublicId);
        $pdo->commit();
        mg_fail('This wallet reward has expired.', 410);
    }
    $status = (string)($wallet['status'] ?? 'issued');
    if ($status === 'redeemed') {
        $pdo->commit();
        mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    }
    if (!in_array($status, ['issued','viewed','claimed'], true)) mg_fail('This wallet reward is not available for scanner redemption.', 409);

    $title = trim((string)($wallet['title_snapshot'] ?? '')) ?: trim((string)($wallet['reward_template_title'] ?? '')) ?: 'Microgifter reward';
    if ($action === 'verify' || ($action === 'redeem' && $requireConfirm && !$confirmed)) {
        mg_ac_wallet_event($pdo, $wallet, 'wallet_item.scanner_verified', ['location_id' => $locationPublicId, 'claim_code_last4' => (string)($claimCode['code_last4'] ?? '')]);
        $pdo->commit();
        mg_audit('wallet.scanner_claim_verified', 'wallet_item', ['wallet_item_id' => $walletPublicId, 'location_id' => $locationPublicId], $merchantUserId);
        mg_ok(['gift_id' => 'wallet-' . $walletPublicId, 'instance_id' => $walletPublicId, 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => false, 'needs_confirmation' => $action === 'redeem' && $requireConfirm && !$confirmed, 'is_wallet_reward' => true, 'gift' => ['title' => $title, 'value_cents' => (int)($wallet['value_cents_snapshot'] ?? 0), 'currency' => (string)($wallet['currency_snapshot'] ?? 'USD')]], $action === 'verify' ? 'Wallet reward verified for this scanner location.' : 'Wallet reward verified. Confirm redemption before claiming voucher.');
    }

    $eventContext = ['location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'merchant_claim_code_id' => (string)$claimCode['public_id'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'source' => 'merchant_scanner'];
    $pdo->prepare("UPDATE wallet_items SET status='redeemed',claimed_at=COALESCE(claimed_at,NOW()),redeemed_at=NOW(),updated_at=NOW() WHERE id=? AND status<>'redeemed'")->execute([(int)$wallet['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    mg_ac_wallet_event($pdo, $wallet, 'wallet_item.redeemed', $eventContext);
    $pdo->commit();
    mg_audit('wallet.scanner_claim_redeemed', 'wallet_item', ['wallet_item_id' => $walletPublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_event('wallet.scanner_claim_redeemed', ['wallet_item_id' => $walletPublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_ok(['gift_id' => 'wallet-' . $walletPublicId, 'instance_id' => $walletPublicId, 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => true, 'notifications' => true, 'is_wallet_reward' => true, 'gift' => ['title' => $title, 'value_cents' => (int)($wallet['value_cents_snapshot'] ?? 0), 'currency' => (string)($wallet['currency_snapshot'] ?? 'USD')]], 'Wallet reward redeemed.');
}

function mg_scanner_claim_process_microgift(PDO $pdo, array $instance, array $location, array $claimCode, int $merchantUserId, string $locationPublicId, string $action, bool $requireConfirm, bool $confirmed, ?array $voucherToken): void
{
    $instancePublicId = (string)$instance['public_id'];
    $claimantUserId = (int)($instance['owner_user_id'] ?: $instance['recipient_user_id'] ?: 0);
    if ($claimantUserId < 1) mg_fail('This Microgift is not assigned to a customer account yet.', 409);
    if (in_array((string)$instance['status'], ['cancelled','revoked','expired'], true)) mg_fail('This Microgift is not available for scanner redemption.', 409);
    if (!empty($instance['expires_at']) && strtotime((string)$instance['expires_at']) < time()) {
        $pdo->prepare("UPDATE microgift_instances SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$instance['id']]);
        mg_fail('This Microgift has expired.', 410);
    }

    $redemptionStmt = $pdo->prepare("SELECT * FROM microgift_redemptions WHERE instance_id=? AND status='completed' LIMIT 1 FOR UPDATE");
    $redemptionStmt->execute([(int)$instance['id']]);
    if ($redemptionStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->commit();
        mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    }

    if ($action === 'verify' || ($action === 'redeem' && $requireConfirm && !$confirmed)) {
        $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([mg_public_uuid(), (int)$instance['id'], 'scanner_verified', $merchantUserId, 'merchant_scanner', $locationPublicId, json_encode(['location_id' => $locationPublicId, 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'voucher_token_id' => $voucherToken['public_id'] ?? null], JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
        mg_audit('microgift.scanner_claim_verified', 'microgift_instance', ['instance_id' => $instancePublicId, 'location_id' => $locationPublicId], $merchantUserId);
        mg_ok(['gift_id' => $instancePublicId, 'instance_id' => $instancePublicId, 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => false, 'needs_confirmation' => $action === 'redeem' && $requireConfirm && !$confirmed, 'gift' => ['title' => (string)($instance['title_snapshot'] ?: $instance['template_name'] ?: 'Microgift'), 'value_cents' => (int)($instance['face_value_cents'] ?? 0), 'currency' => (string)($instance['currency'] ?? 'USD')]], $action === 'verify' ? 'Microgift verified for this scanner location.' : 'Microgift verified. Confirm redemption before claiming voucher.');
    }

    $redemptionPublicId = mg_public_uuid();
    $idempotencyKey = 'scanner:' . $instancePublicId . ':' . $locationPublicId . ':' . $redemptionPublicId;
    $metadata = ['location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'merchant_claim_code_id' => (string)$claimCode['public_id'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'source' => 'merchant_scanner', 'voucher_token_id' => $voucherToken['public_id'] ?? null];
    $pdo->prepare("INSERT INTO microgift_redemptions (public_id,instance_id,claimant_user_id,merchant_user_id,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,'completed',?,?,NOW(),?,NOW())")->execute([$redemptionPublicId, (int)$instance['id'], $claimantUserId, $merchantUserId, $locationPublicId, (int)($instance['face_value_cents'] ?? 0), (string)($instance['currency'] ?? 'USD'), $idempotencyKey, 'merchant_scanner:' . $locationPublicId, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
    $redemptionId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE microgift_instances SET status='redeemed',redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$instance['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    $pdo->prepare("UPDATE microgift_inbox_items SET folder='claimed',state='redeemed',merchant_user_id=?,location_id=?,redemption_id=?,redeemed_at=NOW(),updated_at=NOW() WHERE instance_id=? AND user_id=? AND archived_at IS NULL")->execute([$merchantUserId, (int)$location['id'], $redemptionId, (int)$instance['id'], $claimantUserId]);
    if ($voucherToken) mg_claim_voucher_mark_redeemed($pdo, (int)$voucherToken['id'], $merchantUserId, (int)$location['id']);
    $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([mg_public_uuid(), (int)$instance['id'], 'scanner_redeemed', $merchantUserId, 'merchant_scanner', $locationPublicId, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);

    $title = (string)($instance['title_snapshot'] ?: $instance['template_name'] ?: 'Microgift');
    mg_scanner_claim_notify_many($pdo, [$claimantUserId, (int)($instance['issuer_user_id'] ?? 0), $merchantUserId], $merchantUserId, 'microgift_redeemed', 'Microgift redeemed', $title . ' was redeemed at ' . (string)$location['name'] . '.', '/claimed.php?gift=' . rawurlencode($instancePublicId), null);
    $pdo->commit();
    mg_audit('microgift.scanner_claim_redeemed', 'microgift_instance', ['instance_id' => $instancePublicId, 'location_id' => $locationPublicId, 'redemption_id' => $redemptionPublicId], $merchantUserId);
    mg_event('microgift.scanner_claim_redeemed', ['instance_id' => $instancePublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_ok(['gift_id' => $instancePublicId, 'instance_id' => $instancePublicId, 'redemption_id' => $redemptionPublicId, 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => true, 'notifications' => true, 'gift' => ['title' => $title, 'value_cents' => (int)($instance['face_value_cents'] ?? 0), 'currency' => (string)($instance['currency'] ?? 'USD')]], 'Microgift redeemed and notifications queued.');
}

mg_require_method('POST');
$user = mg_require_permission('merchant.gifts.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'redeem')));
if (!in_array($action, ['verify','redeem'], true)) mg_fail('Invalid scanner action.', 422);
$scanInput = $input['scan'] ?? $input['gift_id'] ?? '';
$locationPublicId = mg_claim_code_public_id((string)($input['location_id'] ?? ''), 'Choose a merchant location for this scanner.');
$requireConfirm = !empty($input['require_confirmation']);
$confirmed = !empty($input['confirmed']);
$pdo = mg_db();
$workspace = mg_claim_workspace($pdo, $user);
$merchantUserId = (int)$user['id'];
$identifier = '';

try {
    $pdo->beginTransaction();
    $context = mg_scanner_claim_context($pdo, $scanInput);
    $identifier = (string)$context['identifier'];
    $voucherToken = is_array($context['voucher_token'] ?? null) ? $context['voucher_token'] : null;
    $walletId = is_string($context['wallet_id'] ?? null) ? (string)$context['wallet_id'] : null;

    $locationStmt = $pdo->prepare("SELECT ml.* FROM merchant_locations ml WHERE ml.public_id=? AND ml.workspace_id=? AND ml.merchant_user_id=? AND ml.status='active' LIMIT 1 FOR UPDATE");
    $locationStmt->execute([$locationPublicId, (int)$workspace['id'], $merchantUserId]);
    $location = $locationStmt->fetch(PDO::FETCH_ASSOC);
    if (!$location) mg_fail('Merchant location not found or inactive.', 404);
    $claimCode = mg_scanner_claim_claim_code($pdo, $merchantUserId, (int)$location['id']);
    if ($voucherToken) mg_claim_voucher_mark_scanned($pdo, (int)$voucherToken['id'], $merchantUserId, (int)$location['id']);

    if ($walletId !== null) {
        $wallet = mg_scanner_claim_wallet_lookup($pdo, $merchantUserId, $walletId);
        if ($wallet) mg_scanner_claim_process_wallet($pdo, $wallet, $location, $claimCode, $merchantUserId, $locationPublicId, $action, $requireConfirm, $confirmed);
        mg_fail('Eligible wallet reward not found.', 404);
    }

    $lookup = mg_scanner_claim_legacy_lookup($pdo, $merchantUserId, $identifier);
    if (!$lookup) {
        $microgift = mg_scanner_claim_microgift_lookup($pdo, $merchantUserId, $identifier);
        if ($microgift) mg_scanner_claim_process_microgift($pdo, $microgift, $location, $claimCode, $merchantUserId, $locationPublicId, $action, $requireConfirm, $confirmed, $voucherToken);
        mg_fail('Eligible gift, Microgift, or PPPM item not found.', 404);
    }

    $giftPublicId = (string)($lookup['gift_id'] ?? $identifier);
    $giftStmt = $pdo->prepare("SELECT * FROM gifts WHERE public_id=? AND status IN ('sent','delivered','claimed') LIMIT 1 FOR UPDATE");
    $giftStmt->execute([$giftPublicId]);
    $gift = $giftStmt->fetch(PDO::FETCH_ASSOC);
    if (!$gift) mg_fail('Gift is not available for scanner redemption.', 404);

    $eligibilityStmt = $pdo->prepare('SELECT 1 FROM gift_merchant_eligibility WHERE gift_id=? AND merchant_user_id=? AND (location_id IS NULL OR location_id=?) LIMIT 1');
    $eligibilityStmt->execute([(int)$gift['id'], $merchantUserId, (int)$location['id']]);
    if (!$eligibilityStmt->fetchColumn()) mg_fail('This location is not authorized for the gift.', 403);

    $claimStmt = $pdo->prepare('SELECT * FROM gift_claims WHERE gift_id=? LIMIT 1 FOR UPDATE');
    $claimStmt->execute([(int)$gift['id']]);
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
    if (!$claim) {
        $pdo->prepare("INSERT INTO gift_claims (public_id,gift_id,location_id,status,failed_attempts,expires_at,created_at,updated_at) VALUES (?,?,?,'pending',0,?,NOW(),NOW())")->execute([mg_public_uuid(), (int)$gift['id'], (int)$location['id'], $gift['expires_at'] ?? null]);
        $claimStmt->execute([(int)$gift['id']]);
        $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$claim) mg_fail('Unable to initialize this gift claim.', 500);
    mg_scanner_claim_assert_location_binding($claim, (int)$location['id']);

    if ((string)$claim['status'] === 'redeemed' || (string)$gift['status'] === 'claimed') {
        $pdo->commit();
        mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
    }
    if (in_array((string)$claim['status'], ['cancelled','expired','locked'], true)) mg_fail('This claim is not available.', 409);
    if (!empty($claim['expires_at']) && strtotime((string)$claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE gift_claims SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$claim['id']]);
        $pdo->commit();
        mg_fail('This claim has expired.', 410);
    }

    $pepper = mg_claim_code_pepper();
    $pdo->prepare('INSERT INTO gift_claim_attempts (claim_id,actor_user_id,successful,ip_hash,user_agent_hash,created_at) VALUES (?,?,?,?,?,NOW())')->execute([(int)$claim['id'], $merchantUserId, 1, hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper), hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper)]);
    $pdo->prepare("UPDATE gift_claims SET location_id=?,merchant_claim_code_id=?,verified_by_user_id=?,status='verified',verified_at=COALESCE(verified_at,NOW()),failed_attempts=0,locked_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$location['id'], (int)$claimCode['id'], $merchantUserId, (int)$claim['id']]);

    if ($action === 'verify' || ($action === 'redeem' && $requireConfirm && !$confirmed)) {
        $pdo->commit();
        mg_audit('gift.scanner_claim_verified', 'gift', ['gift_id' => $giftPublicId, 'location_id' => $locationPublicId], $merchantUserId);
        mg_ok(['gift_id' => $giftPublicId, 'claim_id' => (string)$claim['public_id'], 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => false, 'needs_confirmation' => $action === 'redeem' && $requireConfirm && !$confirmed, 'gift' => ['title' => (string)($gift['title'] ?? 'Microgift'), 'value_cents' => (int)($gift['value_cents'] ?? 0), 'currency' => (string)($gift['currency'] ?? 'USD')]], $action === 'verify' ? 'Gift verified for this scanner location.' : 'Gift verified. Confirm redemption before claiming voucher.');
    }

    $pdo->prepare("UPDATE gift_claims SET status='redeemed',redeemed_by_user_id=?,redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$merchantUserId, (int)$claim['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    $pdo->prepare("UPDATE gifts SET status='claimed',claimed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$gift['id']]);
    if ($voucherToken) mg_claim_voucher_mark_redeemed($pdo, (int)$voucherToken['id'], $merchantUserId, (int)$location['id']);
    mg_gift_event($pdo, (int)$gift['id'], $merchantUserId, 'claimed', ['claim_id' => (string)$claim['public_id'], 'location_id' => $locationPublicId, 'source' => 'scanner', 'voucher_token_id' => $voucherToken['public_id'] ?? null]);
    mg_scanner_claim_notify_many($pdo, [(int)($gift['sender_user_id'] ?? 0), (int)($gift['recipient_user_id'] ?? 0), $merchantUserId], $merchantUserId, 'gift_claimed', 'Gift redeemed', 'A merchant location successfully redeemed your gift.', '/claimed.php?gift=' . rawurlencode($giftPublicId), (int)$gift['id']);

    $pdo->commit();
    mg_audit('gift.scanner_claim_redeemed', 'gift', ['gift_id' => $giftPublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_event('gift.scanner_claim_redeemed', ['gift_id' => $giftPublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_ok(['gift_id' => $giftPublicId, 'claim_id' => (string)$claim['public_id'], 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => true, 'notifications' => true, 'gift' => ['title' => (string)($gift['title'] ?? 'Microgift'), 'value_cents' => (int)($gift['value_cents'] ?? 0), 'currency' => (string)($gift['currency'] ?? 'USD')]], 'Gift redeemed and notifications queued.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.scanner_claim_failed', 'Scanner claim failed.', ['identifier' => $identifier, 'location_id' => $locationPublicId, 'exception_class' => $error::class], $merchantUserId);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to process scanner claim right now.', 500);
}