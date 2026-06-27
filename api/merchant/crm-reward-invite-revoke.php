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
$reason = trim((string)($input['reason'] ?? 'merchant_revoked'));
if ($inviteRef === '' || strlen($inviteRef) !== 36) mg_fail('Invite is required.', 422);
if (mb_strlen($reason) > 180) mg_fail('Reason is too long.', 422);
if (!mg_crm_reward_invites_ready($pdo)) mg_fail('CRM reward invite schema is not installed.', 503);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT i.*,cc.public_id contact_public_id,cc.campaign_id,cc.merchant_user_id,rt.value_amount_cents FROM crm_reward_invites i INNER JOIN campaign_contacts cc ON cc.id=i.contact_id INNER JOIN reward_templates rt ON rt.id=i.reward_template_id WHERE i.public_id=? AND i.merchant_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$inviteRef, $merchantId]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite) { $pdo->rollBack(); mg_fail('Reward invite not found.', 404); }
    if (!in_array((string)$invite['status'], ['sent','expired'], true)) { $pdo->rollBack(); mg_fail('Delivered or linked reward invites cannot be revoked.', 409); }
    $meta = [];
    if (!empty($invite['metadata_json'])) {
        try { $decoded = json_decode((string)$invite['metadata_json'], true, 512, JSON_THROW_ON_ERROR); if (is_array($decoded)) $meta = $decoded; } catch (Throwable) { $meta = []; }
    }
    $meta['revoked_reason'] = $reason;
    $pdo->prepare("UPDATE crm_reward_invites SET status='revoked',metadata_json=?,updated_at=NOW() WHERE id=?")->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$invite['id']]);
    mg_crm_reward_invite_record_campaign_event($pdo, $invite, null, 'crm.reward_invite.revoked', ['invite_id' => (string)$invite['public_id'], 'reason' => $reason]);
    mg_merchant_crm_record_event($pdo, ['merchant_user_id' => $merchantId, 'campaign_id' => (int)$invite['campaign_id'], 'campaign_type' => 'merchant_crm_reward_invite', 'event_type' => 'crm.reward_invite.revoked', 'source_type' => 'merchant_crm_reward_invite', 'source_public_id' => (string)$invite['contact_public_id'], 'email' => (string)$invite['email'], 'name' => (string)($invite['name'] ?? ''), 'value_cents' => (int)$invite['value_amount_cents'], 'metadata' => ['invite_id' => (string)$invite['public_id'], 'reason' => $reason]]);
    $pdo->commit();
    mg_ok(['invite_id' => (string)$invite['public_id'], 'status' => 'revoked'], 'Reward invite revoked.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_reward_invite_revoke.failed', 'Unable to revoke CRM reward invite.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to revoke reward invite.', 500);
}
