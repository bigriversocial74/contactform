<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once __DIR__ . '/_claims.php';
require_once __DIR__ . '/_scanner_trust.php';
require_once dirname(__DIR__) . '/account/_claim_voucher_token.php';

function mg_scanner_trust_scan_context(PDO $pdo, mixed $value): array
{
    $raw = trim((string)$value);
    if ($raw === '' || mb_strlen($raw) > 1400) mg_fail('Scan a Microgifter voucher QR code or enter a gift identifier.', 422);
    $decoded = trim(rawurldecode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $candidates = [];
    $parts = @parse_url($decoded);
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        foreach (['t','token','voucher_token'] as $key) {
            if (isset($query[$key]) && !is_array($query[$key])) {
                try {
                    $token = mg_claim_voucher_require_active($pdo, (string)$query[$key], true);
                    return ['identifier' => (string)$token['action_item_public_id'], 'voucher_token' => $token, 'raw_scan' => $raw];
                } catch (Throwable) {
                    mg_fail('The scanned voucher QR is invalid or expired. Refresh the customer voucher and scan again.', 422);
                }
            }
        }
        foreach (['gift','gift_id','id','item','action_item','action_item_id','voucher','voucher_id','instance','instance_id','g','claim','code'] as $key) {
            if (isset($query[$key]) && !is_array($query[$key])) $candidates[] = trim((string)$query[$key]);
        }
    }
    if (str_starts_with(strtoupper($decoded), 'MGFT-CLAIM-TOKEN|')) {
        try {
            $token = mg_claim_voucher_require_active($pdo, substr($decoded, 17), true);
            return ['identifier' => (string)$token['action_item_public_id'], 'voucher_token' => $token, 'raw_scan' => $raw];
        } catch (Throwable) {
            mg_fail('The scanned voucher QR is invalid or expired. Refresh the customer voucher and scan again.', 422);
        }
    }
    if (preg_match('/^mgv1_[0-9a-f-]{36}_[a-f0-9]{32}$/i', $decoded) === 1) {
        try {
            $token = mg_claim_voucher_require_active($pdo, $decoded, true);
            return ['identifier' => (string)$token['action_item_public_id'], 'voucher_token' => $token, 'raw_scan' => $raw];
        } catch (Throwable) {
            mg_fail('The scanned voucher QR is invalid or expired. Refresh the customer voucher and scan again.', 422);
        }
    }
    if (str_starts_with(strtoupper($decoded), 'MGFT-CLAIM|')) $candidates[] = substr($decoded, 11);
    $candidates[] = $decoded;
    foreach ($candidates as $candidate) {
        if (preg_match('/GFT-[A-Z0-9-]{4,32}/i', $candidate, $match)) return ['identifier' => strtoupper($match[0]), 'voucher_token' => null, 'raw_scan' => $raw];
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $candidate, $match)) return ['identifier' => strtolower($match[0]), 'voucher_token' => null, 'raw_scan' => $raw];
    }
    mg_fail('This scan does not look like a Microgifter gift or claim QR code.', 422);
}

function mg_scanner_trust_claim_code(PDO $pdo, int $merchantUserId, int $locationId): array
{
    $stmt = $pdo->prepare("SELECT * FROM merchant_claim_codes WHERE merchant_user_id=? AND location_id=? AND status='active' AND (valid_from IS NULL OR valid_from<=NOW()) AND (valid_until IS NULL OR valid_until>=NOW()) AND (usage_limit IS NULL OR usage_count<usage_limit) ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId, $locationId]);
    $code = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$code) mg_fail('This scanner location does not have an active claim code assigned.', 409);
    return $code;
}

