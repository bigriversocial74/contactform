#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, value: str) -> None:
    (ROOT / path).write_text(value, encoding="utf-8", newline="\n")


def replace_once(value: str, old: str, new: str, label: str) -> str:
    count = value.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one anchor, found {count}")
    return value.replace(old, new, 1)

# Expand only the signed endpoint scopes. Dataset and campaign policies remain
# separate merchant-owned records.
homeserver = read("api/homeserver/_homeserver.php")
homeserver = replace_once(
    homeserver,
    """function mg_homeserver_scopes(): array
{
    return ['homeserver.status', 'homeserver.sync.write'];
}
""",
    """function mg_homeserver_scopes(): array
{
    return [
        'homeserver.status',
        'homeserver.sync.write',
        'homeserver.operational.read',
        'homeserver.reviews.read',
        'homeserver.messages.read',
        'homeserver.crm.read',
        'homeserver.commerce_history.read',
        'homeserver.gifts.read',
        'homeserver.campaigns.read',
        'homeserver.campaigns.execute',
    ];
}
""",
    "HomeServer scope set",
)
write("api/homeserver/_homeserver.php", homeserver)

# Register the additive migration exactly once.
migrations = read("config/migrations.php")
marker = "        '20260727_homeserver_release_distribution_v1.sql',\n"
addition = marker + "        '20260728_homeserver_operational_intelligence_campaign_authority_v1.sql',\n"
if "20260728_homeserver_operational_intelligence_campaign_authority_v1.sql" not in migrations:
    migrations = replace_once(migrations, marker, addition, "migration manifest")
write("config/migrations.php", migrations)

# Reconcile the catalog with canonical Microgifter review and CRM table names.
operational = read("api/homeserver/_operational_intelligence.php")
review_anchor = """        'reviews.customer_reviews' => mg_homeserver_dataset('Customer reviews', 'restricted', array_merge($commonUses, ['sentiment_analysis','semantic_clustering','service_recovery']), [
            mg_homeserver_source('reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','order_id','product_id','location_id','rating','title','review_text','body','status','source','merchant_response','response_status','created_at','updated_at']),
"""
review_replacement = """        'reviews.customer_reviews' => mg_homeserver_dataset('Customer reviews', 'restricted', array_merge($commonUses, ['sentiment_analysis','semantic_clustering','service_recovery']), [
            mg_homeserver_source('customer_reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','profile_id','campaign_id','reviewer_user_id','contact_id','wallet_item_id','reviewer_name','rating','review_title','review_body','status','metadata_json','submitted_at','created_at','updated_at']),
            mg_homeserver_source('reviews', 'merchant_user_id', 'id', 'updated_at', ['id','public_id','merchant_user_id','user_id','order_id','product_id','location_id','rating','title','review_text','body','status','source','merchant_response','response_status','created_at','updated_at']),
"""
operational = replace_once(operational, review_anchor, review_replacement, "customer review source")
operational = operational.replace("mg_homeserver_source('merchant_crm_events'", "mg_homeserver_source('merchant_crm_contact_events'")
operational = operational.replace(
    "['id','public_id','merchant_user_id','user_id','email','phone','name','first_name','last_name','company','job_title','address','birthday','source','status','lifecycle_stage','relationship_type','tags_json','preferences_json','consent_json','created_at','updated_at']",
    "['id','public_id','merchant_user_id','user_id','primary_email','primary_phone','display_name','lifecycle_stage','crm_status','last_campaign_type','last_source_type','first_seen_at','last_seen_at','last_engaged_at','last_purchased_at','last_reward_issued_at','last_reward_claimed_at','last_reward_redeemed_at','total_purchase_cents','total_rewards_issued','total_rewards_claimed','total_rewards_redeemed','source_summary_json','tags_json','metadata_json','created_at','updated_at']",
)
operational = operational.replace(
    "['id','public_id','merchant_user_id','contact_id','campaign_id','campaign_type','event_type','source_type','source_public_id','user_id','email','name','value_cents','metadata_json','created_at']",
    "['id','public_id','merchant_user_id','crm_contact_id','campaign_id','campaign_type','event_type','source_type','source_public_id','user_id','email','phone','name','value_cents','metadata_json','created_at']",
)

