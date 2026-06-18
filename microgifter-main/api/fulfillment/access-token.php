<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

mg_require_method('POST');
$user = mg_require_permission('gift.activity.view');
$input = mg_input();
mg_require_csrf_for_write($input);
$entitlementId = strtolower(trim((string) ($input['entitlement_id'] ?? '')));
$purpose = trim((string) ($input['purpose'] ?? 'download'));
if (!in_array($purpose, ['download','feed_stream','preview'], true)) mg_fail('Invalid access purpose.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT de.*, dfr.asset_id, dfr.access_mode, dfr.max_downloads, dfr.disposition,
                dfr.filename_override, ca.status AS asset_status, ca.moderation_status
         FROM digital_entitlements de
         INNER JOIN digital_fulfillment_rules dfr ON dfr.id = de.fulfillment_rule_id
         INNER JOIN catalog_assets ca ON ca.id = dfr.asset_id
         WHERE de.public_id = ? AND (de.entitled_user_id = ? OR de.entitled_user_id IS NULL)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$entitlementId, (int) $user['id']]);
    $entitlement = $stmt->fetch();
    if (!$entitlement) mg_fail('Digital entitlement not found.', 404);
    if ((string) $entitlement['status'] !== 'active') mg_fail('Digital entitlement is not active.', 409);
    if (!empty($entitlement['expires_at']) && strtotime((string) $entitlement['expires_at']) < time()) {
        $pdo->prepare("UPDATE digital_entitlements SET status = 'expired', updated_at = NOW() WHERE id = ?")->execute([(int) $entitlement['id']]);
        mg_fail('Digital entitlement has expired.', 410);
    }
    if ($purpose === 'download' && !in_array((string) $entitlement['access_mode'], ['download','both'], true)) mg_fail('Download access is not allowed.', 403);
    if ($purpose !== 'download' && !in_array((string) $entitlement['access_mode'], ['stream','both'], true)) mg_fail('Streaming access is not allowed.', 403);
    if ($entitlement['max_downloads'] !== null && (int) $entitlement['downloads_used'] >= (int) $entitlement['max_downloads']) {
        $pdo->prepare("UPDATE digital_entitlements SET status = 'exhausted', updated_at = NOW() WHERE id = ?")->execute([(int) $entitlement['id']]);
        mg_fail('Download limit reached.', 409);
    }
    if ((string) $entitlement['asset_status'] !== 'ready' || in_array((string) $entitlement['moderation_status'], ['quarantined','blocked','takedown'], true)) {
        mg_fail('Digital asset is unavailable.', 451);
    }

    $token = mg_media_issue_token($pdo, [
        'asset_id' => (int) $entitlement['asset_id'],
        'entitlement_id' => (int) $entitlement['id'],
        'user_id' => (int) $user['id'],
        'purpose' => $purpose,
        'disposition' => $purpose === 'download' ? 'attachment' : 'inline',
        'max_uses' => 1,
    ], 300);

    $pdo->prepare(
        'INSERT INTO digital_access_events
         (public_id, entitlement_id, user_id, event_type, ip_hash, user_agent_hash, metadata_json, occurred_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        mg_feed_uuid(),
        (int) $entitlement['id'],
        (int) $user['id'],
        'token_issued',
        mg_media_hash_context((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        mg_media_hash_context((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        json_encode(['purpose' => $purpose], JSON_UNESCAPED_SLASHES),
    ]);
    $pdo->commit();
    mg_ok([
        'access_url' => '/api/fulfillment/deliver.php?token=' . rawurlencode($token['token']),
        'expires_at' => $token['expires_at'],
        'purpose' => $purpose,
    ], 'Access token issued.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to issue access token.', 500);
}
