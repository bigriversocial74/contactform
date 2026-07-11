<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__) . '/rewards/_identity_gate.php';
require_once dirname(__DIR__) . '/ads/_direct_attribution.php';

function mg_wpa_uuid(): string
{
    return mg_microgift_uuid();
}

function mg_wpa_event(PDO $pdo, array $item, string $eventType, array $context = []): void
{
    if (empty($item['campaign_id'])) return;
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_wpa_uuid(),
        (int)$item['merchant_user_id'],
        (int)$item['campaign_id'],
        (int)$item['id'],
        $item['contact_id'] === null ? null : (int)$item['contact_id'],
        $eventType,
        json_encode($context + ['wallet_item_id'=>(string)$item['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_wpa_bridge_input(array $item, int $userId): array
{
    return [
        'merchant_user_id'=>(int)$item['merchant_user_id'],
        'recipient_user_id'=>$userId,
        'recipient_external_id'=>(string)($item['source_id'] ?? $item['public_id']),
        'recipient_name'=>(string)($item['recipient_name'] ?? ''),
        'wallet_item_db_id'=>(int)$item['id'],
        'wallet_item_public_id'=>(string)$item['public_id'],
        'campaign_public_id'=>(string)($item['campaign_public_id'] ?? ''),
        'reward_template_public_id'=>(string)($item['reward_template_public_id'] ?? ''),
        'source_type'=>(string)($item['source_type'] ?? 'wallet_reward'),
        'source_reference'=>(string)$item['public_id'],
        'source_line_reference'=>(string)($item['source_id'] ?? $item['public_id']),
        'title'=>(string)($item['title_snapshot'] ?? 'Microgifter reward'),
        'description'=>$item['reward_template_description'] ?? null,
        'currency'=>(string)($item['currency_snapshot'] ?? 'USD'),
        'display_value_cents'=>(int)($item['value_cents_snapshot'] ?? 0),
        'expires_at'=>$item['expires_at'] ?? null,
        'redemption_instructions'=>$item['redemption_instructions'] ?? null,
        'terms'=>['wallet_item_id'=>(string)$item['public_id']],
    ];
}

/**
 * Compatibility adapter for previously staged wallet records.
 *
 * This does not create a second claim lifecycle and does not move the reward
 * into a customer-visible wallet. It ensures the canonical Microgift/PPPM
 * record exists and projects the reward into Action Center Inbox.
 */
function mg_wallet_claim_to_pppm(PDO $pdo, array $user, string $walletId, array $input = []): array
{
    $walletId = strtolower(trim($walletId));
    $userId = (int)($user['id'] ?? 0);
    $userEmail = strtolower(trim((string)($user['email'] ?? '')));
    if ($userId <= 0) mg_fail('Sign in to receive this reward.', 401);
    if (strlen($walletId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $walletId) !== 1) mg_fail('Invalid reward.', 422);

    $adAttribution = mg_ads_direct_attribution_from_input($input);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT wi.*,cc.email contact_email,cc.name recipient_name,c.public_id campaign_public_id,rt.public_id reward_template_public_id,rt.description reward_template_description,rt.redemption_instructions FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id LEFT JOIN campaigns c ON c.id=wi.campaign_id LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id WHERE wi.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$walletId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) throw new RuntimeException('Wallet staging record not found.');

        $contactEmail = strtolower(trim((string)($item['contact_email'] ?? '')));
        $sourceId = strtolower(trim((string)($item['source_id'] ?? '')));
        $owned = ((int)($item['user_id'] ?? 0)) === $userId;
        $emailMatch = $userEmail !== '' && ($contactEmail === $userEmail || $sourceId === $userEmail);
        if (!$owned && !$emailMatch) throw new RuntimeException('Wallet staging record is not available for this account.');

        mg_reward_require_verified_email($pdo, $userId, 'receive this reward');
        if (!empty($item['expires_at']) && strtotime((string)$item['expires_at']) < time()) {
            $pdo->prepare("UPDATE wallet_items SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$item['id']]);
            mg_wpa_event($pdo, $item, 'wallet_item.expired');
            $pdo->commit();
            mg_fail('Reward has expired.', 410);
        }
        if (in_array((string)$item['status'], ['redeemed','expired','cancelled'], true)) throw new RuntimeException('Reward cannot be delivered from its current state.');

        $metadata = mg_ads_decode_json($item['metadata_json'] ?? null);
        $metadata = mg_ads_wallet_metadata_with_attribution($metadata, $adAttribution);
        $metadata['authority'] = 'microgift_pppm_inbox';
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $item['metadata_json'] = $metadataJson;

        $bridge = mg_zero_reward_issue_from_wallet($pdo, mg_wpa_bridge_input($item, $userId));
        if (!empty($bridge['pending_account_link'])) throw new RuntimeException('Reward is not linked to a Microgifter account yet.');
        if (empty($bridge['microgift_instance_id']) || empty($bridge['pppm_item_db_id']) || empty($bridge['action_center']['recipient_inbox_item_id'])) {
            throw new RuntimeException('Reward is missing its Microgift, PPPM, or Inbox projection.');
        }

        $pdo->prepare('UPDATE wallet_items SET user_id=?,pppm_item_id=?,metadata_json=?,updated_at=NOW() WHERE id=?')
            ->execute([$userId,(int)$bridge['pppm_item_db_id'],$metadataJson,(int)$item['id']]);
        mg_wpa_event($pdo, $item, 'wallet_item.projected_to_inbox', [
            'microgift_instance_id'=>(string)$bridge['microgift_instance_id'],
            'pppm_item_id'=>(string)$bridge['pppm_item_id'],
            'action_center'=>$bridge['action_center'],
        ]);
        mg_audit('account.wallet_reward_projected_to_inbox', 'wallet_item', [
            'wallet_item_id'=>$walletId,
            'microgift_instance_id'=>(string)$bridge['microgift_instance_id'],
            'pppm_item_id'=>(string)$bridge['pppm_item_id'],
            'destination'=>'inbox',
        ], $userId);
        $pdo->commit();

        try {
            mg_ads_track_direct_wallet_event($pdo, 'claim', $item, ['ad_attribution'=>$adAttribution], $user, ['duplicate'=>false]);
        } catch (Throwable $trackingError) {
            mg_security_log('warning', 'wallet.inbox_projection.attribution_failed', 'Reward reached Inbox but attribution tracking failed.', ['exception_class'=>$trackingError::class], $userId);
        }

        return $bridge + [
            'wallet_status'=>(string)$item['status'],
            'redirect_url'=>'/inbox.php',
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'wallet.inbox_projection.failed', 'Unable to project wallet reward into Inbox.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $userId);
        mg_fail('Unable to deliver this reward to Inbox.', 500);
    }
}
