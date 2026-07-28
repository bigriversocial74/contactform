#!/usr/bin/env python3
from pathlib import Path

path = Path("api/homeserver/_operational_intelligence.php")
text = path.read_text(encoding="utf-8")

include_anchor = "require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php';\n"
include_replacement = include_anchor + "require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';\n"
if "includes/campaign-types.php" not in text:
    if text.count(include_anchor) != 1:
        raise SystemExit("campaign type include anchor was not found exactly once")
    text = text.replace(include_anchor, include_replacement, 1)

helper_anchor = "function mg_homeserver_campaign_authorization(PDO $pdo, array $device, string $campaignType): array\n"
helpers = r'''function mg_homeserver_campaign_slug(string $title): string
{
    $slug = strtolower(trim((string)(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '')));
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'campaign', 0, 120);
}

function mg_homeserver_campaign_unique_slug(PDO $pdo, int $merchantId, string $title, string $excludePublicId = ''): string
{
    $base = mg_homeserver_campaign_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND public_slug=? AND public_id<>?');
    while (true) {
        $stmt->execute([$merchantId, $candidate, $excludePublicId]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string)$suffix) - 1)) . '-' . $suffix;
    }
}

function mg_homeserver_campaign_reward(PDO $pdo, int $merchantId, string $publicId): ?array
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (!mg_homeserver_is_uuid($publicId)) mg_fail('Reward template identity is invalid.', 422);
    $stmt = $pdo->prepare("SELECT id,public_id,title,status,value_amount_cents,currency FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]);
    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reward) mg_fail('Merchant reward template was not found.', 404);
    return $reward;
}

function mg_homeserver_campaign_save_draft(PDO $pdo, int $merchantId, string $campaignType, string $campaignId, array $input): array
{
    if (!mg_campaign_type_is_valid($campaignType, true)) mg_fail('Campaign type is not supported.', 422);
    $existing = null;
    if ($campaignId !== '') {
        $stmt = $pdo->prepare('SELECT c.*,rt.public_id reward_template_public_id,rt.status reward_template_status,rt.value_amount_cents FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1');
        $stmt->execute([$campaignId, $merchantId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) mg_fail('Merchant campaign was not found.', 404);
        if (!in_array((string)$existing['status'], ['draft', 'paused'], true)) mg_fail('Only draft or paused campaigns may be revised through a draft action.', 409);
        if ((string)$existing['campaign_type'] !== $campaignType) mg_fail('Campaign type does not match the merchant authorization.', 409);
    }

    $title = trim((string)($input['title'] ?? $existing['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 180) mg_fail('A campaign draft title is required.', 422);
    $description = trim((string)($input['description'] ?? $input['message'] ?? $existing['description'] ?? ''));
    $description = $description !== '' ? mb_substr($description, 0, 4000) : null;
    $rewardPublicId = strtolower(trim((string)($input['reward_template_id'] ?? $existing['reward_template_public_id'] ?? '')));
    $reward = mg_homeserver_campaign_reward($pdo, $merchantId, $rewardPublicId);
    $rewardId = $reward ? (int)$reward['id'] : null;
    $valueCents = $reward ? max(0, (int)$reward['value_amount_cents']) : 0;
    $quantityRaw = trim((string)($input['quantity_limit'] ?? $existing['quantity_limit'] ?? ''));
    $quantityLimit = $quantityRaw === '' ? null : max(1, min(1000000, (int)$quantityRaw));
    $perUserLimit = max(1, min(1000, (int)($input['per_user_limit'] ?? $existing['per_user_limit'] ?? 1)));
    $agentDiscoverable = !empty($input['agent_discoverable']) ? 1 : 0;
    $existingRules = json_decode((string)($existing['rules_json'] ?? '[]'), true);
    if (!is_array($existingRules)) $existingRules = [];
    $rules = array_replace($existingRules, [
        'campaign_type' => $campaignType,
        'version' => max(2, (int)($existingRules['version'] ?? 2)),
        'registry' => 'homeserver_agent_campaign_draft',
        'homeserver_agent' => [
            'recommendation_id' => trim((string)($input['recommendation_id'] ?? '')) ?: null,
            'message_intent' => mb_substr(trim((string)($input['message'] ?? '')), 0, 1000),
            'created_from_authorized_plan' => true,
        ],
    ]);
    if ($campaignType === 'customer_refund') {
        $rules = array_replace($rules, ['mode' => 'merchant_initiated', 'internal_only' => true, 'entry_reward_enabled' => true]);
    }
    $rulesJson = mg_homeserver_json($rules);
    $publicSlug = mg_campaign_type_public_enabled($campaignType)
        ? mg_homeserver_campaign_unique_slug($pdo, $merchantId, $title, $campaignId)
        : null;

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE campaigns SET reward_template_id=?,title=?,description=?,status='draft',quantity_limit=?,per_user_limit=?,agent_discoverable=?,public_slug=?,rules_json=?,updated_at=UTC_TIMESTAMP() WHERE public_id=? AND merchant_user_id=?");
        $stmt->execute([$rewardId, $title, $description, $quantityLimit, $perUserLimit, $agentDiscoverable, $publicSlug, $rulesJson, $campaignId, $merchantId]);
    } else {
        $campaignId = mg_homeserver_public_uuid();
        $qrToken = $campaignType === 'qr_reward_drop' ? bin2hex(random_bytes(16)) : null;
        $stmt = $pdo->prepare("INSERT INTO campaigns (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,status,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,rules_json,created_at,updated_at) VALUES (?,?,?,?,?,?,'draft',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([$campaignId, $merchantId, $rewardId, $campaignType, $title, $description, $quantityLimit, $perUserLimit, $agentDiscoverable, $publicSlug, $qrToken, $rulesJson]);
    }

    if (function_exists('mg_audit')) {
        mg_audit('merchant.homeserver_campaign_draft_saved', 'campaign', [
            'campaign_id' => $campaignId,
            'campaign_type' => $campaignType,
            'reward_template_id' => $rewardPublicId !== '' ? $rewardPublicId : null,
            'source' => 'homeserver_agent',
        ], $merchantId);
    }
    return [
        'campaign_id' => $campaignId,
        'campaign_type' => $campaignType,
        'title' => $title,
        'status' => 'draft',
        'reward_template_id' => $rewardPublicId !== '' ? $rewardPublicId : null,
        'value_cents' => $valueCents,
        'authority' => 'microgifter',
    ];
}

function mg_homeserver_campaign_authorization(PDO $pdo, array $device, string $campaignType): array
'''
if "function mg_homeserver_campaign_save_draft" not in text:
    if text.count(helper_anchor) != 1:
        raise SystemExit("campaign authorization helper anchor was not found exactly once")
    text = text.replace(helper_anchor, helpers, 1)

