<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm.php';
require_once __DIR__ . '/_limits.php';
require_once __DIR__ . '/_security.php';
require_once __DIR__ . '/_followups.php';
require_once __DIR__ . '/_merchant_notifications.php';

function mg_contest_campaign_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_contest_campaign_expiry(array $template): ?string
{
    $rule = (string)($template['expiration_rule'] ?? 'none');
    if ($rule === 'fixed_date' || $rule === 'event_date') return $template['expires_at'] ?: null;
    if ($rule === 'after_issue' && !empty($template['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int)$template['expiration_days'] * 86400));
    return null;
}

function mg_contest_campaign_event(PDO $pdo, int $merchantId, int $campaignId, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_contest_campaign_uuid(), $merchantId, $campaignId, $walletItemId, $contactId, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    mg_campaign_followup_schedule($pdo, ['merchant_user_id' => $merchantId, 'campaign_id' => $campaignId, 'contact_id' => $contactId, 'wallet_item_id' => $walletItemId, 'trigger_event' => $eventType, 'context' => $context]);
}

function mg_contest_campaign_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_contest_campaign_rules(array $campaign): array
{
    $json = (string)($campaign['rules_json'] ?? '');
    $decoded = $json !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_contest_campaign_should_issue_entry_reward(array $campaign, array $rules): bool
{
    $mode = (string)($rules['mode'] ?? 'instant_reward');
    if ($mode === 'instant_reward') return true;
    if ($mode === 'first_x') {
        $limit = (int)($rules['winner_limit'] ?? 0);
        if ($limit <= 0) $limit = (int)($campaign['quantity_limit'] ?? 100);
        return (int)($campaign['issued_count'] ?? 0) < $limit;
    }
    return !empty($rules['entry_reward_enabled']);
}

function mg_contest_campaign_success_message(bool $issuedReward, bool $alreadyIssued, array $rules, array $campaign): string
{
    if ($alreadyIssued) return 'Contest entry already has this reward.';
    if ($issuedReward && (string)($rules['mode'] ?? '') === 'first_x') {
        $limit = (int)($rules['winner_limit'] ?? $campaign['quantity_limit'] ?? 0);
        return $limit > 0 ? 'Contest entry recorded. You were one of the first ' . $limit . ' entries, so the reward was issued.' : 'Contest entry reward issued.';
    }
    if ($issuedReward) return 'Contest entry reward issued.';
    if ((string)($rules['mode'] ?? '') === 'random_draw') return 'Contest entry recorded. Winner selection happens later.';
    if ((string)($rules['mode'] ?? '') === 'manual_winner') return 'Contest entry recorded. The merchant will select winners.';
    return 'Contest entry recorded.';
}

function mg_contest_campaign_bridge(PDO $pdo, array $campaign, array $contact, int $walletDbId, string $walletPublicId, ?int $userId, ?string $expiresAt, string $sourceType): ?array
{
    if (!$userId) return null;
    return mg_zero_reward_issue_from_wallet($pdo, [
        'merchant_user_id' => (int)$campaign['merchant_user_id'],
        'recipient_user_id' => $userId,
        'recipient_external_id' => (string)($contact['public_id'] ?? ''),
        'wallet_item_db_id' => $walletDbId,
        'wallet_item_public_id' => $walletPublicId,
        'campaign_public_id' => (string)$campaign['public_id'],
        'reward_template_public_id' => (string)$campaign['reward_template_public_id'],
        'source_type' => $sourceType,
        'source_reference' => $walletPublicId,
        'source_line_reference' => (string)($contact['public_id'] ?? $walletPublicId),
        'title' => (string)($campaign['reward_template_title'] ?? 'Contest reward'),
        'description' => $campaign['description'] ?? null,
        'currency' => (string)($campaign['currency'] ?? 'USD'),
        'display_value_cents' => (int)($campaign['value_amount_cents'] ?? 0),
        'expires_at' => $expiresAt,
        'redemption_instructions' => $campaign['redemption_instructions'] ?? null,
        'terms' => ['campaign_type' => 'contest_giveaway'],
    ]);
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();

$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$name = trim((string)($input['name'] ?? $input['full_name'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$entryContext = is_array($input['entry'] ?? null) ? $input['entry'] : [];

if ($campaignRef === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255 || mb_strlen($name) > 180 || mb_strlen($phone) > 60) {
    mg_fail('Invalid contest entry.', 422);
}
mg_public_campaign_throttle('contest_entry', $campaignRef, $email);

try {
    $pdo->beginTransaction();
    $sql = 'SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description,
                   rt.redemption_instructions, rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at,
                   rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count, rt.per_user_limit reward_template_per_user_limit
            FROM campaigns c
            INNER JOIN reward_templates rt ON rt.id = c.reward_template_id AND rt.status = \'active\'
            WHERE c.campaign_type = \'contest_giveaway\' AND c.status = \'active\' AND (c.public_id = ? OR c.public_slug = ?)
            LIMIT 1 FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('Contest is not available.', 404);
    }

    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) {
        $pdo->rollBack();
        mg_fail('Contest has not started yet.', 409);
    }
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) {
        $pdo->rollBack();
        mg_fail('Contest has ended.', 409);
    }

    $merchantId = (int)$campaign['merchant_user_id'];
    $campaignId = (int)$campaign['id'];
    $campaignType = (string)$campaign['campaign_type'];
    $userId = mg_contest_campaign_find_user($pdo, $email);
    $rules = mg_contest_campaign_rules($campaign);
    $source = 'contest_entry';

    $existingContactStmt = $pdo->prepare('SELECT id, public_id FROM campaign_contacts WHERE campaign_id = ? AND email = ? LIMIT 1 FOR UPDATE');
    $existingContactStmt->execute([$campaignId, $email]);
    $isNewContact = !$existingContactStmt->fetch(PDO::FETCH_ASSOC);
    mg_public_campaign_enforce_crm_contact_limit($pdo, $merchantId, $email, $isNewContact);

    $contactPublicId = mg_contest_campaign_uuid();
    $meta = ['campaign_type' => $campaignType, 'campaign_public_id' => (string)$campaign['public_id'], 'entry' => $entryContext, 'rules' => $rules, 'ip' => mg_client_ip()];
    $contactStmt = $pdo->prepare('INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),phone=VALUES(phone),name=VALUES(name),metadata_json=VALUES(metadata_json),updated_at=NOW()');
    $contactStmt->execute([$contactPublicId, $merchantId, $campaignId, $userId, $email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, $source, 'opted_in', json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

    $lookup = $pdo->prepare('SELECT id, public_id, user_id, email, name FROM campaign_contacts WHERE campaign_id = ? AND email = ? LIMIT 1');
    $lookup->execute([$campaignId, $email]);
    $contact = $lookup->fetch(PDO::FETCH_ASSOC);
    $contactId = (int)($contact['id'] ?? 0);

    $crm = mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => $campaignId,
        'campaign_type' => $campaignType,
        'event_type' => 'contest.entered',
        'source_type' => $source,
        'source_public_id' => (string)($contact['public_id'] ?? ''),
        'user_id' => $userId,
        'email' => $email,
        'phone' => $phone,
        'name' => $name,
        'metadata' => $meta,
    ]);
    $merchantNotification = mg_public_campaign_notify_merchant_contact($pdo, $campaign, $contact ?: [], $email, $name, $phone, $source, $crm, $isNewContact);
    mg_contest_campaign_event($pdo, $merchantId, $campaignId, null, $contactId, 'contest.entered', ['email' => $email, 'campaign_type' => $campaignType, 'rules' => $rules, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification]);

    $walletPublicId = null;
    $walletStatus = null;
    $alreadyIssued = false;
    $rewardTitle = null;
    $expiresAt = null;
    $bridge = null;
    $shouldIssueEntryReward = mg_contest_campaign_should_issue_entry_reward($campaign, $rules);

    $existingStmt = $pdo->prepare('SELECT id, public_id, status FROM wallet_items WHERE campaign_id = ? AND contact_id = ? AND source_type = \'contest_entry\' AND status <> \'cancelled\' ORDER BY id DESC LIMIT 1');
    $existingStmt->execute([$campaignId, $contactId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $walletPublicId = (string)$existing['public_id'];
        $walletStatus = (string)$existing['status'];
        $alreadyIssued = true;
        $expiresAt = mg_contest_campaign_expiry($campaign);
        $rewardTitle = (string)($campaign['reward_template_title'] ?? 'Contest reward');
        $bridge = mg_contest_campaign_bridge($pdo, $campaign, $contact ?: [], (int)$existing['id'], $walletPublicId, $userId, $expiresAt, $source);
    } elseif ($shouldIssueEntryReward) {
        if ($campaign['reward_template_quantity_limit'] !== null && (int)$campaign['reward_template_issued_count'] >= (int)$campaign['reward_template_quantity_limit']) {
            $pdo->rollBack();
            mg_fail('Reward template limit has been reached.', 409);
        }
        if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) {
            $pdo->rollBack();
            mg_fail('Campaign reward limit has been reached.', 409);
        }
        mg_public_campaign_enforce_reward_limits($pdo, $campaign, $userId, $email);
        $walletPublicId = mg_contest_campaign_uuid();
        $expiresAt = mg_contest_campaign_expiry($campaign);
        $rewardTitle = (string)$campaign['reward_template_title'];
        $walletStmt = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
        $walletStmt->execute([$walletPublicId, $userId, $contactId, $merchantId, (int)$campaign['reward_template_db_id'], $campaignId, $source, (string)($contact['public_id'] ?? ''), 'issued', (int)($campaign['value_amount_cents'] ?? 0), (string)($campaign['currency'] ?? 'USD'), $rewardTitle, json_encode(['campaign_type' => 'contest_giveaway', 'reward_template_id' => (string)$campaign['reward_template_public_id'], 'rules' => $rules], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
        $walletDbId = (int)$pdo->lastInsertId();
        $walletStatus = 'issued';
        $pdo->prepare('UPDATE campaigns SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([$campaignId]);
        $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int)$campaign['reward_template_db_id']]);
        $bridge = mg_contest_campaign_bridge($pdo, $campaign, $contact ?: [], $walletDbId, $walletPublicId, $userId, $expiresAt, $source);
        mg_contest_campaign_event($pdo, $merchantId, $campaignId, $walletDbId, $contactId, 'wallet_item.issued', ['wallet_item_id' => $walletPublicId, 'source_type' => $source, 'campaign_type' => $campaignType, 'rules' => $rules, 'pppm_bridge' => $bridge, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification]);
    }

    $pdo->commit();
    $message = mg_contest_campaign_success_message($walletPublicId !== null, $alreadyIssued, $rules, $campaign);
    mg_ok([
        'contact_id' => (string)($contact['public_id'] ?? ''),
        'wallet_item_id' => $walletPublicId,
        'wallet_status' => $walletStatus,
        'already_issued' => $alreadyIssued,
        'reward_title' => $rewardTitle,
        'expires_at' => $expiresAt,
        'rules' => $rules,
        'pppm_bridge' => $bridge,
        'merchant_crm' => $crm,
        'merchant_notification' => $merchantNotification,
    ], $message, $walletPublicId !== null && !$alreadyIssued ? 201 : 200);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.contest_entry.failed', 'Unable to process contest entry.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail('Unable to process contest entry.', 500);
}