# Event mode produces event envelopes, while snapshot/incremental produce records.
envelope_anchor = """    $cursorAfter = mg_homeserver_operational_cursor_encode($lastUpdated, $lastId);
    $sourceRevision = hash('sha256', $datasetKey . '|' . $cursorAfter . '|' . count($records));
    $envelope = [
"""
envelope_replacement = """    $cursorAfter = mg_homeserver_operational_cursor_encode($lastUpdated, $lastId);
    $events = [];
    if ($mode === 'event') {
        foreach ($records as $record) {
            $payload = is_array($record['payload'] ?? null) ? $record['payload'] : [];
            $events[] = [
                'source_event_id' => (string)$record['source_object_type'] . ':' . (string)$record['source_object_id'] . ':' . (string)$record['source_revision'],
                'event_type' => (string)($payload['event_type'] ?? $payload['activity_type'] ?? $payload['trigger_event'] ?? $datasetKey . '.updated'),
                'occurred_at_utc' => (string)$record['source_updated_at_utc'],
                'source_revision' => (string)$record['source_revision'],
                'payload' => $payload,
                'payload_hash' => (string)$record['payload_hash'],
            ];
        }
        $records = [];
    }
    $sourceRevision = hash('sha256', $datasetKey . '|' . $cursorAfter . '|' . count($records) . '|' . count($events));
    $envelope = [
"""
operational = replace_once(operational, envelope_anchor, envelope_replacement, "event envelope")
operational = operational.replace("        'events' => [],\n", "        'events' => $events,\n", 1)
operational = operational.replace(
    "mg_homeserver_operational_record_receipt($pdo, $device, $datasetKey, $mode, $cursorBefore, $cursorAfter, $sourceRevision, count($records), 0, $payloadHash, $records === [] ? 'empty' : 'accepted', null, $startedAt);",
    "mg_homeserver_operational_record_receipt($pdo, $device, $datasetKey, $mode, $cursorBefore, $cursorAfter, $sourceRevision, count($records), count($events), $payloadHash, ($records === [] && $events === []) ? 'empty' : 'accepted', null, $startedAt);",
)

