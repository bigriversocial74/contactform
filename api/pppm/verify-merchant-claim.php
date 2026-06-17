<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$itemPublicId = trim((string) ($input['id'] ?? ''));
$locationPublicId = strtolower(trim((string) ($input['location_id'] ?? '')));
$merchantCode = trim((string) ($input['code'] ?? ''));

if ($itemPublicId === '' || strlen($itemPublicId) > 32 || !preg_match('/^(GFT|PPPM)-[A-Z0-9-]+$/', $itemPublicId)) {
    mg_fail('Invalid PPPM item identifier.', 422);
}
if (strlen($locationPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $locationPublicId)) {
    mg_fail('Invalid merchant location.', 422);
}
if ($merchantCode === '' || mb_strlen($merchantCode) > 64) {
    mg_fail('Invalid merchant code.', 422);
}

$config = require dirname(__DIR__) . '/config.php';
$pepper = (string) ($config['security']['claim_code_pepper'] ?? '');
if ($pepper === '') {
    mg_fail('Merchant verification is not configured.', 503);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();

    $itemStmt = $pdo->prepare(
        "SELECT * FROM pppm_items
         WHERE public_id = ? AND status IN ('sent','delivered','viewed','claim_pending')
         LIMIT 1 FOR UPDATE"
    );
    $itemStmt->execute([$itemPublicId]);
    $item = $itemStmt->fetch();
    if (!$item) {
        mg_fail('PPPM item is not available for redemption.', 404);
    }

    $locationStmt = $pdo->prepare(
        "SELECT * FROM merchant_locations
         WHERE public_id = ? AND merchant_user_id = ? AND status = 'active'
         LIMIT 1 FOR UPDATE"
    );
    $locationStmt->execute([$locationPublicId, (int) $user['id']]);
    $location = $locationStmt->fetch();
    if (!$location) {
        mg_fail('Merchant location not found.', 404);
    }

    $eligibilityStmt = $pdo->prepare(
        'SELECT 1 FROM pppm_merchant_eligibility
         WHERE pppm_item_id = ? AND merchant_user_id = ?
           AND (merchant_location_id IS NULL OR merchant_location_id = ?)
         LIMIT 1'
    );
    $eligibilityStmt->execute([(int) $item['id'], (int) $user['id'], (int) $location['id']]);
    if (!$eligibilityStmt->fetchColumn()) {
        mg_fail('This merchant location is not authorized for the PPPM item.', 403);
    }

    $claimStmt = $pdo->prepare('SELECT * FROM pppm_claims WHERE pppm_item_id = ? LIMIT 1 FOR UPDATE');
    $claimStmt->execute([(int) $item['id']]);
    $claim = $claimStmt->fetch();
    if (!$claim) {
        $claimPublicId = mg_pppm_uuid();
        $pdo->prepare(
            "INSERT INTO pppm_claims
             (public_id, pppm_item_id, claimant_user_id, claimant_external_id, status, failed_attempts,
              merchant_location_id, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', 0, ?, ?, NOW(), NOW())"
        )->execute([
            $claimPublicId,
            (int) $item['id'],
            $item['recipient_user_id'] ?? null,
            $item['recipient_external_id'] ?? null,
            (int) $location['id'],
            $item['expires_at'] ?? null,
        ]);
        $claimStmt->execute([(int) $item['id']]);
        $claim = $claimStmt->fetch();
    }

    if (!$claim || in_array((string) $claim['status'], ['redeemed','cancelled','expired','locked'], true)) {
        mg_fail('This PPPM claim is not available.', 409);
    }
    if (!empty($claim['expires_at']) && strtotime((string) $claim['expires_at']) < time()) {
        $pdo->prepare("UPDATE pppm_claims SET status = 'expired', updated_at = NOW() WHERE id = ?")
            ->execute([(int) $claim['id']]);
        $pdo->prepare("UPDATE pppm_items SET status = 'expired', version_no = version_no + 1, updated_at = NOW() WHERE id = ?")
            ->execute([(int) $item['id']]);
        $pdo->commit();
        mg_fail('This PPPM item has expired.', 410);
    }

    $candidateHash = hash_hmac('sha256', $merchantCode, $pepper);
    $codesStmt = $pdo->prepare(
        "SELECT * FROM merchant_claim_codes
         WHERE merchant_user_id = ? AND location_id = ? AND status = 'active'
           AND (valid_from IS NULL OR valid_from <= NOW())
           AND (valid_until IS NULL OR valid_until >= NOW())
           AND (usage_limit IS NULL OR usage_count < usage_limit)
         FOR UPDATE"
    );
    $codesStmt->execute([(int) $user['id'], (int) $location['id']]);
    $matched = null;
    foreach ($codesStmt->fetchAll() as $candidate) {
        if (hash_equals((string) $candidate['code_hash'], $candidateHash)) {
            $matched = $candidate;
            break;
        }
    }

    $success = is_array($matched);
    $ipHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $pepper);
    $uaHash = hash_hmac('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper);
    $pdo->prepare(
        'INSERT INTO pppm_claim_attempts
         (claim_id, actor_user_id, successful, ip_hash, user_agent_hash, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    )->execute([(int) $claim['id'], (int) $user['id'], $success ? 1 : 0, $ipHash, $uaHash]);

    if (!$success) {
        $attempts = (int) $claim['failed_attempts'] + 1;
        $locked = $attempts >= 5;
        $pdo->prepare(
            'UPDATE pppm_claims
             SET failed_attempts = ?, status = ?, locked_at = ?, merchant_location_id = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $attempts,
            $locked ? 'locked' : 'pending',
            $locked ? date('Y-m-d H:i:s') : null,
            (int) $location['id'],
            (int) $claim['id'],
        ]);
        $pdo->commit();
        mg_audit('pppm.claim_failed', 'pppm_item', [
            'item_id' => $itemPublicId,
            'location_id' => $locationPublicId,
            'locked' => $locked,
        ], (int) $user['id']);
        mg_fail($locked ? 'This PPPM claim is locked.' : 'Invalid merchant code.', $locked ? 423 : 422);
    }

    $pdo->prepare(
        "UPDATE pppm_claims
         SET merchant_location_id = ?, merchant_claim_code_id = ?, verified_by_user_id = ?,
             status = 'verified', verified_at = NOW(), failed_attempts = 0, locked_at = NULL, updated_at = NOW()
         WHERE id = ?"
    )->execute([(int) $location['id'], (int) $matched['id'], (int) $user['id'], (int) $claim['id']]);

    $fromStatus = (string) $item['status'];
    $pdo->prepare(
        "UPDATE pppm_items SET status = 'verified', version_no = version_no + 1, updated_at = NOW() WHERE id = ?"
    )->execute([(int) $item['id']]);
    $itemStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $itemStmt->execute([(int) $item['id']]);
    $updated = $itemStmt->fetch();
    mg_pppm_record_event($pdo, $updated, 'merchant_verified', $fromStatus, 'verified', (int) $user['id'], null, [
        'location_id' => $locationPublicId,
        'claim_id' => (string) $claim['public_id'],
    ]);

    $pdo->commit();
    mg_audit('pppm.claim_verified', 'pppm_item', [
        'item_id' => $itemPublicId,
        'location_id' => $locationPublicId,
    ], (int) $user['id']);
    mg_ok([
        'item_id' => $itemPublicId,
        'claim_id' => (string) $claim['public_id'],
        'location_id' => $locationPublicId,
        'verified' => true,
    ], 'PPPM item and merchant code verified.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'pppm.claim_verify_failed', 'PPPM claim verification failed.', [
        'item_id' => $itemPublicId,
        'exception_type' => get_class($e),
    ], (int) $user['id']);
    mg_fail('Unable to verify this PPPM item right now.', 500);
}
