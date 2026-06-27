<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once __DIR__ . '/_profile_action_resolver.php';

function mg_profile_action_pick_campaign_contact(PDO $pdo, int $merchantId, array $input): array
{
    $campaignRef = strtolower(trim((string)($input['campaign_contact_id'] ?? '')));
    $profileRef = strtolower(trim((string)($input['crm_contact_id'] ?? $input['profile_contact_id'] ?? $input['contact_id'] ?? '')));
    $email = mg_cp_action_clean_email($input['email'] ?? '');
    $userId = (int)($input['user_id'] ?? 0);

    if ($campaignRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $campaignRef)) {
        $stmt = $pdo->prepare('SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1');
        $stmt->execute([$campaignRef, $merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return mg_cp_action_sync_campaign_contact($pdo, $merchantId, $row);
    }

    if ($profileRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $profileRef)) {
        $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$profileRef, $merchantId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $profile = mg_cp_action_sync_profile_contact($pdo, $merchantId, $profile);
            $email = $email ?: mg_cp_action_clean_email($profile['primary_email'] ?? '');
            $userId = $userId ?: (int)($profile['user_id'] ?? 0);
        }
    }

    $where = [];
    $params = [$merchantId];
    if ($userId > 0) { $where[] = 'cc.user_id=?'; $params[] = $userId; }
    if ($email !== '') { $where[] = 'LOWER(cc.email)=?'; $params[] = $email; }
    if (!$where) mg_fail('Campaign contact link required before this profile action can run.', 422);

    $stmt = $pdo->prepare('SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.merchant_user_id=? AND (' . implode(' OR ', $where) . ') ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 1');
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('No campaign contact is linked to this profile yet. Open the profile from a CRM campaign contact first.', 422);
    return mg_cp_action_sync_campaign_contact($pdo, $merchantId, $row);
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $contact = mg_profile_action_pick_campaign_contact($pdo, $merchantId, $input);
    $userId = (int)($contact['user_id'] ?? 0);
    mg_ok([
        'campaign_contact_id' => (string)$contact['public_id'],
        'contact_id' => (string)$contact['public_id'],
        'campaign_id' => (string)($contact['campaign_public_id'] ?? ''),
        'campaign_title' => (string)($contact['campaign_title'] ?? ''),
        'campaign_type' => (string)($contact['campaign_type'] ?? ''),
        'email' => (string)($contact['email'] ?? ''),
        'name' => (string)($contact['name'] ?? ''),
        'user_id' => $userId ?: null,
        'can_message' => true,
        'can_send_reward' => $userId > 0,
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.profile_action.resolve_failed', 'Unable to resolve profile action.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
    $message = $error instanceof RuntimeException || $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to resolve this profile action.';
    mg_fail($message, 422);
}
