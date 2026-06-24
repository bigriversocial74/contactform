<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/stamps/_stamps.php';

function mg_campaign_send_action_key(string $channel): string
{
    return match ($channel) {
        'feed' => 'campaign_feed_send',
        'email' => 'email_list_send',
        'sms' => 'sms_send',
        'qr' => 'qr_claim_prompt_send',
        'agent' => 'agentic_discovery_send',
        default => '',
    };
}

function mg_campaign_send_label(string $channel): string
{
    return match ($channel) {
        'feed' => 'Feed campaign send',
        'email' => 'Email list campaign send',
        'sms' => 'SMS campaign send',
        'qr' => 'QR claim prompt send',
        'agent' => 'Agentic discovery campaign send',
        default => 'Campaign send',
    };
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$campaignPublicId = strtolower(trim((string) ($input['campaign_id'] ?? '')));
$channel = strtolower(trim((string) ($input['channel'] ?? 'feed')));
$quantity = max(1, (int) ($input['quantity'] ?? $input['recipient_count'] ?? 1));
$idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
$reference = trim((string) ($input['reference'] ?? ''));
$note = trim((string) ($input['note'] ?? ''));

$actionKey = mg_campaign_send_action_key($channel);
if ($actionKey === '' || $idempotencyKey === '') {
    mg_fail('Campaign send channel and idempotency key are required.', 422);
}
if ($campaignPublicId !== '' && (strlen($campaignPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignPublicId))) {
    mg_fail('Invalid campaign.', 422);
}
if ($quantity > 100000) {
    mg_fail('Recipient count is too large for one send batch.', 422);
}

try {
    $pdo->beginTransaction();
    $campaign = null;
    if ($campaignPublicId !== '') {
        $stmt = $pdo->prepare('SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title FROM campaigns c LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id WHERE c.public_id = ? AND c.merchant_user_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$campaignPublicId, $merchantId]);
        $campaign = $stmt->fetch();
        if (!$campaign) {
            throw new RuntimeException('Campaign not found.');
        }
        if (!in_array((string) $campaign['status'], ['active','draft'], true)) {
            throw new RuntimeException('Campaign must be active or draft to record this send.');
        }
    }

    $sendPublicId = mg_merchant_uuid();
    $stampLedger = mg_stamp_debit_send($pdo, $merchantId, $merchantId, $actionKey, $idempotencyKey, [
        'quantity' => $quantity,
        'source_type' => 'merchant_campaign_send',
        'source_id' => $sendPublicId,
        'reference' => $reference !== '' ? $reference : ($campaignPublicId !== '' ? $campaignPublicId : $channel),
        'metadata' => [
            'campaign_id' => $campaignPublicId !== '' ? $campaignPublicId : null,
            'channel' => $channel,
            'recipient_count' => $quantity,
            'label' => mg_campaign_send_label($channel),
        ],
    ]);

    if ($campaign) {
        $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $event->execute([
            $sendPublicId,
            $merchantId,
            (int) $campaign['id'],
            null,
            null,
            'campaign.send.' . $channel,
            json_encode([
                'channel' => $channel,
                'action_key' => $actionKey,
                'recipient_count' => $quantity,
                'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
                'stamp_delta' => $stampLedger['entry']['delta'] ?? null,
                'reference' => $reference !== '' ? $reference : null,
                'note' => $note !== '' ? $note : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    $pdo->commit();
    mg_audit('merchant.campaign_send_stamped', 'campaign', [
        'campaign_id' => $campaignPublicId ?: null,
        'send_id' => $sendPublicId,
        'channel' => $channel,
        'action_key' => $actionKey,
        'recipient_count' => $quantity,
        'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
    ], $merchantId);

    mg_ok([
        'send_id' => $sendPublicId,
        'campaign_id' => $campaignPublicId ?: null,
        'channel' => $channel,
        'action_key' => $actionKey,
        'recipient_count' => $quantity,
        'stamp_ledger' => $stampLedger,
    ], 'Campaign send Stamps debited.', 201);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.campaign_send.failed', 'Unable to debit campaign send Stamps.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to debit campaign send Stamps.', 500);
}
