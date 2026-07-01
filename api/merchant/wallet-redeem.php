<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/rewards/_identity_gate.php';
require_once dirname(__DIR__) . '/rewards/_wallet_lifecycle_automation.php';
require_once dirname(__DIR__) . '/ads/_direct_attribution.php';

function mg_wr_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_wr_event(PDO $pdo, array $item, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_wr_uuid(),
        (int)$item['merchant_user_id'],
        $item['campaign_id'] === null ? null : (int)$item['campaign_id'],
        (int)$item['id'],
        $item['contact_id'] === null ? null : (int)$item['contact_id'],
        $eventType,
        json_encode($context + ['wallet_item_id' => (string)$item['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$walletId = strtolower(trim((string)($input['wallet_item_id'] ?? '')));
$locationCode = trim((string)($input['location_code'] ?? ''));
$adAttribution = mg_ads_direct_attribution_from_input($input);
if ($walletId === '' || strlen($walletId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $walletId)) {
    mg_fail('Invalid wallet item.', 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM wallet_items WHERE public_id = ? AND merchant_user_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$walletId, $merchantId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        $pdo->rollBack();
        mg_fail('Wallet item not found.', 404);
    }

    if (!empty($item['expires_at']) && strtotime((string)$item['expires_at']) < time()) {
        $pdo->prepare("UPDATE wallet_items SET status = 'expired', updated_at = NOW() WHERE id = ?")->execute([(int)$item['id']]);
        mg_wr_event($pdo, $item, 'wallet_item.expired');
        $pdo->commit();
        mg_fail('Wallet item has expired.', 410);
    }

    if ((string)$item['status'] === 'redeemed') {
        $pdo->commit();
        mg_ads_track_direct_wallet_event($pdo, 'redeem', $item, ['ad_attribution' => $adAttribution], ['id' => (int)($item['user_id'] ?? 0)], ['already_redeemed' => true, 'location_code' => $locationCode !== '' ? $locationCode : null]);
        mg_ok(['wallet_item_id' => $walletId, 'wallet_status' => 'redeemed', 'already_redeemed' => true], 'Wallet item already redeemed.');
    }
    if ((string)$item['status'] !== 'claimed') {
        $pdo->rollBack();
        mg_fail('Wallet item must be claimed before redemption.', 409);
    }

    $claimantUserId = (int)($item['user_id'] ?? 0);
    if ($claimantUserId < 1) {
        $pdo->rollBack();
        mg_fail('Wallet item claimant is unavailable.', 409);
    }
    mg_reward_require_verified_email($pdo, $claimantUserId, 'redeem this reward');

    $metadata = mg_ads_decode_json($item['metadata_json'] ?? null);
    $metadata = mg_ads_wallet_metadata_with_attribution($metadata, $adAttribution);
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $item['metadata_json'] = $metadataJson;

    $pdo->prepare("UPDATE wallet_items SET status = 'redeemed', redeemed_at = NOW(), metadata_json = ?, updated_at = NOW() WHERE id = ?")->execute([$metadataJson, (int)$item['id']]);
    $walletAfter = $item;
    $walletAfter['status'] = 'redeemed';
    $walletAfter['metadata_json'] = $metadataJson;
    $automation = mg_wallet_lifecycle_automation($pdo, $item, 'wallet_item.redeemed', $claimantUserId, [], ['location_code' => $locationCode !== '' ? $locationCode : null]);
    mg_wr_event($pdo, $walletAfter, 'wallet_item.redeemed', ['location_code' => $locationCode !== '' ? $locationCode : null, 'lifecycle_automation' => $automation, 'ad_attribution' => $adAttribution ?: null]);
    $pdo->commit();
    mg_ads_track_direct_wallet_event($pdo, 'redeem', $walletAfter, ['ad_attribution' => $adAttribution], ['id' => $claimantUserId], ['already_redeemed' => false, 'location_code' => $locationCode !== '' ? $locationCode : null]);
    mg_ok(['wallet_item_id' => $walletId, 'wallet_status' => 'redeemed', 'already_redeemed' => false, 'lifecycle_automation' => $automation], 'Wallet item redeemed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'wallet.redeem.failed', 'Unable to redeem wallet item.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to redeem wallet item.', 500);
}
