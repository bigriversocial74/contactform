<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm.php';
require_once __DIR__ . '/merchant-crm-reward-invites.php';
require_once dirname(__DIR__) . '/api/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__) . '/api/public/campaigns/_limits.php';
require_once dirname(__DIR__) . '/api/public/campaigns/_followups.php';

final class MgCrmCampaignSendException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}

function mg_crm_campaign_send_service_fail(string $message, int $status = 422, array $context = []): never
{
    throw new MgCrmCampaignSendException($message, $status, $context);
}

function mg_crm_campaign_send_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_crm_campaign_send_type_label(string $type): string
{
    return match ($type) {
        'customer_refund' => 'Customer Refund / Make Good',
        'referral_reward' => 'Referral Reward',
        'newsletter_signup' => 'Newsletter Signup',
        'contest_giveaway' => 'Contest / Giveaway',
        'qr_reward_drop' => 'QR Reward Drop',
        'birthday_vip' => 'Birthday / VIP',
        'agent_offer' => 'Agent Offer',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

function mg_crm_campaign_send_event_type(string $campaignType): string
{
    return $campaignType === 'customer_refund' ? 'crm.customer_refund.sent' : 'crm.campaign_reward.sent';
}

function mg_crm_campaign_send_expiry(array $campaign): ?string
{
    $rule = (string)($campaign['expiration_rule'] ?? 'none');
    if (($rule === 'fixed_date' || $rule === 'event_date') && !empty($campaign['expires_at'])) {
        return (string)$campaign['expires_at'];
    }
    if ($rule === 'after_issue' && !empty($campaign['expiration_days'])) {
        return date('Y-m-d H:i:s', time() + ((int)$campaign['expiration_days'] * 86400));
    }
    return null;
}

function mg_crm_campaign_send_find_user(PDO $pdo, string $email, ?int $sourceUserId): ?int
{
    if ($sourceUserId && $sourceUserId > 0) return $sourceUserId;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_crm_campaign_send_event(PDO $pdo, array $campaign, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_crm_campaign_send_uuid(),
        (int)$campaign['merchant_user_id'],
        (int)$campaign['id'],
        $walletItemId,
        $contactId,
        $eventType,
        json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    mg_campaign_followup_schedule($pdo, [
        'merchant_user_id' => (int)$campaign['merchant_user_id'],
        'campaign_id' => (int)$campaign['id'],
        'contact_id' => $contactId,
        'wallet_item_id' => $walletItemId,
        'trigger_event' => $eventType,
        'context' => $context,
    ]);
}

function mg_crm_campaign_send_bridge(PDO $pdo, array $campaign, array $contact, int $walletDbId, string $walletPublicId, int $userId, ?string $expiresAt, string $note): ?array
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
        'source_type' => 'crm_campaign_reward',
        'source_reference' => $walletPublicId,
        'source_line_reference' => (string)$contact['public_id'],
        'title' => (string)$campaign['reward_template_title'],
        'description' => $campaign['reward_template_description'] ?? null,
        'currency' => (string)($campaign['currency'] ?? 'USD'),
        'display_value_cents' => (int)($campaign['value_amount_cents'] ?? 0),
        'expires_at' => $expiresAt,
        'redemption_instructions' => $campaign['redemption_instructions'] ?? null,
        'terms' => ['campaign_type' => (string)$campaign['campaign_type'], 'note' => $note],
    ]);
}

function mg_crm_campaign_send_validate_input(array $input, int $merchantId): array
{
    $contactRef = strtolower(trim((string)($input['contact_id'] ?? $input['contact'] ?? '')));
    $campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
    $requiredType = strtolower(trim((string)($input['required_campaign_type'] ?? '')));
    $note = trim((string)($input['note'] ?? ''));
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    $allowedTypes = ['customer_refund', 'referral_reward', 'newsletter_signup', 'contest_giveaway', 'qr_reward_drop', 'birthday_vip', 'agent_offer'];
    if (preg_match('/^[a-f0-9-]{36}$/', $contactRef) !== 1 || preg_match('/^[a-f0-9-]{36}$/', $campaignRef) !== 1 || mb_strlen($note) > 1000) {
        mg_crm_campaign_send_service_fail('Invalid CRM campaign reward send request.', 422);
    }
    if ($requiredType !== '' && !in_array($requiredType, $allowedTypes, true)) {
        mg_crm_campaign_send_service_fail('Unsupported required campaign type.', 422);
    }
    if ($idempotencyKey === '') {
        $idempotencyKey = substr('crm-campaign-reward:' . hash('sha256', $merchantId . '|' . $contactRef . '|' . $campaignRef . '|' . $note), 0, 190);
    }
    if (strlen($idempotencyKey) > 190) mg_crm_campaign_send_service_fail('Idempotency key is too long.', 422);
    return [$contactRef, $campaignRef, $requiredType, $note, $idempotencyKey];
}

