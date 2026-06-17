<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

mg_require_method('POST');
$user = mg_require_permission('media.moderate');
$input = mg_input();
mg_require_csrf_for_write($input);
$assetId = strtolower(trim((string) ($input['asset_id'] ?? '')));
$status = trim((string) ($input['moderation_status'] ?? ''));
$retentionUntil = trim((string) ($input['retention_until'] ?? '')) ?: null;
$allowed = ['unreviewed','approved','quarantined','blocked','takedown'];
if ($assetId === '' || !in_array($status, $allowed, true)) mg_fail('Invalid media status request.', 422);
if ($retentionUntil !== null && strtotime($retentionUntil) === false) mg_fail('Invalid retention date.', 422);

$pdo = mg_db();
$stmt = $pdo->prepare('SELECT id, owner_user_id, status, moderation_status FROM catalog_assets WHERE public_id = ? LIMIT 1 FOR UPDATE');
$pdo->beginTransaction();
try {
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
    if (!$asset) mg_fail('Asset not found.', 404);
    $assetStatus = in_array($status, ['quarantined','blocked','takedown'], true) ? 'quarantined' : ((string) $asset['status'] === 'quarantined' ? 'ready' : (string) $asset['status']);
    $pdo->prepare('UPDATE catalog_assets SET moderation_status = ?, status = ?, retention_until = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$status, $assetStatus, $retentionUntil, (int) $asset['id']]);
    if (in_array($status, ['blocked','takedown'], true)) {
        $pdo->prepare('UPDATE media_delivery_tokens SET revoked_at = NOW() WHERE asset_id = ? AND revoked_at IS NULL')
            ->execute([(int) $asset['id']]);
        $pdo->prepare("UPDATE catalog_asset_variants SET status = 'quarantined', updated_at = NOW() WHERE source_asset_id = ? AND status = 'ready'")
            ->execute([(int) $asset['id']]);
    }
    $pdo->commit();
    mg_audit('media.moderation_changed', 'catalog_asset', ['asset_id' => $assetId, 'moderation_status' => $status], (int) $user['id']);
    mg_ok(['asset_id' => $assetId, 'moderation_status' => $status, 'asset_status' => $assetStatus], 'Media status updated.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to update media status.', 500);
}