# Replace the campaign action implementation with provider-authoritative policy checks.
start = operational.index("function mg_homeserver_campaign_action(PDO $pdo, array $device, array $input): array")
end = operational.index("function mg_homeserver_campaign_receipt_payload(array $row): array", start)
secure_action = r'''function mg_homeserver_campaign_action(PDO $pdo, array $device, array $input): array
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
    if (!in_array($actionType, $allowedActions, true) || preg_match('/^[a-z0-9_]{3,80}$/', $campaignType) !== 1 || $idempotencyKey === '' || strlen($idempotencyKey) > 190) {
        mg_fail('Invalid HomeServer campaign action request.', 422);
    }
    if ($campaignId !== '' && !mg_homeserver_is_uuid($campaignId)) mg_fail('Campaign identity is invalid.', 422);
    if ($contactId !== '' && !mg_homeserver_is_uuid($contactId)) mg_fail('CRM contact identity is invalid.', 422);
    if ($actionType === 'campaign.send_make_good' && $campaignType !== 'customer_refund') mg_fail('Make-Good actions require a Customer Refund campaign.', 422);

    $authorization = mg_homeserver_campaign_authorization($pdo, $device, $campaignType);
    $existingStmt = $pdo->prepare('SELECT * FROM homeserver_campaign_action_receipts WHERE device_id=? AND idempotency_key=? LIMIT 1');
    $existingStmt->execute([(int)$device['id'], $idempotencyKey]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) return ['duplicate' => true, 'receipt' => mg_homeserver_campaign_receipt_payload($existing)];

    $allowedCampaigns = json_decode((string)($authorization['allowed_campaign_ids_json'] ?? 'null'), true);
    if (is_array($allowedCampaigns) && $allowedCampaigns !== [] && !in_array($campaignId, $allowedCampaigns, true)) mg_fail('Campaign is outside the merchant authorization.', 403);
    if ((bool)$authorization['require_evidence'] && $evidence === []) mg_fail('Evidence is required for this campaign action.', 422);

    $sendActions = ['campaign.send_make_good','campaign.send_authorized'];
    $isSend = in_array($actionType, $sendActions, true);
    if (($isSend || in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true)) && $campaignId === '') mg_fail('Campaign identity is required for this action.', 422);
    if ($isSend && $contactId === '') mg_fail('CRM contact identity is required for a campaign send.', 422);

    $actualValueCents = 0;
    $recipientCount = $isSend ? 1 : max(0, (int)($input['recipient_count'] ?? 0));
    $campaign = null;
    $contact = null;
    $channel = $requestedChannel;
    if ($campaignId !== '') {
        $campaignStmt = $pdo->prepare('SELECT c.*,rt.public_id reward_template_public_id,rt.value_amount_cents,rt.currency,rt.status reward_template_status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1');
        $campaignStmt->execute([$campaignId, $merchantId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) mg_fail('Merchant campaign was not found.', 404);
        if ((string)$campaign['campaign_type'] !== $campaignType) mg_fail('Campaign type does not match the merchant authorization.', 409);
        $actualValueCents = max(0, (int)($campaign['value_amount_cents'] ?? 0));
    }
    if ($isSend) {
        if (!$campaign || (string)$campaign['status'] !== 'active' || (string)($campaign['reward_template_status'] ?? '') !== 'active') mg_fail('Authorized campaign and reward must be active before sending.', 409);
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

    $authorityLevel = (string)$authorization['authority_level'];
    $requiresApproval = $authorityLevel === 'approval_required'
        || ($authorization['approval_threshold_cents'] !== null && $actualValueCents > (int)$authorization['approval_threshold_cents']);
    if ($isSend && !in_array($authorityLevel, ['approval_required','authorized_execution'], true)) mg_fail('This campaign authorization does not permit sending.', 403);
    if (in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true) && !in_array($authorityLevel, ['approval_required','authorized_execution'], true)) mg_fail('This campaign authorization does not permit provider changes.', 403);
    if ($authorityLevel === 'authorized_execution') $requiresApproval = false;

    $request = $input;
    unset($request['merchant_approval_token'], $request['merchant_approval_hash'], $request['value_cents']);
    $request['provider_calculated_value_cents'] = $actualValueCents;
    $request['provider_selected_channel'] = $channel !== '' ? $channel : null;
    $requestHash = hash('sha256', mg_homeserver_json($request));
    $receiptId = mg_homeserver_public_uuid();
    $disposition = $actionType === 'campaign.draft' ? 'drafted' : ($requiresApproval ? 'awaiting_approval' : 'executed');
    $providerResponse = null;

    if (!$requiresApproval && $actionType !== 'campaign.draft') {
        if (in_array($actionType, ['campaign.publish','campaign.pause','campaign.resume'], true)) {
            $status = match ($actionType) { 'campaign.publish','campaign.resume' => 'active', 'campaign.pause' => 'paused' };
            $stmt = $pdo->prepare('UPDATE campaigns SET status=?,updated_at=UTC_TIMESTAMP() WHERE public_id=? AND merchant_user_id=?');
            $stmt->execute([$status, $campaignId, $merchantId]);
            if ($stmt->rowCount() !== 1) mg_fail('Merchant campaign could not be updated.', 409);
            $providerResponse = ['campaign_id' => $campaignId, 'status' => $status, 'authority' => 'microgifter'];
        } elseif ($isSend) {
            $merchantStmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
            $merchantStmt->execute([$merchantId]);
            $merchantUser = $merchantStmt->fetch(PDO::FETCH_ASSOC);
            if (!$merchantUser) mg_fail('Merchant account is unavailable.', 404);
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
operational = operational[:start] + secure_action + operational[end:]
write("api/homeserver/_operational_intelligence.php", operational)

# Existing CRM endpoints become thin authenticated wrappers around the same
# service called by the signed HomeServer campaign endpoint.
direct_wrapper = r'''<?php

declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $result = mg_crm_campaign_send_execute($pdo, $merchantId, $input);
    $label = (string)($result['campaign_type_label'] ?? 'Campaign');
    mg_ok($result, !empty($result['duplicate']) ? 'Campaign reward already issued.' : $label . ' reward issued.', !empty($result['duplicate']) ? 200 : 201);
} catch (MgCrmCampaignSendException $error) {
    mg_fail($error->getMessage(), $error->httpStatus, $error->context);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.crm_campaign_send.failed', 'Unable to send CRM campaign reward.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to send CRM campaign reward.', 500);
}
'''
write("api/merchant/crm-campaign-send.php", direct_wrapper)

invite_wrapper = r'''<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $result = mg_crm_campaign_invite_execute($pdo, $merchantId, $user, $input);
    mg_ok($result, !empty($result['duplicate']) ? 'CRM reward invite already sent.' : 'CRM reward invite sent.', !empty($result['duplicate']) ? 200 : 201);
} catch (MgCrmCampaignSendException $error) {
    mg_fail($error->getMessage(), $error->httpStatus, $error->context);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.crm_reward_invite.failed', 'Unable to send CRM reward invite.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to send CRM reward invite.', 500);
}
'''
write("api/merchant/crm-send-reward-invite.php", invite_wrapper)

print("Microgifter HomeServer operational intelligence integration applied.")
