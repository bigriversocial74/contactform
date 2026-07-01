<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__) . '/rewards/_identity_gate.php';
require_once dirname(__DIR__) . '/rewards/_wallet_lifecycle_automation.php';
require_once dirname(__DIR__) . '/microgifts/_idempotency.php';
require_once dirname(__DIR__) . '/microgifts/_golden_path_integrity.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/ads/_direct_attribution.php';

function mg_wc_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 15) | 64);
    $b[8] = chr((ord($b[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_wc_event(PDO $pdo, array $item, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_wc_uuid(),
        (int)$item['merchant_user_id'],
        $item['campaign_id'] === null ? null : (int)$item['campaign_id'],
        (int)$item['id'],
        $item['contact_id'] === null ? null : (int)$item['contact_id'],
        $eventType,
        json_encode($context + ['wallet_item_id' => (string)$item['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_wc_bridge_input(array $item, int $userId): array
{
    return [
        'merchant_user_id' => (int)$item['merchant_user_id'],
        'recipient_user_id' => $userId,
        'recipient_external_id' => (string)($item['source_id'] ?? $item['public_id']),
        'wallet_item_db_id' => (int)$item['id'],
        'wallet_item_public_id' => (string)$item['public_id'],
        'campaign_public_id' => (string)($item['campaign_public_id'] ?? ''),
        'reward_template_public_id' => (string)($item['reward_template_public_id'] ?? ''),
        'source_type' => (string)($item['source_type'] ?? 'wallet_claim'),
        'source_reference' => (string)$item['public_id'],
        'source_line_reference' => (string)($item['source_id'] ?? $item['public_id']),
        'title' => (string)($item['title_snapshot'] ?? 'Microgifter reward'),
        'description' => $item['reward_template_description'] ?? null,
        'currency' => (string)($item['currency_snapshot'] ?? 'USD'),
        'display_value_cents' => (int)($item['value_cents_snapshot'] ?? 0),
        'expires_at' => $item['expires_at'] ?? null,
        'redemption_instructions' => $item['redemption_instructions'] ?? null,
        'terms' => ['wallet_item_id' => (string)$item['public_id']],
    ];
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
$pdo = mg_db();
$walletId = strtolower(trim((string)($input['wallet_item_id'] ?? '')));
$userId = (int)$user['id'];
$userEmail = strtolower(trim((string)($user['email'] ?? '')));
$adAttribution = mg_ads_direct_attribution_from_input($input);

if ($walletId === '' || strlen($walletId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $walletId)) mg_fail('Invalid wallet item.', 422);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT wi.*,cc.email contact_email,c.public_id campaign_public_id,rt.public_id reward_template_public_id,rt.description reward_template_description,rt.redemption_instructions FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id LEFT JOIN campaigns c ON c.id=wi.campaign_id LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id WHERE wi.public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$walletId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        $pdo->rollBack();
        mg_fail('Wallet item not found.', 404);
    }

    $contactEmail = strtolower(trim((string)($item['contact_email'] ?? '')));
    $sourceId = strtolower(trim((string)($item['source_id'] ?? '')));
    $owned = ((int)($item['user_id'] ?? 0)) === $userId;
    $emailMatch = $userEmail !== '' && ($contactEmail === $userEmail || $sourceId === $userEmail);
    if (!$owned && !$emailMatch) {
        $pdo->rollBack();
        mg_fail('Wallet item is not available for your account.', 403);
    }

    mg_reward_require_verified_email($pdo, $userId, 'claim this reward');

    if (!empty($item['expires_at']) && strtotime((string)$item['expires_at']) < time()) {
        $pdo->prepare("UPDATE wallet_items SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$item['id']]);
        mg_wc_event($pdo, $item, 'wallet_item.expired');
        $pdo->commit();
        mg_fail('Wallet item has expired.', 410);
    }
    if (in_array((string)$item['status'], ['redeemed','expired','cancelled'], true)) {
        $pdo->rollBack();
        mg_fail('Wallet item is not claimable.', 409);
    }

    $metadata = mg_ads_decode_json($item['metadata_json'] ?? null);
    $metadata = mg_ads_wallet_metadata_with_attribution($metadata, $adAttribution);
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $item['metadata_json'] = $metadataJson;

    $bridge = mg_zero_reward_issue_from_wallet($pdo, mg_wc_bridge_input($item, $userId));
    if (!empty($bridge['pending_account_link'])) {
        $pdo->rollBack();
        mg_fail('Wallet item is not linked to a Microgifter account yet.', 409);
    }
    $instanceId = (string)($bridge['microgift_instance_id'] ?? '');
    if ($instanceId === '') throw new RuntimeException('Wallet item is missing its Microgift bridge.');

    $instance = mg_microgift_load_instance($pdo, $instanceId);
    $claimResult = null;
    if (in_array((string)$instance['status'], ['claimed','redeemable','redeemed'], true)) {
        $claimResult = ['instance_id' => $instanceId, 'status' => (string)$instance['status'], 'duplicate' => true];
    } else {
        $claimResult = mg_microgift_integrity_claim($pdo, $userId, ['instance_id' => $instanceId, 'idempotency_key' => substr('wallet-claim:' . hash('sha256', $walletId . '|' . $userId), 0, 190)]);
        $instance = mg_microgift_load_instance($pdo, $instanceId);
    }
    $projection = mg_action_center_project_lifecycle($pdo, $instance, ['sender_user_id' => (int)$item['merchant_user_id'], 'recipient_user_id' => $userId, 'merchant_user_id' => (int)$item['merchant_user_id'], 'occurred_at' => date('Y-m-d H:i:s')]);
    $pdo->prepare("UPDATE wallet_items SET user_id=?,pppm_item_id=COALESCE(pppm_item_id,?),status='claimed',viewed_at=COALESCE(viewed_at,NOW()),claimed_at=COALESCE(claimed_at,NOW()),metadata_json=?,updated_at=NOW() WHERE id=?")->execute([$userId, (int)($bridge['pppm_item_db_id'] ?? 0), $metadataJson, (int)$item['id']]);
    $walletAfter = $item;
    $walletAfter['user_id'] = $userId;
    $walletAfter['status'] = 'claimed';
    $walletAfter['metadata_json'] = $metadataJson;
    $automation = mg_wallet_lifecycle_automation($pdo, $item, 'wallet_item.claimed', $userId, $user, ['pppm_bridge' => $bridge, 'claim' => $claimResult, 'action_center' => $projection]);
    mg_wc_event($pdo, $walletAfter, 'wallet_item.claimed', ['pppm_bridge' => $bridge, 'claim' => $claimResult, 'action_center' => $projection, 'lifecycle_automation' => $automation, 'ad_attribution' => $adAttribution ?: null]);
    $pdo->commit();
    mg_ads_track_direct_wallet_event($pdo, 'claim', $walletAfter, ['ad_attribution' => $adAttribution], $user, ['duplicate' => !empty($claimResult['duplicate'])]);
    mg_ok(['wallet_item_id' => $walletId, 'wallet_status' => 'claimed', 'pppm_bridge' => $bridge, 'claim' => $claimResult, 'action_center' => $projection, 'lifecycle_automation' => $automation], 'Wallet item claimed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'wallet.claim.failed', 'Unable to claim wallet item.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Unable to claim wallet item.', 500);
}