start = text.find("function mg_homeserver_campaign_action(PDO $pdo, array $device, array $input): array\n")
end = text.find("function mg_homeserver_campaign_receipt_payload(array $row): array\n")
if start < 0 or end < 0 or end <= start:
    raise SystemExit("campaign action function boundaries were not found")

replacement = r'''function mg_homeserver_campaign_action(PDO $pdo, array $device, array $input): array
{
    $merchantId = mg_homeserver_device_merchant_id($device);
    $actionType = strtolower(trim((string)($input['action_type'] ?? '')));
    $campaignType = strtolower(trim((string)($input['campaign_type'] ?? '')));
    $campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
    $contactId = strtolower(trim((string)($input['contact_id'] ?? '')));
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    $evidence = is_array($input['evidence'] ?? null) ? $input['evidence'] : [];
    $requestedChannel = strtolower(trim((string)($input['channel'] ?? '')));
    $allowedActions = ['campaign.draft','campaign.publish','campaign.pause','campaign.resume','campaign.send_make_good','campaign.send_authorized'];
    if (!in_array($actionType, $allowedActions, true)
        || !mg_campaign_type_is_valid($campaignType, true)
        || $idempotencyKey === ''
        || strlen($idempotencyKey) > 190) {
        mg_fail('Invalid HomeServer campaign action request.', 422);
    }
    if ($campaignId !== '' && !mg_homeserver_is_uuid($campaignId)) mg_fail('Campaign identity is invalid.', 422);
    if ($contactId !== '' && !mg_homeserver_is_uuid($contactId)) mg_fail('CRM contact identity is invalid.', 422);
    if ($actionType === 'campaign.send_make_good' && $campaignType !== 'customer_refund') mg_fail('Make-Good actions require a Customer Refund campaign.', 422);

    $authorization = mg_homeserver_campaign_authorization($pdo, $device, $campaignType);
    $authorityLevel = (string)$authorization['authority_level'];
    if ($actionType === 'campaign.draft' && !in_array($authorityLevel, ['draft','approval_required','authorized_execution'], true)) {
        mg_fail('This campaign authorization does not permit provider drafts.', 403);
    }
    if (in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume','campaign.send_make_good','campaign.send_authorized'], true)
        && !in_array($authorityLevel, ['approval_required','authorized_execution'], true)) {
        mg_fail('This campaign authorization does not permit provider changes.', 403);
    }

    $existingStmt = $pdo->prepare('SELECT * FROM homeserver_campaign_action_receipts WHERE device_id=? AND idempotency_key=? LIMIT 1');
    $existingStmt->execute([(int)$device['id'], $idempotencyKey]);
    $existingReceipt = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingReceipt) return ['duplicate' => true, 'receipt' => mg_homeserver_campaign_receipt_payload($existingReceipt)];

    $allowedCampaigns = json_decode((string)($authorization['allowed_campaign_ids_json'] ?? 'null'), true);
    if (is_array($allowedCampaigns) && $allowedCampaigns !== [] && !in_array($campaignId, $allowedCampaigns, true)) {
        mg_fail('Campaign is outside the merchant authorization.', 403);
    }
    if ((bool)$authorization['require_evidence'] && $evidence === []) mg_fail('Evidence is required for this campaign action.', 422);

    $sendActions = ['campaign.send_make_good','campaign.send_authorized'];
    $isSend = in_array($actionType, $sendActions, true);
    if ($actionType !== 'campaign.draft' && $campaignId === '') mg_fail('Campaign identity is required for this action.', 422);
    if ($isSend && $contactId === '') mg_fail('CRM contact identity is required for a campaign send.', 422);

    $actualValueCents = 0;
    $recipientCount = $isSend ? 1 : max(0, (int)($input['recipient_count'] ?? 0));
    $campaign = null;
    $contact = null;
    $channel = $requestedChannel;
    $providerResponse = null;

    if ($actionType === 'campaign.draft') {
        $providerResponse = mg_homeserver_campaign_save_draft($pdo, $merchantId, $campaignType, $campaignId, $input);
        $campaignId = (string)$providerResponse['campaign_id'];
        $actualValueCents = max(0, (int)$providerResponse['value_cents']);
    } else {
        $campaignStmt = $pdo->prepare('SELECT c.*,rt.public_id reward_template_public_id,rt.value_amount_cents,rt.currency,rt.status reward_template_status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1');
        $campaignStmt->execute([$campaignId, $merchantId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_fail('Merchant campaign was not found.', 404);
        if ((string)$campaign['campaign_type'] !== $campaignType) mg_fail('Campaign type does not match the merchant authorization.', 409);
        $actualValueCents = max(0, (int)($campaign['value_amount_cents'] ?? 0));
    }

    $merchantStmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $merchantStmt->execute([$merchantId]);
    $merchantUser = $merchantStmt->fetch(PDO::FETCH_ASSOC);
    if (!$merchantUser) mg_fail('Merchant account is unavailable.', 404);

    if ($isSend) {
        if (!$campaign || (string)$campaign['status'] !== 'active' || (string)($campaign['reward_template_status'] ?? '') !== 'active') {
            mg_fail('Authorized campaign and reward must be active before sending.', 409);
        }
        $contactStmt = $pdo->prepare('SELECT * FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $contactStmt->execute([$contactId, $merchantId]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) mg_fail('Merchant CRM contact was not found.', 404);
        $hasAccount = mg_crm_campaign_send_contact_has_account($pdo, $merchantId, $contactId);
        $channel = $channel !== '' ? $channel : ($hasAccount ? 'microgifter_inbox' : 'email');
        if ((bool)$authorization['require_consent']) {
            $consent = strtolower(trim((string)($contact['opt_in_status'] ?? '')));
            if (!in_array($consent, ['opted_in','subscribed','consented'], true)) mg_fail('The CRM contact has not consented to this campaign channel.', 403);
        }
    }

    $allowedChannels = json_decode((string)$authorization['allowed_channels_json'], true) ?: [];
    if (($isSend || $requestedChannel !== '') && !in_array($channel, $allowedChannels, true)) mg_fail('Campaign channel is outside the merchant authorization.', 403);
    if ($authorization['maximum_value_cents'] !== null && $actualValueCents > (int)$authorization['maximum_value_cents']) mg_fail('Campaign value exceeds the per-recipient authorization.', 403);
    if ($authorization['maximum_recipients'] !== null && $recipientCount > (int)$authorization['maximum_recipients']) mg_fail('Campaign audience exceeds the authorization.', 403);

    if ($isSend && ($authorization['allowed_send_start'] !== null || $authorization['allowed_send_end'] !== null)) {
        $zone = new DateTimeZone((string)$authorization['timezone_name']);
        $now = new DateTimeImmutable('now', $zone);
        $clock = $now->format('H:i:s');
        $startTime = (string)($authorization['allowed_send_start'] ?? '00:00:00');
        $endTime = (string)($authorization['allowed_send_end'] ?? '23:59:59');
        $inside = $startTime <= $endTime ? ($clock >= $startTime && $clock <= $endTime) : ($clock >= $startTime || $clock <= $endTime);
        if (!$inside) mg_fail('Campaign send is outside the merchant-authorized sending hours.', 403);
    }

    if ($isSend && (int)$authorization['duplicate_window_days'] > 0) {
        $duplicateStmt = $pdo->prepare("SELECT public_id FROM homeserver_campaign_action_receipts WHERE merchant_user_id=? AND campaign_type=? AND contact_public_id=? AND action_type IN ('campaign.send_make_good','campaign.send_authorized') AND disposition='executed' AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY) LIMIT 1");
        $duplicateStmt->execute([$merchantId, $campaignType, $contactId, (int)$authorization['duplicate_window_days']]);
        if ($duplicateStmt->fetchColumn()) mg_fail('A matching authorized campaign was already sent inside the duplicate-prevention window.', 409);
    }

    $spentStmt = $pdo->prepare("SELECT COALESCE(SUM(value_cents),0) total_spent,COALESCE(SUM(CASE WHEN created_at>=UTC_DATE() THEN value_cents ELSE 0 END),0) daily_spent FROM homeserver_campaign_action_receipts WHERE authorization_id=? AND disposition='executed'");
    $spentStmt->execute([(int)$authorization['id']]);
    $spent = $spentStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_spent' => 0, 'daily_spent' => 0];
    if ($authorization['maximum_daily_value_cents'] !== null && ((int)$spent['daily_spent'] + ($actualValueCents * $recipientCount)) > (int)$authorization['maximum_daily_value_cents']) mg_fail('Campaign action exceeds the merchant-authorized daily value.', 403);
    if ($authorization['maximum_total_value_cents'] !== null && ((int)$spent['total_spent'] + ($actualValueCents * $recipientCount)) > (int)$authorization['maximum_total_value_cents']) mg_fail('Campaign action exceeds the merchant-authorized total value.', 403);

    $requiresApproval = $authorityLevel === 'approval_required'
        || ($authorization['approval_threshold_cents'] !== null && $actualValueCents > (int)$authorization['approval_threshold_cents']);
    if ($actionType === 'campaign.draft' || $authorityLevel === 'authorized_execution') $requiresApproval = false;

    $request = $input;
    unset($request['merchant_approval_token'], $request['merchant_approval_hash'], $request['value_cents']);
    $request['provider_calculated_value_cents'] = $actualValueCents;
    $request['provider_selected_channel'] = $channel !== '' ? $channel : null;
    $requestHash = hash('sha256', mg_homeserver_json($request));
    $receiptId = mg_homeserver_public_uuid();
    $disposition = $actionType === 'campaign.draft' ? 'drafted' : ($requiresApproval ? 'awaiting_approval' : 'executed');

    if (!$requiresApproval && $actionType !== 'campaign.draft') {
        if (in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true)) {
            if (in_array($actionType, ['campaign.publish','campaign.resume'], true)) {
                if (mg_campaign_type_requires_reward_template($campaignType, 'active')
                    && (empty($campaign['reward_template_id']) || (string)($campaign['reward_template_status'] ?? '') !== 'active')) {
                    mg_fail('Active campaigns require an active reward template.', 422);
                }
                if (function_exists('mg_package_require_limit_available')) {
                    $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active' AND public_id<>?");
                    $usageStmt->execute([$merchantId, $campaignId]);
                    mg_package_require_limit_available($pdo, $merchantUser, 'max_active_campaigns', (int)$usageStmt->fetchColumn(), 'Active campaign limit reached.');
                }
            }
            $status = match ($actionType) { 'campaign.publish','campaign.resume' => 'active', 'campaign.pause' => 'paused' };
            $stmt = $pdo->prepare('UPDATE campaigns SET status=?,updated_at=UTC_TIMESTAMP() WHERE public_id=? AND merchant_user_id=?');
            $stmt->execute([$status, $campaignId, $merchantId]);
            if ($stmt->rowCount() !== 1) mg_fail('Merchant campaign could not be updated.', 409);
            $providerResponse = ['campaign_id' => $campaignId, 'status' => $status, 'authority' => 'microgifter'];
        } elseif ($isSend) {
            try {
                $providerResponse = mg_crm_campaign_send_for_contact($pdo, $merchantId, $merchantUser, [
                    'contact_id' => $contactId,
                    'campaign_id' => $campaignId,
                    'reward_template_id' => (string)($campaign['reward_template_public_id'] ?? ''),
                    'required_campaign_type' => $campaignType,
                    'note' => trim((string)($input['message'] ?? $input['note'] ?? '')),
                    'idempotency_key' => 'homeserver:' . $idempotencyKey,
                ]);
            } catch (MgCrmCampaignSendException $error) {
                mg_fail($error->getMessage(), $error->httpStatus, $error->context);
            }
        }
    }

    $stmt = $pdo->prepare('INSERT INTO homeserver_campaign_action_receipts (public_id,device_id,merchant_user_id,authorization_id,idempotency_key,action_type,campaign_type,campaign_public_id,contact_public_id,evidence_json,request_json,request_hash,policy_hash,disposition,reason_code,provider_response_json,value_cents,recipient_count,executed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $stmt->execute([$receiptId, (int)$device['id'], $merchantId, (int)$authorization['id'], $idempotencyKey, $actionType, $campaignType, $campaignId ?: null, $contactId ?: null, mg_homeserver_json($evidence), mg_homeserver_json($request), $requestHash, (string)$authorization['policy_hash'], $disposition, $requiresApproval ? 'merchant_approval_required' : null, $providerResponse === null ? null : mg_homeserver_json($providerResponse), $actualValueCents * max(1, $recipientCount), $recipientCount, $disposition === 'executed' ? gmdate('Y-m-d H:i:s') : null]);
    return [
        'duplicate' => false,
        'receipt' => [
            'receipt_id' => $receiptId,
            'action_type' => $actionType,
            'campaign_type' => $campaignType,
            'campaign_id' => $campaignId ?: null,
            'contact_id' => $contactId ?: null,
            'channel' => $channel !== '' ? $channel : null,
            'value_cents' => $actualValueCents,
            'disposition' => $disposition,
            'reason_code' => $requiresApproval ? 'merchant_approval_required' : null,
            'request_hash' => $requestHash,
            'policy_hash' => (string)$authorization['policy_hash'],
            'provider_response' => $providerResponse,
            'authority' => 'microgifter',
            'created_at' => gmdate(DATE_ATOM),
        ],
    ];
}

'''
text = text[:start] + replacement + text[end:]
path.write_text(text, encoding="utf-8", newline="\n")
print("Real HomeServer campaign draft management and safe status transitions applied.")
