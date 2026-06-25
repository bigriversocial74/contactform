<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once __DIR__ . '/_claims.php';

function mg_scanner_claim_identifier(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '' || mb_strlen($raw) > 500) {
        mg_fail('Scan a Microgifter gift QR code or enter a gift identifier.', 422);
    }

    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = trim(rawurldecode($decoded));

    $queryCandidates = [];
    $parts = @parse_url($decoded);
    if (is_array($parts)) {
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (['gift','gift_id','id','item','g','claim','code'] as $key) {
                if (isset($query[$key]) && !is_array($query[$key])) {
                    $queryCandidates[] = trim((string) $query[$key]);
                }
            }
        }
        if (!empty($parts['path'])) {
            $queryCandidates[] = trim((string) $parts['path']);
        }
    }
    $queryCandidates[] = $decoded;

    foreach ($queryCandidates as $candidate) {
        if ($candidate === '') continue;
        if (preg_match('/GFT-[A-Z0-9-]{4,32}/i', $candidate, $match)) {
            return strtoupper($match[0]);
        }
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $candidate, $match)) {
            return strtolower($match[0]);
        }
    }

    $plain = strtoupper(trim($decoded));
    if (preg_match('/^GFT-[A-Z0-9-]{4,32}$/', $plain)) {
        return $plain;
    }

    mg_fail('This scan does not look like a Microgifter gift or claim QR code.', 422);
}

function mg_scanner_claim_assert_location_binding(array $claim, int $locationId): void
{
    $status = (string) ($claim['status'] ?? '');
    $claimLocationId = (int) ($claim['location_id'] ?? 0);
    if ($claimLocationId > 0 && $claimLocationId !== $locationId && in_array($status, ['verified','redeemed'], true)) {
        mg_fail('This gift was already verified for another merchant location.', 409);
    }
}

