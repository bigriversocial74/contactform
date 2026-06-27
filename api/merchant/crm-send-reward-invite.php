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
$contactRef = strtolower(trim((string)($input['contact_id'] ?? '')));
$templateRef = strtolower(trim((string)($input['reward_template_id'] ?? '')));
$note = trim((string)($input['note'] ?? ''));
$idem = trim((string)($input['idempotency_key'] ?? ''));
if ($contactRef === '' || strlen($contactRef) !== 36 || $templateRef === '' || strlen($templateRef) !== 36 || mb_strlen($note) > 1000) mg_fail('Invalid CRM reward invite request.', 422);
if ($idem === '') $idem = substr('crm-reward-invite:' . hash('sha256', $merchantId . '|' . $contactRef . '|' . $templateRef . '|' . microtime(true)), 0, 190);
if (!mg_crm_reward_invites_ready($pdo)) mg_fail('CRM reward invite schema is not installed.', 503);
try {
    mg_delivery_install_schema($pdo);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT cc.*,c.public_id campaign_public_id,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$contactRef, $merchantId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) { $pdo->rollBack(); mg_fail('CRM contact not found.', 404); }
    if ((int)($contact['user_id'] ?? 0) > 0) { $pdo->rollBack(); mg_fail('This contact already has an account. Use direct reward send.', 409); }
    $email = strtolower(trim((string)($contact['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $pdo->rollBack(); mg_fail('A valid contact email is required for reward invites.', 422); }
    $t = $pdo->prepare("SELECT * FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
    $t->execute([$templateRef, $merchantId]);
    $template = $t->fetch(PDO::FETCH_ASSOC);
    if (!$template) { $pdo->rollBack(); mg_fail('Active reward template not found.', 404); }
    $pending = $pdo->prepare("SELECT COUNT(*) FROM crm_reward_invites WHERE reward_template_id=? AND status='sent' AND (expires_at IS NULL OR expires_at>NOW())");
    $pending->execute([(int)$template['id']]);
    if ($template['quantity_limit'] !== null && ((int)$template['issued_count'] + (int)$pending->fetchColumn()) >= (int)$template['quantity_limit']) { $pdo->rollBack(); mg_fail('Reward template limit has been reached.', 409); }
    $existing = $pdo->prepare('SELECT public_id FROM crm_reward_invites WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1');
    $existing->execute([$merchantId, $idem]);
    $old = (string)($existing->fetchColumn() ?: '');
    if ($old !== '') { $pdo->commit(); mg_ok(['invite_id' => $old, 'duplicate' => true], 'CRM reward invite already sent.'); }
    $active = $pdo->prepare("SELECT public_id FROM crm_reward_invites WHERE merchant_user_id=? AND contact_id=? AND reward_template_id=? AND status='sent' AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1");
    $active->execute([$merchantId, (int)$contact['id'], (int)$template['id']]);
    $activeId = (string)($active->fetchColumn() ?: '');
    if ($activeId !== '') { $pdo->rollBack(); mg_fail('This reward invite is already waiting for this contact.', 409, ['invite_id' => $activeId]); }
    $inviteId = mg_crm_reward_invite_uuid();
    $inviteExpiresAt = date('Y-m-d H:i:s', time() + 1209600);
    $inviteUrl = mg_app_base_url() . '/signup.php?crm_reward_invite=' . rawurlencode($inviteId);
    $meta = ['campaign_type' => 'merchant_crm_reward_invite', 'contact_id' => (string)$contact['public_id'], 'reward_template_id' => (string)$template['public_id']];
    $ins = $pdo->prepare('INSERT INTO crm_reward_invites (public_id,merchant_user_id,campaign_id,contact_id,reward_template_id,email,name,status,note,idempotency_key,invite_url,sent_at,expires_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    $ins->execute([$inviteId, $merchantId, (int)$contact['campaign_id'], (int)$contact['id'], (int)$template['id'], $email, (string)($contact['name'] ?? ''), 'sent', $note, $idem, $inviteUrl, date('Y-m-d H:i:s'), $inviteExpiresAt, json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $delivery = null;
    try { $delivery = mg_delivery_enqueue($pdo, ['idempotency_key' => 'crm-reward-invite-email:' . $idem, 'event_type' => 'campaign.outbound_email', 'category' => 'campaign', 'channel' => 'email', 'template_key' => 'campaign.crm_reward_invite', 'recipient_snapshot' => ['email' => $email, 'name' => (string)($contact['name'] ?? '')], 'payload' => mg_crm_reward_invite_email_payload($contact, $template, $inviteId, $inviteUrl, $note, $inviteExpiresAt), 'max_attempts' => 3]); } catch (Throwable) { $delivery = ['queued' => false]; }
    mg_crm_reward_invite_record_campaign_event($pdo, $contact, null, 'crm.reward_invite.sent', ['invite_id' => $inviteId, 'reward_template_id' => (string)$template['public_id'], 'email_delivery' => $delivery]);
    mg_merchant_crm_record_event($pdo, ['merchant_user_id' => $merchantId, 'campaign_id' => (int)$contact['campaign_id'], 'campaign_type' => 'merchant_crm_reward_invite', 'event_type' => 'crm.reward_invite.sent', 'source_type' => 'merchant_crm_reward_invite', 'source_public_id' => (string)$contact['public_id'], 'email' => $email, 'name' => (string)($contact['name'] ?? ''), 'value_cents' => (int)$template['value_amount_cents'], 'metadata' => ['invite_id' => $inviteId, 'reward_template_id' => (string)$template['public_id'], 'delivery' => $delivery]]);
    $pdo->commit();
    mg_ok(['invite_id' => $inviteId, 'invite_url' => $inviteUrl, 'email_delivery' => $delivery, 'expires_at' => $inviteExpiresAt, 'duplicate' => false], 'CRM reward invite sent.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_reward_invite.failed', 'Unable to send CRM reward invite.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to send CRM reward invite.', 500);
}
