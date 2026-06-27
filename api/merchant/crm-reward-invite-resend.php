<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reward-invites.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$inviteRef = strtolower(trim((string)($input['invite_id'] ?? $input['id'] ?? '')));
if ($inviteRef === '' || strlen($inviteRef) !== 36) mg_fail('Invite is required.', 422);
if (!mg_crm_reward_invites_ready($pdo)) mg_fail('CRM reward invite schema is not installed.', 503);

try {
    mg_delivery_install_schema($pdo);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT i.*,cc.public_id contact_public_id,cc.name contact_name,cc.email contact_email,cc.campaign_id,cc.merchant_user_id,c.public_id campaign_public_id,c.campaign_type,rt.public_id template_public_id,rt.title,rt.description,rt.value_amount_cents,rt.currency,rt.redemption_instructions FROM crm_reward_invites i INNER JOIN campaign_contacts cc ON cc.id=i.contact_id INNER JOIN campaigns c ON c.id=i.campaign_id INNER JOIN reward_templates rt ON rt.id=i.reward_template_id WHERE i.public_id=? AND i.merchant_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$inviteRef, $merchantId]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite) { $pdo->rollBack(); mg_fail('Reward invite not found.', 404); }
    if ((string)$invite['status'] !== 'sent') { $pdo->rollBack(); mg_fail('Only pending invites can be resent.', 409); }
    if (!empty($invite['expires_at']) && strtotime((string)$invite['expires_at']) < time()) { $pdo->prepare("UPDATE crm_reward_invites SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$invite['id']]); $pdo->commit(); mg_fail('This reward invite has expired.', 410); }
    $contact = ['public_id' => (string)$invite['contact_public_id'], 'campaign_public_id' => (string)$invite['campaign_public_id'], 'email' => (string)$invite['email'], 'name' => (string)($invite['name'] ?: $invite['contact_name'])];
    $template = ['public_id' => (string)$invite['template_public_id'], 'title' => (string)$invite['title'], 'description' => $invite['description'] ?? null, 'value_amount_cents' => (int)$invite['value_amount_cents'], 'currency' => (string)$invite['currency'], 'redemption_instructions' => $invite['redemption_instructions'] ?? null];
    $payload = mg_crm_reward_invite_email_payload($contact, $template, (string)$invite['public_id'], (string)$invite['invite_url'], (string)($invite['note'] ?? ''), $invite['expires_at'] ?? null);
    $delivery = mg_delivery_enqueue($pdo, ['idempotency_key' => 'crm-reward-invite-resend:' . (string)$invite['public_id'] . ':' . time(), 'event_type' => 'campaign.outbound_email', 'category' => 'campaign', 'channel' => 'email', 'template_key' => 'campaign.crm_reward_invite_resend', 'recipient_snapshot' => ['email' => (string)$invite['email'], 'name' => (string)($invite['name'] ?? '')], 'payload' => $payload, 'max_attempts' => 3]);
    $pdo->prepare('UPDATE crm_reward_invites SET sent_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$invite['id']]);
    mg_crm_reward_invite_record_campaign_event($pdo, $invite, null, 'crm.reward_invite.resent', ['invite_id' => (string)$invite['public_id'], 'email_delivery' => $delivery]);
    mg_merchant_crm_record_event($pdo, ['merchant_user_id' => $merchantId, 'campaign_id' => (int)$invite['campaign_id'], 'campaign_type' => 'merchant_crm_reward_invite', 'event_type' => 'crm.reward_invite.resent', 'source_type' => 'merchant_crm_reward_invite', 'source_public_id' => (string)$invite['contact_public_id'], 'email' => (string)$invite['email'], 'name' => (string)($invite['name'] ?? ''), 'value_cents' => (int)$invite['value_amount_cents'], 'metadata' => ['invite_id' => (string)$invite['public_id'], 'delivery' => $delivery]]);
    $pdo->commit();
    mg_ok(['invite_id' => (string)$invite['public_id'], 'email_delivery' => $delivery], 'Reward invite resent.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_reward_invite_resend.failed', 'Unable to resend CRM reward invite.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to resend reward invite.', 500);
}
