<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'gift.activity.view' : 'fulfillment.entitlements.issue');
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT de.public_id, p.public_id AS pppm_id, dfr.public_id AS rule_id,
                ca.public_id AS asset_id, ca.original_filename, ca.mime_type,
                de.status, de.downloads_used, dfr.max_downloads, de.expires_at,
                de.first_accessed_at, de.last_accessed_at
         FROM digital_entitlements de
         INNER JOIN pppm_items p ON p.id = de.pppm_item_id
         INNER JOIN digital_fulfillment_rules dfr ON dfr.id = de.fulfillment_rule_id
         INNER JOIN catalog_assets ca ON ca.id = dfr.asset_id
         WHERE de.entitled_user_id = ? OR p.owner_user_id = ? OR p.recipient_user_id = ?
         ORDER BY de.created_at DESC, de.id DESC'
    );
    $stmt->execute([(int) $user['id'], (int) $user['id'], (int) $user['id']]);
    mg_ok(['entitlements' => $stmt->fetchAll()]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$pppmId = trim((string) ($input['pppm_id'] ?? ''));
$ruleId = strtolower(trim((string) ($input['rule_id'] ?? '')));
if ($pppmId === '' || $ruleId === '') mg_fail('PPPM item and fulfillment rule are required.', 422);

try {
    $pdo->beginTransaction();
    $itemStmt = $pdo->prepare(
        'SELECT * FROM pppm_items WHERE public_id = ? AND (issuer_user_id = ? OR merchant_user_id = ? OR owner_user_id = ?) LIMIT 1 FOR UPDATE'
    );
    $itemStmt->execute([$pppmId, (int) $user['id'], (int) $user['id'], (int) $user['id']]);
    $item = $itemStmt->fetch();
    if (!$item) mg_fail('PPPM item not found.', 404);

    $ruleStmt = $pdo->prepare(
        "SELECT dfr.*, cp.merchant_user_id
         FROM digital_fulfillment_rules dfr
         INNER JOIN catalog_product_versions cpv ON cpv.id = dfr.product_version_id
         INNER JOIN catalog_products cp ON cp.id = cpv.product_id
         WHERE dfr.public_id = ? AND dfr.status = 'active' AND cp.merchant_user_id = ? LIMIT 1"
    );
    $ruleStmt->execute([$ruleId, (int) $user['id']]);
    $rule = $ruleStmt->fetch();
    if (!$rule) mg_fail('Fulfillment rule not found.', 404);

    $entitledUserId = $item['recipient_user_id'] ?: $item['owner_user_id'] ?: null;
    $expiresAt = !empty($rule['access_duration_seconds']) ? date('Y-m-d H:i:s', time() + (int) $rule['access_duration_seconds']) : null;
    $publicId = mg_feed_uuid();
    $pdo->prepare(
        "INSERT INTO digital_entitlements
         (public_id, pppm_item_id, fulfillment_rule_id, entitled_user_id, status,
          downloads_used, expires_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'active', 0, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE entitled_user_id = VALUES(entitled_user_id),
           status = CASE WHEN status = 'revoked' THEN status ELSE 'active' END,
           expires_at = COALESCE(expires_at, VALUES(expires_at)), updated_at = NOW()"
    )->execute([$publicId, (int) $item['id'], (int) $rule['id'], $entitledUserId, $expiresAt]);

    $stmt = $pdo->prepare('SELECT public_id FROM digital_entitlements WHERE pppm_item_id = ? AND fulfillment_rule_id = ? LIMIT 1');
    $stmt->execute([(int) $item['id'], (int) $rule['id']]);
    $entitlementId = (string) $stmt->fetchColumn();
    mg_pppm_record_event($pdo, $item, 'digital_entitlement_issued', (string) $item['status'], (string) $item['status'], (int) $user['id'], null, ['entitlement_id' => $entitlementId]);
    $pdo->commit();
    mg_ok(['entitlement_id' => $entitlementId, 'pppm_id' => $pppmId, 'expires_at' => $expiresAt], 'Digital entitlement issued.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to issue digital entitlement.', 500);
}