mg_require_method('POST');
$user = mg_require_permission('merchant.gifts.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);

$action = strtolower(trim((string) ($input['action'] ?? 'redeem')));
if (!in_array($action, ['verify','redeem'], true)) {
    mg_fail('Invalid scanner action.', 422);
}

$identifier = mg_scanner_claim_identifier($input['scan'] ?? $input['gift_id'] ?? '');
$locationPublicId = mg_claim_code_public_id((string) ($input['location_id'] ?? ''), 'Choose a merchant location for this scanner.');
$requireConfirm = !empty($input['require_confirmation']);
$confirmed = !empty($input['confirmed']);
$pdo = mg_db();
$workspace = mg_claim_workspace($pdo, $user);
$merchantUserId = (int) $user['id'];

try {
    $pdo->beginTransaction();

    $lookup = mg_claim_lookup($pdo, $merchantUserId, $identifier);
    $giftPublicId = (string) ($lookup['gift_id'] ?? $identifier);

    $giftStmt = $pdo->prepare("SELECT * FROM gifts WHERE public_id=? AND status IN ('sent','delivered','claimed') LIMIT 1 FOR UPDATE");
    $giftStmt->execute([$giftPublicId]);
    $gift = $giftStmt->fetch(PDO::FETCH_ASSOC);
    if (!$gift) {
        mg_fail('Gift is not available for scanner redemption.', 404);
    }

    $locationStmt = $pdo->prepare("SELECT ml.* FROM merchant_locations ml WHERE ml.public_id=? AND ml.workspace_id=? AND ml.merchant_user_id=? AND ml.status='active' LIMIT 1 FOR UPDATE");
    $locationStmt->execute([$locationPublicId, (int) $workspace['id'], $merchantUserId]);
    $location = $locationStmt->fetch(PDO::FETCH_ASSOC);
    if (!$location) {
        mg_fail('Merchant location not found or inactive.', 404);
    }

    $eligibilityStmt = $pdo->prepare('SELECT 1 FROM gift_merchant_eligibility WHERE gift_id=? AND merchant_user_id=? AND (location_id IS NULL OR location_id=?) LIMIT 1');
    $eligibilityStmt->execute([(int) $gift['id'], $merchantUserId, (int) $location['id']]);
    if (!$eligibilityStmt->fetchColumn()) {
        mg_fail('This location is not authorized for the gift.', 403);
    }

    $claimCodeStmt = $pdo->prepare("SELECT * FROM merchant_claim_codes WHERE merchant_user_id=? AND location_id=? AND status='active' AND (valid_from IS NULL OR valid_from<=NOW()) AND (valid_until IS NULL OR valid_until>=NOW()) AND (usage_limit IS NULL OR usage_count<usage_limit) ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $claimCodeStmt->execute([$merchantUserId, (int) $location['id']]);
    $claimCode = $claimCodeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$claimCode) {
        mg_fail('This scanner location does not have an active claim code assigned.', 409);
    }

    $claimStmt = $pdo->prepare('SELECT * FROM gift_claims WHERE gift_id=? LIMIT 1 FOR UPDATE');
    $claimStmt->execute([(int) $gift['id']]);
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
    if (!$claim) {
        $claimPublicId = mg_public_uuid();
        $pdo->prepare("INSERT INTO gift_claims (public_id,gift_id,location_id,status,failed_attempts,expires_at,created_at,updated_at) VALUES (?,?,?,'pending',0,?,NOW(),NOW())")->execute([
            $claimPublicId,
            (int) $gift['id'],
            (int) $location['id'],
            $gift['expires_at'] ?? null,
        ]);
        $claimStmt->execute([(int) $gift['id']]);
        $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$claim) {
        mg_fail('Unable to initialize this gift claim.', 500);
    }

    mg_scanner_claim_assert_location_binding($claim, (int) $location['id']);

    if ((string) $claim['status'] === 'redeemed' || (string) $gift['status'] === 'claimed') {
        $pdo->commit();
        mg_ok([
            'gift_id' => $giftPublicId,
            'claim_id' => (string) $claim['public_id'],
            'location_id' => $locationPublicId,
            'location_name' => (string) $location['name'],
            'verified' => true,
            'redeemed' => true,
            'already_redeemed' => true,
        ], 'Gift already redeemed.');
    }

    if (in_array((string) $claim['status'], ['cancelled','expired','locked'], true)) {
        mg_fail('This claim is not available.', 409);
    }

    if (!empty($claim['expires_at']) && strtotime((string) $claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE gift_claims SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int) $claim['id']]);
        $pdo->commit();
        mg_fail('This claim has expired.', 410);
    }

    $pepper = mg_claim_code_pepper();
    $ipHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $pepper);
    $uaHash = hash_hmac('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper);
    $pdo->prepare('INSERT INTO gift_claim_attempts (claim_id,actor_user_id,successful,ip_hash,user_agent_hash,created_at) VALUES (?,?,?,?,?,NOW())')->execute([
        (int) $claim['id'],
        $merchantUserId,
        1,
        $ipHash,
        $uaHash,
    ]);

    $pdo->prepare("UPDATE gift_claims SET location_id=?,merchant_claim_code_id=?,verified_by_user_id=?,status='verified',verified_at=COALESCE(verified_at,NOW()),failed_attempts=0,locked_at=NULL,updated_at=NOW() WHERE id=?")->execute([
        (int) $location['id'],
        (int) $claimCode['id'],
        $merchantUserId,
        (int) $claim['id'],
    ]);

    if ($action === 'verify' || ($action === 'redeem' && $requireConfirm && !$confirmed)) {
        $pdo->commit();
        mg_audit('gift.scanner_claim_verified', 'gift', ['gift_id' => $giftPublicId, 'location_id' => $locationPublicId], $merchantUserId);
        mg_ok([
            'gift_id' => $giftPublicId,
            'claim_id' => (string) $claim['public_id'],
            'location_id' => $locationPublicId,
            'location_name' => (string) $location['name'],
            'claim_code_last4' => (string) ($claimCode['code_last4'] ?? ''),
            'verified' => true,
            'redeemed' => false,
            'needs_confirmation' => $action === 'redeem' && $requireConfirm && !$confirmed,
            'gift' => [
                'title' => (string) ($gift['title'] ?? 'Microgift'),
                'value_cents' => (int) ($gift['value_cents'] ?? 0),
                'currency' => (string) ($gift['currency'] ?? 'USD'),
            ],
        ], $action === 'verify' ? 'Gift verified for this scanner location.' : 'Gift verified. Confirm redemption before claiming voucher.');
    }

    $pdo->prepare("UPDATE gift_claims SET status='redeemed',redeemed_by_user_id=?,redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$merchantUserId, (int) $claim['id']]);
    $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int) $claimCode['id']]);
    $pdo->prepare("UPDATE gifts SET status='claimed',claimed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int) $gift['id']]);
    mg_gift_event($pdo, (int) $gift['id'], $merchantUserId, 'claimed', [
        'claim_id' => (string) $claim['public_id'],
        'location_id' => $locationPublicId,
        'source' => 'scanner',
    ]);

    if ((int) ($gift['sender_user_id'] ?? 0) > 0) {
        $notificationStmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,gift_id,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $notificationStmt->execute([
            mg_public_uuid(),
            (int) $gift['sender_user_id'],
            'gift_claimed',
            'Gift redeemed',
            'A merchant location successfully redeemed your gift.',
            '/claimed.php?gift=' . rawurlencode($giftPublicId),
            (int) $gift['id'],
        ]);
    }

    $pdo->commit();
    mg_audit('gift.scanner_claim_redeemed', 'gift', ['gift_id' => $giftPublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_event('gift.scanner_claim_redeemed', ['gift_id' => $giftPublicId, 'location_id' => $locationPublicId], $merchantUserId);
    mg_ok([
        'gift_id' => $giftPublicId,
        'claim_id' => (string) $claim['public_id'],
        'location_id' => $locationPublicId,
        'location_name' => (string) $location['name'],
        'claim_code_last4' => (string) ($claimCode['code_last4'] ?? ''),
        'verified' => true,
        'redeemed' => true,
        'gift' => [
            'title' => (string) ($gift['title'] ?? 'Microgift'),
            'value_cents' => (int) ($gift['value_cents'] ?? 0),
            'currency' => (string) ($gift['currency'] ?? 'USD'),
        ],
    ], 'Gift redeemed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.scanner_claim_failed', 'Scanner claim failed.', [
        'identifier' => $identifier,
        'location_id' => $locationPublicId,
        'exception_class' => $error::class,
    ], $merchantUserId);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to process scanner claim right now.', 500);
}