function mg_scanner_trust_microgift(PDO $pdo, int $merchantUserId, string $identifier): ?array
{
    $stmt = $pdo->prepare("SELECT mi.*, mt.name template_name, mt.owner_user_id template_owner_user_id, ac.public_id action_item_id
        FROM microgift_instances mi
        INNER JOIN microgift_templates mt ON mt.id=mi.template_id
        LEFT JOIN microgift_inbox_items ac ON ac.instance_id=mi.id AND ac.public_id=?
        WHERE (mi.public_id=? OR ac.public_id=?) AND (mt.owner_user_id=? OR mi.issuer_user_id=?)
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$identifier, $identifier, $identifier, $merchantUserId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_scanner_trust_legacy(PDO $pdo, int $merchantUserId, string $identifier): ?array
{
    $stmt = $pdo->prepare("SELECT g.*, gc.public_id claim_public_id, gc.status claim_status, gc.location_id claim_location_id
        FROM gifts g
        LEFT JOIN gift_claims gc ON gc.gift_id=g.id
        WHERE (g.public_id=? OR gc.public_id=?)
          AND EXISTS (SELECT 1 FROM gift_merchant_eligibility e WHERE e.gift_id=g.id AND e.merchant_user_id=?)
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$identifier, $identifier, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

mg_require_method('POST');
$user = mg_require_permission('merchant.gifts.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'redeem')));
if (!in_array($action, ['verify','redeem'], true)) mg_fail('Invalid scanner action.', 422);
$requireConfirm = !empty($input['require_confirmation']);
$confirmed = !empty($input['confirmed']);
$pdo = mg_db();
$merchantUserId = (int)$user['id'];
$workspace = mg_claim_workspace($pdo, $user);
$locationPublicId = mg_claim_code_public_id((string)($input['location_id'] ?? ''), 'Choose a merchant location for this scanner.');
$identifier = '';
$location = null;
$voucherToken = null;
$rawScan = (string)($input['scan'] ?? $input['gift_id'] ?? '');

try {
    $pdo->beginTransaction();
    $context = mg_scanner_trust_scan_context($pdo, $rawScan);
    $identifier = (string)$context['identifier'];
    $voucherToken = is_array($context['voucher_token'] ?? null) ? $context['voucher_token'] : null;
    $rawScan = (string)($context['raw_scan'] ?? $rawScan);

    $locationStmt = $pdo->prepare("SELECT * FROM merchant_locations WHERE public_id=? AND workspace_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
    $locationStmt->execute([$locationPublicId, (int)$workspace['id'], $merchantUserId]);
    $location = $locationStmt->fetch(PDO::FETCH_ASSOC);
    if (!$location) mg_fail('Merchant location not found or inactive.', 404);
    $claimCode = mg_scanner_trust_claim_code($pdo, $merchantUserId, (int)$location['id']);
    if ($voucherToken) mg_claim_voucher_mark_scanned($pdo, (int)$voucherToken['id'], $merchantUserId, (int)$location['id']);

    $microgift = mg_scanner_trust_microgift($pdo, $merchantUserId, $identifier);
    if ($microgift) {
        $giftId = (string)$microgift['public_id'];
        $claimantId = (int)($microgift['owner_user_id'] ?: $microgift['recipient_user_id'] ?: 0);
        $issuerId = (int)($microgift['issuer_user_id'] ?? 0);
        $title = (string)($microgift['title_snapshot'] ?: $microgift['template_name'] ?: 'Microgift');
        $amount = (int)($microgift['face_value_cents'] ?? 0);
        $currency = (string)($microgift['currency'] ?? 'USD');
        if ($claimantId < 1) mg_fail('This Microgift is not assigned to a customer account yet.', 409);
        if (in_array((string)$microgift['status'], ['cancelled','revoked','expired'], true)) mg_fail('This Microgift is not available for scanner redemption.', 409);
        if ((string)$microgift['status'] === 'redeemed') {
            mg_scanner_trust_event($pdo, 'already_redeemed_scan', 45, $giftId, $merchantUserId, $location, $voucherToken, null, $rawScan, ['type' => 'microgift']);
            $pdo->commit();
            mg_ok(['gift_id' => $giftId, 'verified' => true, 'redeemed' => true, 'already_redeemed' => true], 'Microgift already redeemed.');
        }
        $confirmation = mg_scanner_trust_confirmation($giftId, $title, $amount, $currency, $location, $claimCode, mg_scanner_trust_user_summary($pdo, $claimantId));
        if ($action === 'verify' || ($action === 'redeem' && $requireConfirm && !$confirmed)) {
            mg_scanner_trust_event($pdo, 'scanner_verified', 5, $giftId, $merchantUserId, $location, $voucherToken, null, $rawScan, ['type' => 'microgift']);
            $pdo->commit();
            mg_ok(['gift_id' => $giftId, 'instance_id' => $giftId, 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => false, 'needs_confirmation' => $action === 'redeem' && $requireConfirm && !$confirmed, 'confirmation' => $confirmation, 'gift' => ['title' => $title, 'value_cents' => $amount, 'currency' => $currency]], 'Microgift verified. Confirm redemption before claiming voucher.');
        }
        $redemptionPublicId = mg_public_uuid();
        $pdo->prepare("INSERT INTO microgift_redemptions (public_id,instance_id,claimant_user_id,merchant_user_id,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,'completed',?,?,NOW(),?,NOW())")->execute([$redemptionPublicId, (int)$microgift['id'], $claimantId, $merchantUserId, $locationPublicId, $amount, $currency, 'scanner:' . $giftId . ':' . $locationPublicId, 'merchant_scanner:' . $locationPublicId, json_encode(['location_id' => $locationPublicId, 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'voucher_token_id' => $voucherToken['public_id'] ?? null], JSON_UNESCAPED_SLASHES)]);
        $redemptionId = (int)$pdo->lastInsertId();
        $receipt = mg_scanner_trust_receipt($pdo, 'microgift', $giftId, $redemptionPublicId, null, $claimantId, $issuerId, $merchantUserId, $location, $claimCode, $amount, $currency, ['confirmation' => $confirmation, 'redemption_id' => $redemptionPublicId]);
        $pdo->prepare("UPDATE microgift_instances SET status='redeemed',redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$microgift['id']]);
        $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
        $pdo->prepare("UPDATE microgift_inbox_items SET folder='claimed',state='redeemed',merchant_user_id=?,location_id=?,redemption_id=?,redeemed_at=NOW(),updated_at=NOW() WHERE instance_id=? AND user_id=? AND archived_at IS NULL")->execute([$merchantUserId, (int)$location['id'], $redemptionId, (int)$microgift['id'], $claimantId]);
        if ($voucherToken) mg_claim_voucher_mark_redeemed($pdo, (int)$voucherToken['id'], $merchantUserId, (int)$location['id']);
        $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([mg_public_uuid(), (int)$microgift['id'], 'scanner_redeemed', $merchantUserId, 'merchant_scanner', $locationPublicId, json_encode(['receipt_id' => $receipt['id'], 'location_id' => $locationPublicId], JSON_UNESCAPED_SLASHES)]);
        mg_scanner_claim_notify_many($pdo, [$claimantId, $issuerId, $merchantUserId], $merchantUserId, 'microgift_redeemed', 'Microgift redeemed', $title . ' was redeemed at ' . (string)$location['name'] . '.', $receipt['url'], null);
        mg_scanner_trust_event($pdo, 'scanner_redeemed', 10, $giftId, $merchantUserId, $location, $voucherToken, $receipt['id'], $rawScan, ['type' => 'microgift']);
        $pdo->commit();
        mg_ok(['gift_id' => $giftId, 'redemption_id' => $redemptionPublicId, 'receipt_id' => $receipt['id'], 'receipt_url' => $receipt['url'], 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'verified' => true, 'redeemed' => true, 'notifications' => true, 'confirmation' => $confirmation, 'gift' => ['title' => $title, 'value_cents' => $amount, 'currency' => $currency]], 'Microgift redeemed and receipt created.');
    }

    $legacy = mg_scanner_trust_legacy($pdo, $merchantUserId, $identifier);
    if (!$legacy) mg_fail('Eligible gift, Microgift, or PPPM item not found.', 404);
    $giftId = (string)$legacy['public_id'];
    if (!in_array((string)$legacy['status'], ['sent','delivered','claimed'], true)) mg_fail('Gift is not available for scanner redemption.', 404);
    $eligibilityStmt = $pdo->prepare('SELECT 1 FROM gift_merchant_eligibility WHERE gift_id=? AND merchant_user_id=? AND (location_id IS NULL OR location_id=?) LIMIT 1');
    $eligibilityStmt->execute([(int)$legacy['id'], $merchantUserId, (int)$location['id']]);
    if (!$eligibilityStmt->fetchColumn()) mg_fail('This location is not authorized for the gift.', 403);
    $claimStmt = $pdo->prepare('SELECT * FROM gift_claims WHERE gift_id=? LIMIT 1 FOR UPDATE');
    $claimStmt->execute([(int)$legacy['id']]);
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
    if (!$claim) {
        $pdo->prepare("INSERT INTO gift_claims (public_id,gift_id,location_id,status,failed_attempts,expires_at,created_at,updated_at) VALUES (?,?,?,'pending',0,?,NOW(),NOW())")->execute([mg_public_uuid(), (int)$legacy['id'], (int)$location['id'], $legacy['expires_at'] ?? null]);
        $claimStmt->execute([(int)$legacy['id']]);
        $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$claim) mg_fail('Unable to initialize this gift claim.', 500);
    if ((int)($claim['location_id'] ?? 0) > 0 && (int)$claim['location_id'] !== (int)$location['id'] && in_array((string)$claim['status'], ['verified','redeemed'], true)) mg_fail('This gift was already verified for another merchant location.', 409);
    $title = (string)($legacy['title'] ?? 'Microgift');
    $amount = (int)($legacy['value_cents'] ?? 0);
    $currency = (string)($legacy['currency'] ?? 'USD');
    $confirmation = mg_scanner_trust_confirmation($giftId, $title, $amount, $currency, $location, $claimCode, mg_scanner_trust_user_summary($pdo, (int)($legacy['recipient_user_id'] ?? 0)));
    if ((string)$claim['status'] === 'redeemed' || (string)$legacy['status'] === 'claimed') {
        mg_scanner_trust_event($pdo, 'already_redeemed_scan', 45, $giftId, $merchantUserId, $location, $voucherToken, null, $rawScan, ['type' => 'legacy']);
        $pdo->commit();
        mg_ok(['gift_id' => $giftId, 'claim_id' => (string)$claim['public_id'], 'verified' => true, 'redeemed' => true, 'already_redeemed' => true], 'Gift already redeemed.');
    }
    $pepper = mg_claim_code_pepper();
    $pdo->prepare('INSERT INTO gift_claim_attempts (claim_id,actor_user_id,successful,ip_hash,user_agent_hash,created_at) VALUES (?,?,?,?,?,NOW())')->execute([(int)$claim['id'], $merchantUserId, 1, hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper), hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper)]);
    $pdo->prepare("UPDATE gift_claims SET location_id=?,merchant_claim_code_id=?,verified_by_user_id=?,status='verified',verified_at=COALESCE(verified_at,NOW()),failed_attempts=0,locked_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$location['id'], (int)$claimCode['id'], $merchantUserId, (int)$claim['id']]);
    if ($action === 'verify' || ($action === 'redeem' && $requireConfirm && !$confirmed)) {
        mg_scanner_trust_event($pdo, 'scanner_verified', 5, $giftId, $merchantUserId, $location, $voucherToken, null, $rawScan, ['type' => 'legacy']);
        $pdo->commit();
        mg_ok(['gift_id' => $giftId, 'claim_id' => (string)$claim['public_id'], 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''), 'verified' => true, 'redeemed' => false, 'needs_confirmation' => $action === 'redeem' && $requireConfirm && !$confirmed, 'confirmation' => $confirmation, 'gift' => ['title' => $title, 'value_cents' => $amount, 'currency' => $currency]], 'Gift verified. Confirm redemption before claiming voucher.');
    }
    $receipt = mg_scanner_trust_receipt($pdo, 'legacy_gift', $giftId, null, (string)$claim['public_id'], (int)($legacy['recipient_user_id'] ?? 0), (int)($legacy['sender_user_id'] ?? 0), $merchantUserId, $location, $claimCode, $amount, $currency, ['confirmation' => $confirmation, 'claim_id' => (string)$claim['public_id']]);
    $pdo->prepare("UPDATE gift_claims SET status='redeemed',redeemed_by_user_id=?,redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$merchantUserId, (int)$claim['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$claimCode['id']]);
    $pdo->prepare("UPDATE gifts SET status='claimed',claimed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$legacy['id']]);
    if ($voucherToken) mg_claim_voucher_mark_redeemed($pdo, (int)$voucherToken['id'], $merchantUserId, (int)$location['id']);
    mg_gift_event($pdo, (int)$legacy['id'], $merchantUserId, 'claimed', ['claim_id' => (string)$claim['public_id'], 'location_id' => $locationPublicId, 'source' => 'scanner', 'receipt_id' => $receipt['id']]);
    mg_scanner_claim_notify_many($pdo, [(int)($legacy['sender_user_id'] ?? 0), (int)($legacy['recipient_user_id'] ?? 0), $merchantUserId], $merchantUserId, 'gift_claimed', 'Gift redeemed', 'A merchant location successfully redeemed your gift.', $receipt['url'], (int)$legacy['id']);
    mg_scanner_trust_event($pdo, 'scanner_redeemed', 10, $giftId, $merchantUserId, $location, $voucherToken, $receipt['id'], $rawScan, ['type' => 'legacy']);
    $pdo->commit();
    mg_ok(['gift_id' => $giftId, 'claim_id' => (string)$claim['public_id'], 'receipt_id' => $receipt['id'], 'receipt_url' => $receipt['url'], 'location_id' => $locationPublicId, 'location_name' => (string)$location['name'], 'verified' => true, 'redeemed' => true, 'notifications' => true, 'confirmation' => $confirmation, 'gift' => ['title' => $title, 'value_cents' => $amount, 'currency' => $currency]], 'Gift redeemed and receipt created.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    try { mg_scanner_trust_event($pdo, 'scanner_exception', 70, $identifier !== '' ? $identifier : null, $merchantUserId, is_array($location) ? $location : null, is_array($voucherToken) ? $voucherToken : null, null, $rawScan, ['exception_class' => $error::class, 'message' => $error->getMessage()]); } catch (Throwable) {}
    mg_security_log('error', 'merchant.scanner_claim_trust_failed', 'Scanner trust claim failed.', ['identifier' => $identifier, 'exception_class' => $error::class], $merchantUserId);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to process scanner claim right now.', 500);
}
