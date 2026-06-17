<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

mg_require_method('POST');
$user = mg_require_permission('gift.activity.view');
$input = mg_input();
mg_require_csrf_for_write($input);
$assetPublicId = strtolower(trim((string) ($input['asset_id'] ?? '')));
$pppmId = trim((string) ($input['pppm_id'] ?? ''));
$purpose = trim((string) ($input['purpose'] ?? 'feed_stream'));
$profileKey = trim((string) ($input['profile'] ?? '')) ?: null;
if ($assetPublicId === '' || $pppmId === '' || !in_array($purpose, ['feed_stream','preview'], true)) {
    mg_fail('Invalid media token request.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT ca.id AS asset_id, ca.status, ca.moderation_status
         FROM catalog_assets ca
         INNER JOIN feed_post_elements fpe ON fpe.asset_id = ca.id
         INNER JOIN pppm_feed_bindings pfb ON pfb.feed_post_version_id = fpe.feed_post_version_id
         INNER JOIN pppm_items p ON p.id = pfb.pppm_item_id
         WHERE ca.public_id = ? AND p.public_id = ?
           AND (p.recipient_user_id = ? OR p.owner_user_id = ? OR p.issuer_user_id = ? OR p.merchant_user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$assetPublicId, $pppmId, (int) $user['id'], (int) $user['id'], (int) $user['id'], (int) $user['id']]);
    $asset = $stmt->fetch();
    if (!$asset || (string) $asset['status'] !== 'ready' || in_array((string) $asset['moderation_status'], ['quarantined','blocked','takedown'], true)) {
        mg_fail('Media is unavailable.', 404);
    }

    $variantId = null;
    if ($profileKey) {
        $variantStmt = $pdo->prepare(
            "SELECT cav.id FROM catalog_asset_variants cav
             INNER JOIN media_delivery_profiles mdp ON mdp.id = cav.profile_id
             WHERE cav.source_asset_id = ? AND mdp.profile_key = ? AND cav.status = 'ready'
             LIMIT 1"
        );
        $variantStmt->execute([(int) $asset['asset_id'], $profileKey]);
        $variantId = $variantStmt->fetchColumn() ?: null;
    }

    $token = mg_media_issue_token($pdo, [
        'asset_id' => $variantId ? null : (int) $asset['asset_id'],
        'variant_id' => $variantId ? (int) $variantId : null,
        'user_id' => (int) $user['id'],
        'purpose' => $purpose,
        'disposition' => 'inline',
        'max_uses' => null,
    ], 600);
    $pdo->commit();
    mg_ok([
        'media_url' => '/api/fulfillment/deliver.php?token=' . rawurlencode($token['token']),
        'expires_at' => $token['expires_at'],
        'profile' => $variantId ? $profileKey : 'source',
    ], 'Media token issued.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to issue media token.', 500);
}
