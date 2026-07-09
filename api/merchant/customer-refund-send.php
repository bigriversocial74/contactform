<?php

declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__) . '/public/campaigns/_limits.php';
require_once dirname(__DIR__) . '/public/campaigns/_followups.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

function mg_customer_refund_send_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_customer_refund_send_expiry(array $campaign): ?string
{
    $rule = (string)($campaign['expiration_rule'] ?? 'none');
    if (($rule === 'fixed_date' || $rule === 'event_date') && !empty($campaign['expires_at'])) return (string)$campaign['expires_at'];
    if ($rule === 'after_issue' && !empty($campaign['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int)$campaign['expiration_days'] * 86400));
    return null;
}

function mg_customer_refund_send_find_user(PDO $pdo, string $email, ?int $sourceUserId): ?int
{
    if ($sourceUserId && $sourceUserId > 0) return $sourceUserId;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_customer_refund_send_event(PDO $pdo, array $campaign, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_customer_refund_send_uuid(), (int)$campaign['merchant_user_id'], (int)$campaign['id'], $walletItemId, $contactId, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    mg_campaign_followup_schedule($pdo, ['merchant_user_id' => (int)$campaign['merchant_user_id'], 'campaign_id' => (int)$campaign['id'], 'contact_id' => $contactId, 'wallet_item_id' => $walletItemId, 'trigger_event' => $eventType, 'context' => $context]);
}

function mg_customer_refund_send_bridge(PDO $pdo, array $campaign, array $contact, int $walletDbId, string $walletPublicId, int $userId, ?string $expiresAt, string $note): ?array
{
    return mg_zero_reward_issue_from_wallet($pdo, [
        'merchant_user_id' => (int)$campaign['merchant_user_id'],
        'recipient_user_id' => $userId,
        'recipient_external_id' => (string)$contact['public_id'],
        'recipient_name' => (string)($contact['name'] ?? ''),
        'wallet_item_db_id' => $walletDbId,
        'wallet_item_public_id' => $walletPublicId,
        'campaign_public_id' => (string)$campaign['public_id'],
        'reward_template_public_id' => (string)$campaign['reward_template_public_id'],
        'source_type' => 'customer_refund',
        'source_reference' => $walletPublicId,
        'source_line_reference' => (string)$contact['public_id'],
        'title' => (string)$campaign['reward_template_title'],
        'description' => $campaign['reward_template_description'] ?? null,
        'currency' => (string)($campaign['currency'] ?? 'USD'),
        'display_value_cents' => (int)($campaign['value_amount_cents'] ?? 0),
        'expires_at' => $expiresAt,
        'redemption_instructions' => $campaign['redemption_instructions'] ?? null,
        'terms' => ['campaign_type' => 'customer_refund', 'note' => $note],
    ]);
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$sourceContactRef = strtolower(trim((string)($input['contact_id'] ?? $input['contact'] ?? '')));
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
$note = trim((string)($input['note'] ?? ''));
$idem = trim((string)($input['idempotency_key'] ?? ''));
if ($sourceContactRef === '' || strlen($sourceContactRef) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $sourceContactRef) || $campaignRef === '' || strlen($campaignRef) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignRef) || mb_strlen($note) > 1000) {
    mg_fail('Invalid Customer Refund send request.', 422);
}
if ($idem === '') $idem = substr('customer-refund:' . hash('sha256', $merchantId . '|' . $sourceContactRef . '|' . $campaignRef . '|' . $note . '|' . microtime(true)), 0, 190);

try {
    $pdo->beginTransaction();

    $sourceStmt = $pdo->prepare('SELECT cc.*, c.public_id source_campaign_public_id, c.campaign_type source_campaign_type, c.title source_campaign_title FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1 FOR UPDATE');
    $sourceStmt->execute([$sourceContactRef, $merchantId]);
    $sourceContact = $sourceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sourceContact) {
        $pdo->rollBack();
        mg_fail('CRM contact not found.', 404);
    }

    $email = strtolower(trim((string)($sourceContact['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pdo->rollBack();
        mg_fail('Customer email is required before sending a Customer Refund voucher.', 422);
    }
    $userId = mg_customer_refund_send_find_user($pdo, $email, (int)($sourceContact['user_id'] ?? 0) ?: null);
    if (!$userId) {
        $pdo->rollBack();
        mg_fail('Customer account required before this voucher can be placed into wallet.php.', 409);
    }

    $campaignSql = 'SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title,
            rt.description reward_template_description, rt.redemption_instructions,
            rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at,
            rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count, rt.per_user_limit reward_template_per_user_limit, rt.status reward_template_status
        FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id
        WHERE c.public_id=? AND c.merchant_user_id=? AND c.campaign_type=\'customer_refund\' LIMIT 1 FOR UPDATE';
    $campaignStmt = $pdo->prepare($campaignSql);
    $campaignStmt->execute([$campaignRef, $merchantId]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('Customer Refund campaign not found.', 404);
    }
    if ((string)$campaign['status'] !== 'active' || (string)$campaign['reward_template_status'] !== 'active') {
        $pdo->rollBack();
        mg_fail('Customer Refund campaign must be active with an active reward assigned.', 409);
    }
    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) {
        $pdo->rollBack();
        mg_fail('Customer Refund campaign has not started yet.', 409);
    }
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) {
        $pdo->rollBack();
        mg_fail('Customer Refund campaign has ended.', 409);
    }
    if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Customer Refund campaign inventory is unavailable.', 409);
    }
    if ($campaign['reward_template_quantity_limit'] !== null && (int)$campaign['reward_template_issued_count'] >= (int)$campaign['reward_template_quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Assigned reward inventory is unavailable.', 409);
    }

    $existing = $pdo->prepare("SELECT public_id FROM wallet_items WHERE merchant_user_id=? AND campaign_id=? AND source_type='customer_refund' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.crm_idempotency_key'))=? LIMIT 1");
    $existing->execute([$merchantId, (int)$campaign['id'], $idem]);
    $existingWallet = (string)($existing->fetchColumn() ?: '');
    if ($existingWallet !== '') {
        $pdo->commit();
        mg_ok(['wallet_item_id' => $existingWallet, 'duplicate' => true], 'Customer Refund voucher already issued.');
    }

    $contactPublicId = mg_customer_refund_send_uuid();
    $contactMetadata = [
        'campaign_type' => 'customer_refund',
        'source_contact_id' => (string)$sourceContact['public_id'],
        'source_campaign_id' => (string)$sourceContact['source_campaign_public_id'],
        'source_campaign_type' => (string)$sourceContact['source_campaign_type'],
    ];
    $contactStmt = $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), source=VALUES(source), opt_in_status=VALUES(opt_in_status), metadata_json=VALUES(metadata_json), updated_at=NOW()");
    $contactStmt->execute([$contactPublicId, $merchantId, (int)$campaign['id'], $userId, $email, $sourceContact['phone'] ?? null, $sourceContact['name'] ?? null, 'customer_refund', 'opted_in', json_encode($contactMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $refundContactLookup = $pdo->prepare('SELECT * FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1 FOR UPDATE');
    $refundContactLookup->execute([(int)$campaign['id'], $email]);
    $refundContact = $refundContactLookup->fetch(PDO::FETCH_ASSOC);
    if (!$refundContact) {
        $pdo->rollBack();
        mg_fail('Customer Refund contact could not be prepared.', 500);
    }

    mg_public_campaign_enforce_reward_limits($pdo, $campaign, $userId, $email);

    $expiresAt = mg_customer_refund_send_expiry($campaign);
    $walletPublicId = mg_customer_refund_send_uuid();
    $stampLedger = mg_public_campaign_debit_reward_stamp($pdo, $campaign, $walletPublicId, 'customer_refund', [
        'contact_id' => (string)$refundContact['public_id'],
        'source_contact_id' => (string)$sourceContact['public_id'],
        'email' => $email,
    ]);
    $walletMetadata = [
        'campaign_type' => 'customer_refund',
        'customer_refund_campaign_id' => (string)$campaign['public_id'],
        'crm_source_contact_id' => (string)$sourceContact['public_id'],
        'reward_template_id' => (string)$campaign['reward_template_public_id'],
        'note' => $note,
        'crm_idempotency_key' => $idem,
        'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
    ];
    $walletStmt = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $walletStmt->execute([$walletPublicId, $userId, (int)$refundContact['id'], $merchantId, (int)$campaign['reward_template_db_id'], (int)$campaign['id'], 'customer_refund', (string)$refundContact['public_id'], 'issued', (int)$campaign['value_amount_cents'], (string)$campaign['currency'], (string)$campaign['reward_template_title'], json_encode($walletMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
    $walletDbId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+1, updated_at=NOW() WHERE id=?')->execute([(int)$campaign['id']]);
    $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1, updated_at=NOW() WHERE id=?')->execute([(int)$campaign['reward_template_db_id']]);

    $bridge = mg_customer_refund_send_bridge($pdo, $campaign, $refundContact, $walletDbId, $walletPublicId, $userId, $expiresAt, $note);
    mg_customer_refund_send_event($pdo, $campaign, $walletDbId, (int)$refundContact['id'], 'wallet_item.issued', ['wallet_item_id' => $walletPublicId, 'campaign_type' => 'customer_refund', 'source_type' => 'customer_refund', 'source_contact_id' => (string)$sourceContact['public_id'], 'pppm_bridge' => $bridge, 'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null]);
    mg_customer_refund_send_event($pdo, $campaign, $walletDbId, (int)$refundContact['id'], 'crm.customer_refund.sent', ['wallet_item_id' => $walletPublicId, 'source_type' => 'customer_refund', 'source_contact_id' => (string)$sourceContact['public_id'], 'note' => $note]);
    mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => (int)$campaign['id'],
        'campaign_type' => 'customer_refund',
        'event_type' => 'crm.customer_refund.sent',
        'source_type' => 'customer_refund',
        'source_public_id' => (string)$refundContact['public_id'],
        'user_id' => $userId,
        'email' => $email,
        'name' => (string)($refundContact['name'] ?? ''),
        'value_cents' => (int)$campaign['value_amount_cents'],
        'metadata' => [
            'wallet_item_id' => $walletPublicId,
            'customer_refund_campaign_id' => (string)$campaign['public_id'],
            'source_contact_id' => (string)$sourceContact['public_id'],
            'reward_template_id' => (string)$campaign['reward_template_public_id'],
            'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
        ],
    ]);

    $pdo->commit();
    mg_ok(['contact_id' => (string)$refundContact['public_id'], 'source_contact_id' => (string)$sourceContact['public_id'], 'campaign_id' => (string)$campaign['public_id'], 'campaign_type' => 'customer_refund', 'source_type' => 'customer_refund', 'wallet_item_id' => $walletPublicId, 'expires_at' => $expiresAt, 'pppm_bridge' => $bridge, 'stamp_ledger' => $stampLedger, 'duplicate' => false], 'Customer Refund voucher issued.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.customer_refund_send.failed', 'Unable to send Customer Refund voucher.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to send Customer Refund voucher.', 500);
}