/**
 * Canonical direct Wallet / PPPM campaign reward execution.
 * The caller owns authentication and authorization; this function owns the transaction.
 */
function mg_crm_campaign_send_execute(PDO $pdo, int $merchantId, array $input): array
{
    [$sourceContactRef, $campaignRef, $requiredCampaignType, $note, $idem] = mg_crm_campaign_send_validate_input($input, $merchantId);
    $pdo->beginTransaction();
    try {
        $sourceStmt = $pdo->prepare('SELECT cc.*, c.public_id source_campaign_public_id, c.campaign_type source_campaign_type, c.title source_campaign_title FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1 FOR UPDATE');
        $sourceStmt->execute([$sourceContactRef, $merchantId]);
        $sourceContact = $sourceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sourceContact) mg_crm_campaign_send_service_fail('CRM contact not found.', 404);

        $email = strtolower(trim((string)($sourceContact['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) mg_crm_campaign_send_service_fail('Customer email is required before sending a campaign reward.', 422);
        $userId = mg_crm_campaign_send_find_user($pdo, $email, (int)($sourceContact['user_id'] ?? 0) ?: null);
        if (!$userId) mg_crm_campaign_send_service_fail('Customer account required before this reward can be placed into the Wallet / Inbox PPPM.', 409, ['requires_invite' => true]);

        $campaignSql = "SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title,
                rt.description reward_template_description, rt.redemption_instructions,
                rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at,
                rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count,
                rt.per_user_limit reward_template_per_user_limit, rt.status reward_template_status
            FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id
            WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1 FOR UPDATE";
        $campaignStmt = $pdo->prepare($campaignSql);
        $campaignStmt->execute([$campaignRef, $merchantId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_crm_campaign_send_service_fail('Eligible reward-backed campaign not found.', 404);

        $campaignType = (string)$campaign['campaign_type'];
        $campaignTypeLabel = mg_crm_campaign_send_type_label($campaignType);
        $crmEventType = mg_crm_campaign_send_event_type($campaignType);
        if ($requiredCampaignType !== '' && $campaignType !== $requiredCampaignType) {
            mg_crm_campaign_send_service_fail('Choose an active ' . mg_crm_campaign_send_type_label($requiredCampaignType) . ' campaign for this action.', 409);
        }
        if ((string)$campaign['status'] !== 'active' || (string)$campaign['reward_template_status'] !== 'active') {
            mg_crm_campaign_send_service_fail($campaignTypeLabel . ' campaign must be active with an active reward assigned.', 409);
        }
        $now = time();
        if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_crm_campaign_send_service_fail($campaignTypeLabel . ' campaign has not started yet.', 409);
        if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) mg_crm_campaign_send_service_fail($campaignTypeLabel . ' campaign has ended.', 409);
        if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) mg_crm_campaign_send_service_fail($campaignTypeLabel . ' campaign inventory is unavailable.', 409);
        if ($campaign['reward_template_quantity_limit'] !== null && (int)$campaign['reward_template_issued_count'] >= (int)$campaign['reward_template_quantity_limit']) mg_crm_campaign_send_service_fail('Assigned reward inventory is unavailable.', 409);

        $existing = $pdo->prepare("SELECT public_id FROM wallet_items WHERE merchant_user_id=? AND campaign_id=? AND source_type='manual_send' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.crm_idempotency_key'))=? LIMIT 1");
        $existing->execute([$merchantId, (int)$campaign['id'], $idem]);
        $existingWallet = (string)($existing->fetchColumn() ?: '');
        if ($existingWallet !== '') {
            $pdo->commit();
            return [
                'wallet_item_id' => $existingWallet,
                'duplicate' => true,
                'campaign_id' => (string)$campaign['public_id'],
                'campaign_title' => (string)$campaign['title'],
                'campaign_type' => $campaignType,
                'campaign_type_label' => $campaignTypeLabel,
                'reward_template_id' => (string)$campaign['reward_template_public_id'],
                'reward_template_title' => (string)$campaign['reward_template_title'],
                'customer_email' => $email,
                'customer_name' => (string)($sourceContact['name'] ?? ''),
                'wallet_status' => 'Already issued to Wallet / Inbox PPPM',
                'sent_at' => date('c'),
                'crm_event_type' => $crmEventType,
            ];
        }

        $targetLookup = $pdo->prepare('SELECT * FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1 FOR UPDATE');
        $targetLookup->execute([(int)$campaign['id'], $email]);
        $targetContact = $targetLookup->fetch(PDO::FETCH_ASSOC);
        if (!$targetContact) {
            $targetPublicId = mg_crm_campaign_send_uuid();
            $metadata = [
                'campaign_type' => $campaignType,
                'source_contact_id' => (string)$sourceContact['public_id'],
                'source_campaign_id' => (string)$sourceContact['source_campaign_public_id'],
                'source_campaign_type' => (string)$sourceContact['source_campaign_type'],
                'crm_manual_send' => true,
            ];
            $stmt = $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->execute([$targetPublicId, $merchantId, (int)$campaign['id'], $userId, $email, $sourceContact['phone'] ?? null, $sourceContact['name'] ?? null, 'manual', 'opted_in', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $targetLookup->execute([(int)$campaign['id'], $email]);
            $targetContact = $targetLookup->fetch(PDO::FETCH_ASSOC);
        } else {
            $pdo->prepare("UPDATE campaign_contacts SET user_id=?, phone=COALESCE(NULLIF(phone,''),?), name=COALESCE(NULLIF(name,''),?), opt_in_status=IFNULL(NULLIF(opt_in_status,''),'opted_in'), updated_at=NOW() WHERE id=?")
                ->execute([$userId, $sourceContact['phone'] ?? null, $sourceContact['name'] ?? null, (int)$targetContact['id']]);
            $targetLookup->execute([(int)$campaign['id'], $email]);
            $targetContact = $targetLookup->fetch(PDO::FETCH_ASSOC);
        }
        if (!$targetContact) mg_crm_campaign_send_service_fail('Campaign contact could not be prepared.', 500);

        mg_public_campaign_enforce_reward_limits($pdo, $campaign, $userId, $email);
        $expiresAt = mg_crm_campaign_send_expiry($campaign);
        $walletPublicId = mg_crm_campaign_send_uuid();
        $stampLedger = mg_public_campaign_debit_reward_stamp($pdo, $campaign, $walletPublicId, 'crm_campaign_reward', [
            'contact_id' => (string)$targetContact['public_id'],
            'source_contact_id' => (string)$sourceContact['public_id'],
            'email' => $email,
        ]);
        $sentAt = date('c');
        $walletStatus = 'Issued to Wallet / Inbox PPPM';
        $walletMetadata = [
            'campaign_type' => $campaignType,
            'crm_event_type' => $crmEventType,
            'crm_send_campaign_id' => (string)$campaign['public_id'],
            'crm_source_contact_id' => (string)$sourceContact['public_id'],
            'source_campaign_id' => (string)$sourceContact['source_campaign_public_id'],
            'source_campaign_type' => (string)$sourceContact['source_campaign_type'],
            'reward_template_id' => (string)$campaign['reward_template_public_id'],
            'note' => $note,
            'crm_idempotency_key' => $idem,
            'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
        ];
        $walletStmt = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
        $walletStmt->execute([$walletPublicId, $userId, (int)$targetContact['id'], $merchantId, (int)$campaign['reward_template_db_id'], (int)$campaign['id'], 'manual_send', (string)$targetContact['public_id'], 'issued', (int)$campaign['value_amount_cents'], (string)$campaign['currency'], (string)$campaign['reward_template_title'], json_encode($walletMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
        $walletDbId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+1, updated_at=NOW() WHERE id=?')->execute([(int)$campaign['id']]);
        $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1, updated_at=NOW() WHERE id=?')->execute([(int)$campaign['reward_template_db_id']]);

        $bridge = mg_crm_campaign_send_bridge($pdo, $campaign, $targetContact, $walletDbId, $walletPublicId, $userId, $expiresAt, $note);
        $eventContext = [
            'wallet_item_id' => $walletPublicId,
            'wallet_status' => $walletStatus,
            'campaign_type' => $campaignType,
            'campaign_type_label' => $campaignTypeLabel,
            'campaign_title' => (string)$campaign['title'],
            'reward_template_id' => (string)$campaign['reward_template_public_id'],
            'reward_template_title' => (string)$campaign['reward_template_title'],
            'customer_email' => $email,
            'customer_name' => (string)($targetContact['name'] ?? $sourceContact['name'] ?? ''),
            'sent_at' => $sentAt,
            'timeline_label' => $campaignType === 'customer_refund' ? 'Customer Refund / Make Good sent' : $campaignTypeLabel . ' reward sent',
            'crm_event_type' => $crmEventType,
            'target_campaign_id' => (string)$campaign['public_id'],
            'target_contact_id' => (string)$targetContact['public_id'],
            'source_contact_id' => (string)$sourceContact['public_id'],
            'pppm_bridge' => $bridge,
            'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
            'note' => $note,
        ];
        mg_crm_campaign_send_event($pdo, $campaign, $walletDbId, (int)$targetContact['id'], 'wallet_item.issued', $eventContext);
        mg_crm_campaign_send_event($pdo, $campaign, $walletDbId, (int)$targetContact['id'], $crmEventType, $eventContext);
        mg_crm_campaign_send_event($pdo, ['id' => (int)$sourceContact['campaign_id'], 'merchant_user_id' => $merchantId], $walletDbId, (int)$sourceContact['id'], $crmEventType, $eventContext);
        mg_merchant_crm_record_event($pdo, [
            'merchant_user_id' => $merchantId,
            'campaign_id' => (int)$campaign['id'],
            'campaign_type' => $campaignType,
            'event_type' => $crmEventType,
            'source_type' => 'crm_campaign_reward',
            'source_public_id' => (string)$targetContact['public_id'],
            'user_id' => $userId,
            'email' => $email,
            'name' => (string)($targetContact['name'] ?? ''),
            'value_cents' => (int)$campaign['value_amount_cents'],
            'metadata' => ['wallet_item_id' => $walletPublicId, 'wallet_status' => $walletStatus, 'campaign_title' => (string)$campaign['title'], 'reward_template_title' => (string)$campaign['reward_template_title'], 'source_contact_id' => (string)$sourceContact['public_id'], 'reward_template_id' => (string)$campaign['reward_template_public_id'], 'campaign_type_label' => $campaignTypeLabel, 'crm_event_type' => $crmEventType],
        ]);
        $pdo->commit();
        return [
            'contact_id' => (string)$targetContact['public_id'],
            'source_contact_id' => (string)$sourceContact['public_id'],
            'campaign_id' => (string)$campaign['public_id'],
            'campaign_title' => (string)$campaign['title'],
            'campaign_type' => $campaignType,
            'campaign_type_label' => $campaignTypeLabel,
            'reward_template_id' => (string)$campaign['reward_template_public_id'],
            'reward_template_title' => (string)$campaign['reward_template_title'],
            'customer_email' => $email,
            'customer_name' => (string)($targetContact['name'] ?? $sourceContact['name'] ?? ''),
            'wallet_item_id' => $walletPublicId,
            'wallet_status' => $walletStatus,
            'sent_at' => $sentAt,
            'crm_event_type' => $crmEventType,
            'expires_at' => $expiresAt,
            'pppm_bridge' => $bridge,
            'stamp_ledger' => $stampLedger,
            'duplicate' => false,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_crm_campaign_send_contact_has_account(PDO $pdo, int $merchantId, string $contactRef): bool
{
    $stmt = $pdo->prepare('SELECT user_id,email FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$contactRef, $merchantId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) mg_crm_campaign_send_service_fail('CRM contact not found.', 404);
    return mg_crm_campaign_send_find_user($pdo, strtolower(trim((string)($contact['email'] ?? ''))), (int)($contact['user_id'] ?? 0) ?: null) !== null;
}

/**
 * Canonical non-account invite path. This preserves package limits, inventory,
 * email delivery, CRM events, idempotency, and existing account invitation flow.
 */
function mg_crm_campaign_invite_execute(PDO $pdo, int $merchantId, array $merchantUser, array $input): array
{
    $contactRef = strtolower(trim((string)($input['contact_id'] ?? '')));
    $templateRef = strtolower(trim((string)($input['reward_template_id'] ?? '')));
    $campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
    $requiredType = strtolower(trim((string)($input['required_campaign_type'] ?? '')));
    $note = trim((string)($input['note'] ?? ''));
    $idem = trim((string)($input['idempotency_key'] ?? ''));
    if (preg_match('/^[a-f0-9-]{36}$/', $contactRef) !== 1 || preg_match('/^[a-f0-9-]{36}$/', $templateRef) !== 1 || preg_match('/^[a-f0-9-]{36}$/', $campaignRef) !== 1 || mb_strlen($note) > 1000) {
        mg_crm_campaign_send_service_fail('Invalid CRM reward invite request.', 422);
    }
    if ($idem === '') $idem = substr('crm-reward-invite:' . hash('sha256', $merchantId . '|' . $contactRef . '|' . $templateRef . '|' . $campaignRef), 0, 190);
    if (!mg_crm_reward_invites_ready($pdo)) mg_crm_campaign_send_service_fail('CRM reward invite schema is not installed.', 503);
    mg_delivery_install_schema($pdo);
    $pdo->beginTransaction();
    try {
        if (!mg_package_limit_value(mg_user_package_context($pdo, $merchantUser), 'email_stamps_enabled')) {
            mg_crm_campaign_send_service_fail('Email Stamps are not enabled for this package.', 402);
        }
        $stmt = $pdo->prepare('SELECT cc.*,c.public_id campaign_public_id,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$contactRef, $merchantId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) mg_crm_campaign_send_service_fail('CRM contact not found.', 404);
        if ((int)($contact['user_id'] ?? 0) > 0) mg_crm_campaign_send_service_fail('This contact already has an account. Use direct reward send.', 409);
        $email = strtolower(trim((string)($contact['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) mg_crm_campaign_send_service_fail('A valid contact email is required for reward invites.', 422);

        $templateStmt = $pdo->prepare("SELECT * FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $templateStmt->execute([$templateRef, $merchantId]);
        $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) mg_crm_campaign_send_service_fail('Active reward template not found.', 404);
        $campaignStmt = $pdo->prepare('SELECT * FROM campaigns WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
        $campaignStmt->execute([$campaignRef, $merchantId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_crm_campaign_send_service_fail('Selected campaign not found.', 404);
        if ((string)$campaign['status'] !== 'active') mg_crm_campaign_send_service_fail('Selected campaign must be active.', 409);
        if ((int)($campaign['reward_template_id'] ?? 0) !== (int)$template['id']) mg_crm_campaign_send_service_fail('Selected campaign reward does not match this invite.', 409);
        if ($requiredType !== '' && (string)$campaign['campaign_type'] !== $requiredType) mg_crm_campaign_send_service_fail('Selected campaign type is not authorized for this action.', 409);
        $now = time();
        if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_crm_campaign_send_service_fail('Selected campaign has not started yet.', 409);
        if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) mg_crm_campaign_send_service_fail('Selected campaign has ended.', 409);

        $campaignPendingStmt = $pdo->prepare("SELECT COUNT(*) FROM crm_reward_invites WHERE campaign_id=? AND status='sent' AND (expires_at IS NULL OR expires_at>NOW())");
        $campaignPendingStmt->execute([(int)$campaign['id']]);
        if ($campaign['quantity_limit'] !== null && ((int)$campaign['issued_count'] + (int)$campaignPendingStmt->fetchColumn()) >= (int)$campaign['quantity_limit']) mg_crm_campaign_send_service_fail('Selected campaign inventory is unavailable.', 409);
        $pending = $pdo->prepare("SELECT COUNT(*) FROM crm_reward_invites WHERE reward_template_id=? AND status='sent' AND (expires_at IS NULL OR expires_at>NOW())");
        $pending->execute([(int)$template['id']]);
        if ($template['quantity_limit'] !== null && ((int)$template['issued_count'] + (int)$pending->fetchColumn()) >= (int)$template['quantity_limit']) mg_crm_campaign_send_service_fail('Reward template limit has been reached.', 409);
        $existing = $pdo->prepare('SELECT public_id FROM crm_reward_invites WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1');
        $existing->execute([$merchantId, $idem]);
        $old = (string)($existing->fetchColumn() ?: '');
        if ($old !== '') {
            $pdo->commit();
            return ['invite_id' => $old, 'duplicate' => true, 'campaign_id' => (string)$campaign['public_id'], 'campaign_type' => (string)$campaign['campaign_type']];
        }
        $active = $pdo->prepare("SELECT public_id FROM crm_reward_invites WHERE merchant_user_id=? AND contact_id=? AND reward_template_id=? AND status='sent' AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1");
        $active->execute([$merchantId, (int)$contact['id'], (int)$template['id']]);
        $activeId = (string)($active->fetchColumn() ?: '');
        if ($activeId !== '') mg_crm_campaign_send_service_fail('This reward invite is already waiting for this contact.', 409, ['invite_id' => $activeId]);

        $inviteId = mg_crm_reward_invite_uuid();
        $expiresAt = date('Y-m-d H:i:s', time() + 1209600);
        $inviteUrl = mg_app_base_url() . '/signup.php?crm_reward_invite=' . rawurlencode($inviteId);
        $meta = ['campaign_type' => (string)$campaign['campaign_type'], 'campaign_id' => (string)$campaign['public_id'], 'contact_id' => (string)$contact['public_id'], 'reward_template_id' => (string)$template['public_id'], 'required_campaign_type' => $requiredType ?: null];
        $insert = $pdo->prepare('INSERT INTO crm_reward_invites (public_id,merchant_user_id,campaign_id,contact_id,reward_template_id,email,name,status,note,idempotency_key,invite_url,sent_at,expires_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $insert->execute([$inviteId, $merchantId, (int)$campaign['id'], (int)$contact['id'], (int)$template['id'], $email, (string)($contact['name'] ?? ''), 'sent', $note, $idem, $inviteUrl, date('Y-m-d H:i:s'), $expiresAt, json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $eventContact = $contact;
        $eventContact['campaign_id'] = (int)$campaign['id'];
        $eventContact['contact_id'] = (int)$contact['id'];
        $eventContact['campaign_public_id'] = (string)$campaign['public_id'];
        $eventContact['campaign_type'] = (string)$campaign['campaign_type'];
        try {
            $delivery = mg_delivery_enqueue($pdo, [
                'idempotency_key' => 'crm-reward-invite-email:' . $idem,
                'event_type' => 'campaign.outbound_email',
                'category' => 'campaign',
                'channel' => 'email',
                'template_key' => 'campaign.crm_reward_invite',
                'recipient_snapshot' => ['email' => $email, 'name' => (string)($contact['name'] ?? '')],
                'payload' => mg_crm_reward_invite_email_payload($eventContact, $template, $inviteId, $inviteUrl, $note, $expiresAt),
                'max_attempts' => 3,
            ]);
        } catch (Throwable) {
            $delivery = ['queued' => false];
        }
        mg_crm_reward_invite_record_campaign_event($pdo, $eventContact, null, 'crm.reward_invite.sent', ['invite_id' => $inviteId, 'campaign_id' => (string)$campaign['public_id'], 'campaign_type' => (string)$campaign['campaign_type'], 'reward_template_id' => (string)$template['public_id'], 'email_delivery' => $delivery]);
        mg_merchant_crm_record_event($pdo, ['merchant_user_id' => $merchantId, 'campaign_id' => (int)$campaign['id'], 'campaign_type' => (string)$campaign['campaign_type'], 'event_type' => 'crm.reward_invite.sent', 'source_type' => 'merchant_crm_reward_invite', 'source_public_id' => (string)$contact['public_id'], 'email' => $email, 'name' => (string)($contact['name'] ?? ''), 'value_cents' => (int)$template['value_amount_cents'], 'metadata' => ['invite_id' => $inviteId, 'campaign_id' => (string)$campaign['public_id'], 'reward_template_id' => (string)$template['public_id'], 'delivery' => $delivery]]);
        $pdo->commit();
        return ['invite_id' => $inviteId, 'invite_url' => $inviteUrl, 'campaign_id' => (string)$campaign['public_id'], 'campaign_title' => (string)$campaign['title'], 'campaign_type' => (string)$campaign['campaign_type'], 'email_delivery' => $delivery, 'expires_at' => $expiresAt, 'duplicate' => false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_crm_campaign_send_for_contact(PDO $pdo, int $merchantId, array $merchantUser, array $input): array
{
    $contactRef = strtolower(trim((string)($input['contact_id'] ?? $input['contact'] ?? '')));
    if (mg_crm_campaign_send_contact_has_account($pdo, $merchantId, $contactRef)) {
        return ['delivery_mode' => 'wallet_pppm', 'result' => mg_crm_campaign_send_execute($pdo, $merchantId, $input)];
    }
    if (empty($input['reward_template_id'])) {
        $stmt = $pdo->prepare('SELECT rt.public_id FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1');
        $stmt->execute([(string)($input['campaign_id'] ?? ''), $merchantId]);
        $input['reward_template_id'] = (string)($stmt->fetchColumn() ?: '');
    }
    return ['delivery_mode' => 'account_invite', 'result' => mg_crm_campaign_invite_execute($pdo, $merchantId, $merchantUser, $input)];
}
