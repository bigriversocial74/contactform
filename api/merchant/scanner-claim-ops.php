<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';
require_once __DIR__ . '/_scanner_operations.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.gifts.redeem');
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$merchantUserId = (int)$user['id'];
$workspace = mg_claim_workspace($pdo, $user);
$locationPublicId = mg_claim_code_public_id((string)($input['location_id'] ?? ''), 'Choose a merchant location for this scanner.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM merchant_locations WHERE public_id=? AND workspace_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
    $stmt->execute([$locationPublicId, (int)$workspace['id'], $merchantUserId]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$location) mg_fail('Merchant location not found or inactive.', 404);
    $device = mg_scanner_ops_touch_device($pdo, $merchantUserId, (int)$workspace['id'], $location, $input);
    $settings = mg_scanner_ops_settings($pdo, $merchantUserId, (int)$workspace['id'], (int)$location['id']);
    $requireConfirm = !empty($input['require_confirmation']);
    mg_scanner_ops_apply_settings($pdo, $settings, $input, $merchantUserId, $location, $device, (string)($input['scan'] ?? ''), $requireConfirm);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.scanner_claim_ops_failed', 'Scanner operations preflight failed.', ['exception_class' => $error::class], $merchantUserId);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to prepare scanner operations.', 500);
}

require __DIR__ . '/scanner-claim-trust.php';
