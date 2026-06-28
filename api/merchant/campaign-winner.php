<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/public/campaigns/_merchant_notifications.php';

function mg_winner_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_winner_expiry(array $row): ?string
{
    $rule = (string) ($row['expiration_rule'] ?? 'none');
    if ($rule === 'fixed_date' || $rule === 'event_date') return $row['expires_at'] ?: null;
    if (($rule === 'after_issue' || $rule === 'after_claim') && !empty($row['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int) $row['expiration_days'] * 86400));
    return null;
}

function mg_winner_rules(array $campaign): array
{
    $decoded = json_decode((string)($campaign['rules_json'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function mg_winner_limit(array $campaign, array $rules): ?int
{
    $limit = (int)($rules['winner_limit'] ?? 0);
    if ($limit > 0) return $limit;
    if ($campaign['quantity_limit'] !== null) return max(1, (int)$campaign['quantity_limit']);
    return 1;
}

function mg_winner_event(PDO $pdo, int $merchantId, int $campaignId, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_winner_uuid(), $merchantId, $campaignId, $walletItemId, $contactId, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$campaignPublicId = strtolower(trim((string) ($input['campaign_id'] ?? '')));
$contactPublicId = strtolower(trim((string) ($input['contact_id'] ?? '')));
$email = strtolower(trim((string) ($input['email'] ?? '')));

if ($campaignPublicId === '' || ($contactPublicId === '' && ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)))) {
    mg_fail('Invalid winner selection.', 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at, rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count
        FROM campaigns c
        INNER JOIN reward_templates rt ON rt.id = c.reward_template_id AND rt.status = \'active\'
        WHERE c.public_id = ? AND c.merchant_user_id = ? AND c.campaign_type = \'contest_giveaway\' AND c.status IN (\'active\',\'ended\')
        LIMIT 1');
    $stmt->execute([$campaignPublicId, $merchantId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('Contest campaign not found or reward template is not active.', 404);
    }

    $rules = mg_winner_rules($campaign);
    $winnerLimit = mg_winner_limit($campaign, $rules);
    $winnerCountStmt = $pdo->prepare('SELECT COUNT(*) FROM wallet_items WHERE campaign_id = ? AND merchant_user_id = ? AND source_type = \'contest_winner\' AND status <> \'cancelled\'');
    $winnerCountStmt->execute([(int)$campaign['id'], $merchantId]);
    $winnerCount = (int)$winnerCountStmt->fetchColumn();
    if ($winnerLimit !== null && $winnerCount >= $winnerLimit) {
        $pdo->rollBack();
        mg_fail('Contest winner limit has been reached.', 409);
    }

    $contactSql = 'SELECT * FROM campaign_contacts WHERE campaign_id = ? AND merchant_user_id = ?';
    $params = [(int) $campaign['id'], $merchantId];
    if ($contactPublicId !== '') {
        $contactSql .= ' AND public_id = ?';
        $params[] = $contactPublicId;
    } else {
        $contactSql .= ' AND email = ?';
        $params[] = $email;
    }
    $contactSql .= ' LIMIT 1';
    $contactStmt = $pdo->prepare($contactSql);
    $contactStmt->execute($params);
    $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) {
        $pdo->rollBack();
        mg_fail('Contest entrant not found.', 404);
    }

    if ($campaign['reward_template_quantity_limit'] !== null && (int) $campaign['reward_template_issued_count'] >= (int) $campaign['reward_template_quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Reward template limit has been reached.', 409);
    }

    $existing = $pdo->prepare('SELECT public_id,status FROM wallet_items WHERE campaign_id = ? AND contact_id = ? AND source_type = \'contest_winner\' AND status <> \'cancelled\' ORDER BY id DESC LIMIT 1');
    $existing->execute([(int) $campaign['id'], (int) $contact['id']]);
    $prior = $existing->fetch(PDO::FETCH_ASSOC);
    if ($prior) {
        $pdo->commit();
        mg_ok(['wallet_item_id' => (string) $prior['public_id'], 'wallet_status' => (string) $prior['status'], 'already_issued' => true], 'Winner reward already issued.');
    }

    mg_winner_event($pdo, $merchantId, (int) $campaign['id'], null, (int) $contact['id'], 'contest.winner_selected', ['contact_id' => (string) $contact['public_id'], 'rules' => $rules]);

    $walletPublicId = mg_winner_uuid();
    $expiresAt = mg_winner_expiry($campaign);
    $wallet = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $wallet->execute([$walletPublicId, $contact['user_id'] ? (int) $contact['user_id'] : null, (int) $contact['id'], $merchantId, (int) $campaign['reward_template_db_id'], (int) $campaign['id'], 'contest_winner', (string) $contact['public_id'], 'issued', (int) $campaign['value_amount_cents'], (string) $campaign['currency'], (string) $campaign['reward_template_title'], json_encode(['campaign_type' => 'contest_giveaway', 'reward_template_id' => (string) $campaign['reward_template_public_id'], 'winner' => true, 'rules' => $rules], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
    $walletDbId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE campaigns SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $campaign['id']]);
    $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $campaign['reward_template_db_id']]);
    $merchantNotification = mg_public_campaign_create_notification($pdo, $merchantId, 'merchant_campaign_winner_selected', 'Contest winner selected', ((string)($contact['name'] ?? '') ?: (string)$contact['email']) . ' received a contest reward for ' . (string)$campaign['title'] . '.', '/merchant-campaigns.php?campaign=' . rawurlencode((string)$campaign['public_id']) . '&contact=' . rawurlencode((string)$contact['public_id']));
    $recipientNotification = ['created' => false, 'reason' => 'missing_recipient_user'];
    if (!empty($contact['user_id'])) {
        $recipientNotification = mg_public_campaign_create_notification($pdo, (int)$contact['user_id'], 'campaign_contest_reward', 'Contest reward issued', 'A contest reward was issued for ' . (string)$campaign['title'] . '.', '/claims.php?wallet=' . rawurlencode($walletPublicId));
    }
    mg_winner_event($pdo, $merchantId, (int) $campaign['id'], $walletDbId, (int) $contact['id'], 'wallet_item.issued', ['wallet_item_id' => $walletPublicId, 'source_type' => 'contest_winner', 'notification' => ['merchant' => $merchantNotification, 'recipient' => $recipientNotification]]);

    $pdo->commit();
    mg_ok(['wallet_item_id' => $walletPublicId, 'wallet_status' => 'issued', 'already_issued' => false, 'expires_at' => $expiresAt, 'notification' => ['merchant' => $merchantNotification, 'recipient' => $recipientNotification]], 'Contest winner reward issued.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.contest_winner.failed', 'Unable to issue contest winner reward.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to issue contest winner reward.', 500);
}
